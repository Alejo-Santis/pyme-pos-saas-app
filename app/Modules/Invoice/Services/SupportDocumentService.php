<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Models\Document;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Shared\Services\InternalCodeService;
use App\Shared\Traits\AccountingEngineTrait;
use App\Shared\Traits\ToolTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de Documento Soporte (DS) — tipo DIAN 05.
 *
 * El Documento Soporte se emite cuando se compra a un proveedor NO obligado
 * a facturar (persona natural, régimen simplificado). En ese caso el COMPRADOR
 * es quien debe expedir el soporte para soportar el costo ante la DIAN.
 *
 * Flujo:
 *  1. La orden de compra debe estar en estado 'received'
 *  2. No puede tener ya un DS asociado (purchase_order.document_id)
 *  3. Se crea un Document tipo 05 con las líneas recibidas de la OC
 *  4. Se vincula el DS a la OC (document_id en la tabla purchase_orders)
 *  5. Asiento contable automático
 */
class SupportDocumentService
{
    use ToolTrait, AccountingEngineTrait;

    public function __construct(
        private readonly InternalCodeService $internalCodeService
    ) {}

    /**
     * Genera un Documento Soporte a partir de una Orden de Compra recibida.
     *
     * @param  PurchaseOrder $order   OC ya recibida
     * @param  string|null   $note    Observación opcional
     * @param  string|null   $userId  Usuario que genera el documento
     */
    public function createFromPurchaseOrder(
        PurchaseOrder $order,
        ?string       $note   = null,
        ?string       $userId = null
    ): Document {
        // Validaciones previas
        if ($order->status !== 'received') {
            throw ValidationException::withMessages([
                'order' => ['Solo se puede emitir un Documento Soporte sobre una OC recibida.'],
            ]);
        }

        if ($order->document_id) {
            throw ValidationException::withMessages([
                'order' => ['Esta OC ya tiene un Documento Soporte asociado.'],
            ]);
        }

        if ($order->annulled) {
            throw ValidationException::withMessages([
                'order' => ['No se puede emitir un DS sobre una OC anulada.'],
            ]);
        }

        return DB::transaction(function () use ($order, $note, $userId) {
            $order->load('items.item');

            // Construir líneas desde los ítems recibidos
            $lines = $order->items->map(function ($item) {
                return [
                    'description' => $item->item?->name ?? 'Ítem sin nombre',
                    'item_id'     => $item->item_id,
                    'amount'      => (float) ($item->received_quantity ?? $item->invoice_quantity),
                    'sale_price'  => (float) ($item->average_cost ?? 0),
                    'discount'    => 0.0,
                    'taxes'       => [],
                ];
            })->toArray();

            $totals                  = $this->calculateTotals($lines);
            $invoiceLinesJson        = $this->buildInvoiceLinesJson($lines);
            $legalMonetaryTotalsJson = $this->buildLegalMonetaryTotalsJson($totals, $invoiceLinesJson);

            $internalCode = $this->internalCodeService->reserveInternalCode(5);

            $fullNote = trim(
                "DS generado desde OC {$order->internal_code}." .
                ($note ? " {$note}" : '')
            );

            $ds = Document::create([
                'internal_code'               => $internalCode,
                'user_id'                     => $userId,
                'third_party_id'              => $order->third_party_id,
                'reference_id'                => null,
                'type_document_id'            => 5,
                'type_document_operation_id'  => 5,
                'resolution_id'               => null,
                'prefix'                      => 'DS',
                'currency_id'                 => 35,   // COP
                'issue_date'                  => now()->toDateString(),
                'note'                        => $fullNote,
                'subtotal'                    => $totals['subtotal'],
                'total_discount'              => $totals['total_discount'],
                'total_tax'                   => $totals['total_tax'],
                'total'                       => $totals['total'],
                'balance'                     => $totals['total'],
                'invoice_lines'               => $invoiceLinesJson,
                'legal_monetary_totals'       => $legalMonetaryTotalsJson,
                'paid'                        => false,
                'electronic'                  => false,
                'accounted'                   => false,
                'annulled'                    => false,
            ]);

            // Crear líneas del DS (sin movimiento de inventario, ya fue recibida)
            $this->createDsLines($ds, $lines);

            // Vincular el DS a la OC
            $order->update(['document_id' => $ds->id]);

            // Historial
            $this->createDocumentHistory(
                $ds,
                null,
                "DS {$internalCode} generado desde OC {$order->internal_code}."
            );

            // Asiento contable (gasto de compra)
            $this->generateAccountingEntry($ds);

            return $ds->load(['lines.item', 'thirdParty']);
        });
    }

    // ─── Helpers privados ──────────────────────────────────────────────────

    private function createDsLines(Document $ds, array $lines): void
    {
        $rows = [];
        foreach ($lines as $line) {
            $result = $this->calcLineTotal($line);
            $rows[] = [
                'document_id'     => $ds->id,
                'item_id'         => $line['item_id'] ?? null,
                'description'     => $line['description'],
                'amount'          => (float) $line['amount'],
                'cost_value'      => (float) $line['sale_price'],
                'sale_price'      => (float) $line['sale_price'],
                'discount'        => 0.0,
                'taxable_amount'  => $result['taxable_amount'],
                'tax_amount'      => $result['tax_amount'],
                'taxes'           => json_encode([]),
                'unit_measure_id' => $line['unit_measure_id'] ?? 70,
                'warehouse_out'   => null,
                'warehouse_in'    => null,
                'movement_type'   => 'NONE',   // inventario ya fue movido al recibir la OC
                'state'           => true,
                'annulled'        => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }
        $ds->lines()->insert($rows);
    }

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
            $result = $this->calcLineTotal($line);
            return [
                'unit_measure_id'       => $line['unit_measure_id'] ?? 70,
                'invoiced_quantity'     => $line['amount'],
                'line_extension_amount' => $result['taxable_amount'],
                'allowance_charges'     => [],
                'tax_totals'            => [],
                'description'           => $line['description'],
                'price_amount'          => (float) $line['sale_price'],
                'base_quantity'         => $line['amount'],
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
