<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Cash\Services\PaymentSettlementService;
use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Models\Document;
use App\Shared\Services\InternalCodeService;
use App\Shared\Traits\AccountingEngineTrait;
use App\Shared\Traits\ToolTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de Nota Crédito (NC).
 *
 * Flujo:
 *  1. Valida que el documento original no esté ya anulado
 *  2. Crea un nuevo Document con type_document_operation_id = 91 (NC DIAN)
 *  3. reference_id → original document
 *  4. Copia las líneas seleccionadas con movimiento invertido (OUT → IN)
 *     lo que devuelve el stock a la bodega
 *  5. Registra movimiento de egreso en la caja (crédito = devuelve dinero)
 *  6. Actualiza el balance del documento original
 */
class CreditNoteService
{
    use ToolTrait, AccountingEngineTrait;

    // Conceptos de corrección DIAN para NC
    public const CORRECTION_CONCEPTS = [
        1 => 'Devolución parcial de bienes o no aceptación parcial del servicio',
        2 => 'Anulación de factura electrónica',
        3 => 'Rebaja o descuento parcial',
        4 => 'Ajuste de precio',
        5 => 'Otro',
    ];

    public function __construct(
        private readonly InternalCodeService $internalCodeService,
        private readonly PaymentSettlementService $paymentSettlementService
    ) {}

    /**
     * Crea una Nota Crédito a partir de un documento existente.
     *
     * @param  Document  $original         Factura de origen
     * @param  int       $correctionConcept  Concepto DIAN (1-5)
     * @param  string    $note             Observación obligatoria
     * @param  array     $selectedLines    Líneas a devolver: [['line_id' => ..., 'qty' => ...]]
     *                                     Si vacío, devuelve todas las líneas completas
     * @param  int|null  $userId
     */
    public function create(
        Document $original,
        int      $correctionConcept,
        string   $note,
        array    $selectedLines = [],
        ?int     $userId        = null
    ): Document {
        if ($original->annulled) {
            throw ValidationException::withMessages([
                'document' => ['No se puede emitir una Nota Crédito sobre un documento anulado.'],
            ]);
        }

        // Verificar que no haya ya una NC de anulación total
        $hasFullCancellation = $original->creditNotes()
            ->where('annulled', false)
            ->whereRaw("note LIKE '%Anulación%'")
            ->exists();

        if ($hasFullCancellation && $correctionConcept === 2) {
            throw ValidationException::withMessages([
                'document' => ['Ya existe una Nota Crédito de anulación para este documento.'],
            ]);
        }

        return DB::transaction(function () use ($original, $correctionConcept, $note, $selectedLines, $userId) {
            // Construir las líneas de la NC
            $ncLines = $this->buildNcLines($original, $selectedLines);

            if (empty($ncLines)) {
                throw ValidationException::withMessages([
                    'lines' => ['Debe seleccionar al menos una línea con cantidad mayor a 0.'],
                ]);
            }

            // Calcular totales
            $totals                  = $this->calculateTotals($ncLines);
            $invoiceLinesJson        = $this->buildInvoiceLinesJson($ncLines);
            $legalMonetaryTotalsJson = $this->buildLegalMonetaryTotalsJson($totals, $invoiceLinesJson);

            // Código interno
            $internalCode = $this->internalCodeService->reserveInternalCode(91);

            // Concepto textual
            $conceptText = self::CORRECTION_CONCEPTS[$correctionConcept] ?? 'Otro';
            $fullNote    = "[NC Concepto {$correctionConcept}: {$conceptText}] {$note}";

            // Crear el documento NC
            $nc = Document::create([
                'internal_code'               => $internalCode,
                'user_id'                     => $userId ?? $original->user_id,
                'third_party_id'              => $original->third_party_id,
                'reference_id'               => $original->id,
                'type_document_id'            => 91,
                'type_document_operation_id'  => 91,
                'resolution_id'               => $original->resolution_id,
                'prefix'                      => $original->prefix,
                'currency_id'                 => $original->currency_id,
                'issue_date'                  => now()->toDateString(),
                'note'                        => $fullNote,
                'subtotal'                    => $totals['subtotal'],
                'total_discount'              => $totals['total_discount'],
                'total_tax'                   => $totals['total_tax'],
                'total'                       => $totals['total'],
                'balance'                     => 0,   // NC se considera pagada inmediatamente
                'invoice_lines'               => $invoiceLinesJson,
                'legal_monetary_totals'       => $legalMonetaryTotalsJson,
                'paid'                        => true,
                'electronic'                  => false,
                'accounted'                   => false,
                'annulled'                    => false,
            ]);

            // Crear líneas de detalle con movimiento invertido (devuelve stock)
            $this->createNcLines($nc, $ncLines);

            // Revertir inventario para cada línea
            foreach ($ncLines as $line) {
                $itemId = $line['item_id'] ?? null;
                if (! $itemId) continue;

                // Las líneas originales eran OUT → la NC hace IN (devuelve a bodega)
                $warehouseId = $line['warehouse_out'] ?? $line['warehouse_in'] ?? null;
                $mvType      = $line['original_movement'] === 'OUT' ? 'IN' : 'OUT';

                if ($warehouseId && $mvType !== 'NONE') {
                    $this->updateItemInventory(
                        $itemId,
                        $warehouseId,
                        (float) $line['amount'],
                        (float) $line['cost_value'],
                        $mvType
                    );
                }
            }

            // Registrar devolución por los mismos medios originales, hasta cubrir la NC.
            $this->paymentSettlementService->registerCreditNoteRefund($original, $nc);

            // Actualizar balance del documento original
            $newBalance = max(0, (float) $original->balance - $totals['total']);
            $original->update([
                'balance' => $newBalance,
                'paid'    => $newBalance <= 0,
            ]);

            // Si la NC es de anulación total, marcar original como anulado
            if ($correctionConcept === 2 && $totals['total'] >= (float) $original->total) {
                $original->update(['annulled' => true, 'balance' => 0]);
            }

            // Historial
            $this->createDocumentHistory(
                $nc,
                null,
                "NC {$internalCode} emitida sobre {$original->internal_code}. Concepto: {$conceptText}."
            );

            $this->createDocumentHistory(
                $original,
                null,
                "NC {$internalCode} emitida. Nuevo balance: $" . number_format($newBalance, 2)
            );

            // Asiento contable automático de la NC
            $this->generateAccountingEntry($nc);

            return $nc->load(['lines.item', 'thirdParty', 'resolution', 'reference']);
        });
    }

    // ─── Helpers privados ─────────────────────────────────────────────────

    /**
     * Construye el array de líneas para la NC basado en las líneas originales
     * y los items seleccionados para devolver.
     */
    private function buildNcLines(Document $original, array $selectedLines): array
    {
        $original->loadMissing('lines');
        $selMap = collect($selectedLines)->keyBy('line_id'); // [line_id => qty]

        $ncLines = [];
        foreach ($original->lines as $line) {
            // Si no hay selección específica → devolver todo
            if (empty($selectedLines)) {
                $qty = (float) $line->amount;
            } else {
                $sel = $selMap->get($line->id);
                if (! $sel || (float) ($sel['qty'] ?? 0) <= 0) continue;
                $qty = min((float) $sel['qty'], (float) $line->amount);
            }

            $taxes = is_array($line->taxes) ? $line->taxes : json_decode($line->taxes ?? '[]', true);

            $ncLines[] = [
                'item_id'           => $line->item_id,
                'description'       => $line->description ?? $line->item?->name ?? 'Devolución',
                'amount'            => $qty,
                'sale_price'        => (float) $line->sale_price,
                'discount'          => (float) $line->discount,
                'cost_value'        => (float) $line->cost_value,
                'taxable_amount'    => 0, // se recalcula en calcLineTotal
                'tax_amount'        => 0,
                'taxes'             => $taxes,
                'unit_measure_id'   => $line->unit_measure_id,
                'warehouse_out'     => $line->warehouse_out,
                'warehouse_in'      => $line->warehouse_in,
                'original_movement' => $line->movement_type ?? 'NONE',
                // En NC la "entrada" a bodega se registra como IN
                'movement_type'     => $line->movement_type === 'OUT' ? 'IN' : ($line->movement_type === 'IN' ? 'OUT' : 'NONE'),
            ];
        }

        return $ncLines;
    }

    /**
     * Inserta las líneas de la NC en documents_details.
     */
    private function createNcLines(Document $nc, array $lines): void
    {
        $rows = [];
        foreach ($lines as $line) {
            $result  = $this->calcLineTotal($line);
            $rows[] = [
                'document_id'     => $nc->id,
                'item_id'         => $line['item_id'] ?? null,
                'description'     => $line['description'] ?? null,
                'amount'          => $line['amount'],
                'cost_value'      => $line['cost_value'] ?? 0,
                'sale_price'      => $line['sale_price'],
                'discount'        => $line['discount'] ?? 0,
                'taxable_amount'  => $result['taxable_amount'],
                'tax_amount'      => $result['tax_amount'],
                'taxes'           => json_encode($line['taxes'] ?? []),
                'unit_measure_id' => $line['unit_measure_id'] ?? null,
                'warehouse_out'   => $line['movement_type'] === 'OUT' ? ($line['warehouse_out'] ?? null) : null,
                'warehouse_in'    => $line['movement_type'] === 'IN'  ? ($line['warehouse_out'] ?? $line['warehouse_in'] ?? null) : null,
                'movement_type'   => $line['movement_type'],
                'state'           => true,
                'annulled'        => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }
        $nc->lines()->insert($rows);
    }

    // ─── Cálculos (reutilizados de InvoiceService via ToolTrait) ─────────

    private function calculateTotals(array $lines): array
    {
        $subtotal = $totalDiscount = $totalTax = 0.0;
        foreach ($lines as $line) {
            $r              = $this->calcLineTotal($line);
            $subtotal      += $r['subtotal'];
            $totalDiscount += $r['discount_amount'];
            $totalTax      += $r['tax_amount'];
        }
        return [
            'subtotal'       => round($subtotal, 4),
            'total_discount' => round($totalDiscount, 4),
            'total_tax'      => round($totalTax, 4),
            'total'          => round($subtotal - $totalDiscount + $totalTax, 4),
        ];
    }

    private function buildInvoiceLinesJson(array $lines): array
    {
        return array_map(function (array $line) {
            $result    = $this->calcLineTotal($line);
            $taxTotals = [];
            foreach ($line['taxes'] ?? [] as $tax) {
                $pct = (float) ($tax['percent'] ?? 0);
                $amt = round($result['taxable_amount'] * ($pct / 100), 4);
                if ($amt > 0) {
                    $taxTotals[] = [
                        'tax_id'         => $tax['tax_id'] ?? $tax['id'] ?? null,
                        'tax_amount'     => $amt,
                        'percent'        => $pct,
                        'taxable_amount' => $result['taxable_amount'],
                    ];
                }
            }
            return [
                'unit_measure_id'             => $line['unit_measure_id'] ?? 70,
                'invoiced_quantity'           => $line['amount'],
                'line_extension_amount'       => $result['taxable_amount'],
                'allowance_charges'           => [],
                'tax_totals'                  => $taxTotals,
                'description'                 => $line['description'] ?? 'Devolución',
                'price_amount'                => (float) $line['sale_price'],
                'base_quantity'               => $line['amount'],
            ];
        }, $lines);
    }

    private function buildLegalMonetaryTotalsJson(array $totals, array $invoiceLines): array
    {
        $lineExt = array_sum(array_column($invoiceLines, 'line_extension_amount'));
        return [
            'line_extension_amount'  => round($lineExt, 4),
            'tax_exclusive_amount'   => round($lineExt, 4),
            'tax_inclusive_amount'   => round($totals['total'], 4),
            'allowance_total_amount' => round($totals['total_discount'], 4),
            'charge_total_amount'    => 0,
            'payable_amount'         => round($totals['total'], 4),
        ];
    }

    private function calcLineTotal(array $line): array
    {
        $amount      = (float) ($line['amount'] ?? 1);
        $salePrice   = (float) ($line['sale_price'] ?? 0);
        $discountPct = (float) ($line['discount'] ?? 0);

        $subtotal       = $amount * $salePrice;
        $discountAmount = $subtotal * ($discountPct / 100);
        $taxableAmount  = $subtotal - $discountAmount;

        $taxPercent = 0.0;
        foreach ($line['taxes'] ?? [] as $tax) {
            $taxPercent += (float) ($tax['percent'] ?? 0);
        }

        return [
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount'  => round($taxableAmount, 4),
            'tax_amount'      => round($taxableAmount * ($taxPercent / 100), 4),
        ];
    }
}
