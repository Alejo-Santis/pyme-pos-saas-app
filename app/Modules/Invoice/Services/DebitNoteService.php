<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Invoice\Models\Document;
use App\Shared\Services\InternalCodeService;
use App\Shared\Traits\AccountingEngineTrait;
use App\Shared\Traits\ToolTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de Nota Débito (ND) — tipo DIAN 92.
 *
 * La Nota Débito es el opuesto de la Nota Crédito: se emite para cobrar
 * cargos ADICIONALES sobre una factura existente (intereses, gastos, ajustes).
 *
 * Diferencias clave respecto a la NC:
 *  - No devuelve stock (no hay movimiento de inventario)
 *  - AUMENTA el balance del documento original (cargo al cliente)
 *  - Las líneas son nuevos cargos, no devoluciones de líneas originales
 *  - Se registra un movimiento de DÉBITO (ingreso de dinero por el cargo)
 */
class DebitNoteService
{
    use ToolTrait, AccountingEngineTrait;

    // Conceptos de corrección DIAN para ND
    public const CORRECTION_CONCEPTS = [
        1 => 'Intereses',
        2 => 'Gastos por cobrar',
        3 => 'Cambio del valor del impuesto facturado',
        4 => 'Otro',
    ];

    public function __construct(
        private readonly InternalCodeService $internalCodeService
    ) {}

    /**
     * Crea una Nota Débito a partir de un documento existente.
     *
     * @param  Document  $original          Factura de origen
     * @param  int       $correctionConcept Concepto DIAN (1-4)
     * @param  string    $note              Observación obligatoria
     * @param  array     $lines             Líneas de cargos adicionales:
     *                                      [['description' => '...', 'amount' => 1, 'sale_price' => 50000, 'taxes' => [...]]]
     * @param  string|null $userId
     */
    public function create(
        Document $original,
        int      $correctionConcept,
        string   $note,
        array    $lines,
        ?string  $userId = null
    ): Document {
        if ($original->annulled) {
            throw ValidationException::withMessages([
                'document' => ['No se puede emitir una Nota Débito sobre un documento anulado.'],
            ]);
        }

        if (empty($lines)) {
            throw ValidationException::withMessages([
                'lines' => ['La Nota Débito requiere al menos una línea de cargo.'],
            ]);
        }

        return DB::transaction(function () use ($original, $correctionConcept, $note, $lines, $userId) {
            // Calcular totales
            $totals                  = $this->calculateTotals($lines);
            $invoiceLinesJson        = $this->buildInvoiceLinesJson($lines);
            $legalMonetaryTotalsJson = $this->buildLegalMonetaryTotalsJson($totals, $invoiceLinesJson);

            // Código interno
            $internalCode = $this->internalCodeService->reserveInternalCode(92);

            // Concepto textual
            $conceptText = self::CORRECTION_CONCEPTS[$correctionConcept] ?? 'Otro';
            $fullNote    = "[ND Concepto {$correctionConcept}: {$conceptText}] {$note}";

            // Crear el documento ND
            $nd = Document::create([
                'internal_code'               => $internalCode,
                'user_id'                     => $userId ?? $original->user_id,
                'third_party_id'              => $original->third_party_id,
                'reference_id'                => $original->id,
                'type_document_id'            => 92,
                'type_document_operation_id'  => 92,
                'resolution_id'               => $original->resolution_id,
                'prefix'                      => $original->prefix,
                'currency_id'                 => $original->currency_id ?? 35,
                'issue_date'                  => now()->toDateString(),
                'note'                        => $fullNote,
                'subtotal'                    => $totals['subtotal'],
                'total_discount'              => $totals['total_discount'],
                'total_tax'                   => $totals['total_tax'],
                'total'                       => $totals['total'],
                'balance'                     => $totals['total'],  // ND genera saldo por cobrar
                'invoice_lines'               => $invoiceLinesJson,
                'legal_monetary_totals'       => $legalMonetaryTotalsJson,
                'paid'                        => false,
                'electronic'                  => false,
                'accounted'                   => false,
                'annulled'                    => false,
            ]);

            // Crear líneas del cargo adicional (sin movimiento de inventario)
            $this->createNdLines($nd, $lines);

            // Aumentar el balance del documento original
            $newBalance = (float) $original->balance + $totals['total'];
            $original->update([
                'balance' => $newBalance,
                'paid'    => false,  // vuelve a estar pendiente si estaba pagado
            ]);

            // Historial
            $this->createDocumentHistory(
                $nd,
                null,
                "ND {$internalCode} emitida sobre {$original->internal_code}. Concepto: {$conceptText}."
            );

            $this->createDocumentHistory(
                $original,
                null,
                "ND {$internalCode} emitida. Nuevo saldo: $" . number_format($newBalance, 2)
            );

            // Asiento contable automático de la ND
            $this->generateAccountingEntry($nd);

            return $nd->load(['lines.item', 'thirdParty', 'resolution', 'reference']);
        });
    }

    // ─── Helpers privados ─────────────────────────────────────────────────

    /**
     * Inserta las líneas del cargo adicional.
     * Las ND no tienen movimiento de inventario (movement_type = 'NONE').
     */
    private function createNdLines(Document $nd, array $lines): void
    {
        $rows = [];
        foreach ($lines as $line) {
            $result = $this->calcLineTotal($line);
            $rows[] = [
                'document_id'     => $nd->id,
                'item_id'         => $line['item_id'] ?? null,
                'description'     => $line['description'] ?? 'Cargo adicional',
                'amount'          => (float) ($line['amount'] ?? 1),
                'cost_value'      => 0,
                'sale_price'      => (float) ($line['sale_price'] ?? 0),
                'discount'        => (float) ($line['discount'] ?? 0),
                'taxable_amount'  => $result['taxable_amount'],
                'tax_amount'      => $result['tax_amount'],
                'taxes'           => json_encode($line['taxes'] ?? []),
                'unit_measure_id' => $line['unit_measure_id'] ?? 70,
                'warehouse_out'   => null,
                'warehouse_in'    => null,
                'movement_type'   => 'NONE',   // ND no mueve inventario
                'state'           => true,
                'annulled'        => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }
        $nd->lines()->insert($rows);
    }

    // ─── Cálculos (mismos helpers que CreditNoteService) ──────────────────

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
                'unit_measure_id'       => $line['unit_measure_id'] ?? 70,
                'invoiced_quantity'     => $line['amount'],
                'line_extension_amount' => $result['taxable_amount'],
                'allowance_charges'     => [],
                'tax_totals'            => $taxTotals,
                'description'           => $line['description'] ?? 'Cargo adicional',
                'price_amount'          => (float) ($line['sale_price'] ?? 0),
                'base_quantity'         => $line['amount'] ?? 1,
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
