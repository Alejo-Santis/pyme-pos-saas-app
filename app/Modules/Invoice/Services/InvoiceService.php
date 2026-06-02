<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Core\Models\Resolution;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Cash\Services\PaymentSettlementService;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentLine;
use App\Shared\Services\InternalCodeService;
use App\Shared\Traits\AccountingEngineTrait;
use App\Shared\Traits\ToolTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de facturación electrónica.
 * Encapsula la lógica de negocio para crear, actualizar y anular documentos.
 *
 * Flujo completo (fiel a xedoc):
 *   1. Generar internal_code atómico (InternalCodeService)
 *   2. Reservar consecutivo de la resolución DIAN (lockForUpdate)
 *   3. Calcular totales financieros desde las líneas
 *   4. Construir JSON invoice_lines y legal_monetary_totals para DIAN
 *   5. Crear Document en BD → dispara DocumentCreateObserver → Job → DIAN
 *   6. Crear DocumentLines (documents_details)
 *   7. Mover inventario (item_warehouse) + registrar kardex (item_stocktakings)
 *   8. Registrar historial inicial del documento
 */
class InvoiceService
{
    use ToolTrait, AccountingEngineTrait;

    public function __construct(
        private readonly InternalCodeService $internalCodeService,
        private readonly PaymentSettlementService $paymentSettlementService
    ) {}

    // ─── CRUD principal ───────────────────────────────────────────────────

    /**
     * Crea un documento con sus líneas, mueve inventario y registra historial.
     */
    public function create(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            $typeOpId = (int) ($data['type_document_operation_id'] ?? 1);

            // 1. Código interno único (D1-00000001, D4-00000001, etc.)
            $internalCode = $this->internalCodeService->reserveInternalCode($typeOpId);
            $systemNumber = $this->numberFromInternalCode($internalCode);

            // 2. Consecutivo de resolución DIAN (si aplica)
            $prefix = $data['prefix'] ?? null;
            $number = $data['number'] ?? $systemNumber;

            if (! empty($data['resolution_id'])) {
                /** @var Resolution $resolution */
                $resolution = Resolution::lockForUpdate()->findOrFail($data['resolution_id']);
                [$prefix, $number] = $this->nextConsecutive($resolution);
            }

            // 3. Calcular totales financieros (incluyendo retenciones)
            $lines        = $data['lines'] ?? [];
            $withholdings = $data['withholdings_tax'] ?? [];
            $totals       = $this->calculateTotals($lines, $withholdings);

            // 4. Construir JSON DIAN (invoice_lines + legal_monetary_totals)
            $invoiceLinesJson         = $this->buildInvoiceLinesJson($lines);
            $legalMonetaryTotalsJson  = $this->buildLegalMonetaryTotalsJson($totals, $invoiceLinesJson);

            // 5. Crear el documento (el Observer disparará el Job DIAN automáticamente)
            $document = Document::create([
                'internal_code'               => $internalCode,
                'system_number'               => $systemNumber,
                'user_id'                     => $data['user_id'],
                'third_party_id'              => $data['third_party_id'] ?? null,
                'seller_id'                   => $data['seller_id'] ?? null,
                'type_document_id'            => $data['type_document_id'],
                'type_document_operation_id'  => $typeOpId,
                'resolution_id'               => $data['resolution_id'] ?? null,
                'prefix'                      => $prefix,
                'number'                      => $number,
                'currency_id'                 => $data['currency_id'] ?? null,
                'issue_date'                  => $data['issue_date'],
                'note'                        => $data['note'] ?? null,
                'subtotal'                    => $totals['subtotal'],
                'total_discount'              => $totals['total_discount'],
                'total_tax'                   => $totals['total_tax'],
                'total'                       => $totals['total'],
                'total_withholding'           => $totals['total_withholding'],
                'balance'                     => round($totals['total'] - $totals['total_withholding'], 4),
                'payment_forms'               => $data['payment_forms'] ?? null,
                'taxes'                       => $data['taxes'] ?? null,
                'withholdings_tax'            => $withholdings ?: null,
                'order_reference'             => $data['order_reference'] ?? null,
                'invoice_lines'               => $invoiceLinesJson,
                'legal_monetary_totals'       => $legalMonetaryTotalsJson,
                'cashier_shift'               => $data['cashier_shift'] ?? null,
                'pos_terminal_id'             => $data['pos_terminal_id'] ?? null,
                'paid'                        => false,
                'electronic'                  => false,
                'accounted'                   => false,
                'annulled'                    => false,
            ]);

            if (! empty($data['payment_forms'])) {
                $this->paymentSettlementService->syncDocumentPaymentMethods($document, $data['payment_forms']);
            }

            // 6. Crear líneas de detalle (documents_details)
            if (! empty($lines)) {
                $this->createLines($document, $lines);
            }

            // 7. Mover inventario y registrar kardex por cada línea
            foreach ($lines as $line) {
                $movementType = $line['movement_type'] ?? 'NONE';
                $itemId       = $line['item_id'] ?? null;

                if ($itemId && $movementType !== 'NONE') {
                    $warehouseId = $movementType === 'OUT'
                        ? ($line['warehouse_out'] ?? null)
                        : ($line['warehouse_in']  ?? null);

                    if ($warehouseId) {
                        $this->updateItemInventory(
                            $itemId,
                            $warehouseId,
                            (float) ($line['amount'] ?? 1),
                            (float) ($line['cost_value'] ?? 0),
                            $movementType
                        );
                        $this->updateItemStocktaking($document, $line, $movementType);
                    }
                }
            }

            // 8. Historial inicial
            $this->createDocumentHistory(
                $document,
                null,
                "Documento {$internalCode} creado."
            );

            // 9. Asiento contable automático (factura y compra)
            $this->generateAccountingEntry($document);

            AuditService::created($document, "Documento {$internalCode} creado por valor {$totals['total']}.", 'Invoice');

            return $document->load(['lines.item', 'thirdParty', 'resolution']);
        });
    }

    /**
     * Actualiza un documento en borrador (no enviado a DIAN, no anulado).
     */
    public function update(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            if ($document->electronic) {
                throw ValidationException::withMessages([
                    'document' => ['No se puede editar un documento ya enviado a la DIAN.'],
                ]);
            }

            if ($document->annulled) {
                throw ValidationException::withMessages([
                    'document' => ['No se puede editar un documento anulado.'],
                ]);
            }

            $lines = $data['lines'] ?? null;

            if ($lines !== null) {
                // Revertir movimientos de inventario de las líneas anteriores
                foreach ($document->lines as $oldLine) {
                    $movementType = $oldLine->movement_type ?? 'NONE';
                    if ($oldLine->item_id && $movementType !== 'NONE') {
                        // Invertir: OUT → IN (devolver al inventario)
                        $reverseType = $movementType === 'OUT' ? 'IN' : 'OUT';
                        $warehouseId = $movementType === 'OUT'
                            ? $oldLine->warehouse_out
                            : $oldLine->warehouse_in;

                        if ($warehouseId) {
                            $this->updateItemInventory(
                                $oldLine->item_id,
                                $warehouseId,
                                (float) $oldLine->amount,
                                (float) $oldLine->cost_value,
                                $reverseType
                            );
                        }
                    }
                }

                // Recalcular totales y JSON DIAN con las nuevas líneas
                $withholdings            = $data['withholdings_tax'] ?? [];
                $totals                  = $this->calculateTotals($lines, $withholdings);
                $invoiceLinesJson        = $this->buildInvoiceLinesJson($lines);
                $legalMonetaryTotalsJson = $this->buildLegalMonetaryTotalsJson($totals, $invoiceLinesJson);

                $data['subtotal']               = $totals['subtotal'];
                $data['total_discount']         = $totals['total_discount'];
                $data['total_tax']              = $totals['total_tax'];
                $data['total']                  = $totals['total'];
                $data['total_withholding']      = $totals['total_withholding'];
                $data['balance']                = round($totals['total'] - $totals['total_withholding'], 4);
                $data['invoice_lines']          = $invoiceLinesJson;
                $data['legal_monetary_totals']  = $legalMonetaryTotalsJson;

                // Eliminar líneas anteriores y recrear
                $document->lines()->delete();
                $this->createLines($document, $lines);

                // Aplicar nuevos movimientos de inventario
                foreach ($lines as $line) {
                    $movementType = $line['movement_type'] ?? 'NONE';
                    $itemId       = $line['item_id'] ?? null;

                    if ($itemId && $movementType !== 'NONE') {
                        $warehouseId = $movementType === 'OUT'
                            ? ($line['warehouse_out'] ?? null)
                            : ($line['warehouse_in']  ?? null);

                        if ($warehouseId) {
                            $this->updateItemInventory(
                                $itemId,
                                $warehouseId,
                                (float) ($line['amount'] ?? 1),
                                (float) ($line['cost_value'] ?? 0),
                                $movementType
                            );
                            $this->updateItemStocktaking($document, $line, $movementType);
                        }
                    }
                }
            }

            $original = $document->getOriginal();

            $document->update(collect($data)->except('lines')->toArray());

            $this->createDocumentHistory(
                $document,
                null,
                "Documento {$document->internal_code} actualizado."
            );

            AuditService::updated($document, $original, "Documento {$document->internal_code} actualizado.", 'Invoice');

            return $document->load(['lines.item', 'thirdParty', 'resolution']);
        });
    }

    /**
     * Anula un documento en borrador (no enviado a DIAN).
     */
    public function annul(Document $document): Document
    {
        if ($document->electronic) {
            throw ValidationException::withMessages([
                'document' => ['Un documento enviado a la DIAN no puede anularse aquí. Use una nota crédito.'],
            ]);
        }

        DB::transaction(function () use ($document) {
            // Revertir movimientos de inventario
            foreach ($document->lines as $line) {
                $movementType = $line->movement_type ?? 'NONE';
                if ($line->item_id && $movementType !== 'NONE') {
                    $reverseType = $movementType === 'OUT' ? 'IN' : 'OUT';
                    $warehouseId = $movementType === 'OUT'
                        ? $line->warehouse_out
                        : $line->warehouse_in;

                    if ($warehouseId) {
                        $this->updateItemInventory(
                            $line->item_id,
                            $warehouseId,
                            (float) $line->amount,
                            (float) $line->cost_value,
                            $reverseType
                        );
                    }
                }
            }

            $document->update(['annulled' => true, 'balance' => 0]);

            $this->createDocumentHistory(
                $document,
                null,
                "Documento {$document->internal_code} anulado."
            );

            AuditService::deleted($document, "Documento {$document->internal_code} anulado.", 'Invoice');
        });

        return $document;
    }

    // ─── Métodos privados ─────────────────────────────────────────────────

    /**
     * Crea las líneas de detalle en documents_details.
     */
    private function createLines(Document $document, array $lines): void
    {
        $linesData = array_map(function (array $line) use ($document) {
            $lineResult = $this->calcLineTotal($line);

            return [
                'document_id'     => $document->id,
                'item_id'         => $line['item_id'] ?? null,
                'description'     => $line['description'] ?? null,
                'item_note'       => $line['item_note'] ?? null,
                'amount'          => $line['amount'] ?? 1,
                'cost_value'      => $line['cost_value'] ?? 0,
                'sale_price'      => $line['sale_price'],
                'discount'        => $line['discount'] ?? 0,
                'taxable_amount'  => $lineResult['taxable_amount'],
                'tax_amount'      => $lineResult['tax_amount'],
                'taxes'           => json_encode($line['taxes'] ?? []),
                'unit_measure_id' => $line['unit_measure_id'] ?? null,
                'warehouse_out'   => $line['warehouse_out'] ?? null,
                'warehouse_in'    => $line['warehouse_in'] ?? null,
                'movement_type'   => $line['movement_type'] ?? 'NONE',
                'state'           => true,
                'annulled'        => false,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }, $lines);

        $document->lines()->insert($linesData);
    }

    /**
     * Calcula totales del documento desde las líneas.
     */
    private function calculateTotals(array $lines, array $withholdings = []): array
    {
        $subtotal      = 0.0;
        $totalDiscount = 0.0;
        $totalTax      = 0.0;

        foreach ($lines as $line) {
            $result         = $this->calcLineTotal($line);
            $subtotal      += $result['subtotal'];
            $totalDiscount += $result['discount_amount'];
            $totalTax      += $result['tax_amount'];
        }

        $total = $subtotal - $totalDiscount + $totalTax;

        // Retenciones (ReteIVA, ReteICA, ReteFuente)
        // El total bruto de la factura NO cambia (DIAN lo ve completo).
        // El saldo neto a cobrar = total - retenciones (el comprador descuenta al pagar).
        $totalWithholding = 0.0;
        foreach ($withholdings as $w) {
            $totalWithholding += (float) ($w['withholding_amount'] ?? $w['tax_amount'] ?? 0);
        }

        return [
            'subtotal'          => round($subtotal, 4),
            'total_discount'    => round($totalDiscount, 4),
            'total_tax'         => round($totalTax, 4),
            'total'             => round($total, 4),
            'total_withholding' => round($totalWithholding, 4),
        ];
    }

    /**
     * Calcula los valores de una sola línea.
     */
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

        $taxAmount = $taxableAmount * ($taxPercent / 100);

        return [
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount'  => round($taxableAmount, 4),
            'tax_amount'      => round($taxAmount, 4),
        ];
    }

    /**
     * Construye el JSON invoice_lines para el builder DIAN.
     * Cada línea incluye los campos requeridos por UBL 2.1 / Nextpyme.
     */
    private function buildInvoiceLinesJson(array $lines): array
    {
        return array_map(function (array $line, int $index) {
            $result = $this->calcLineTotal($line);

            $taxTotals = [];
            foreach ($line['taxes'] ?? [] as $tax) {
                $percent    = (float) ($tax['percent'] ?? 0);
                $taxAmount  = round($result['taxable_amount'] * ($percent / 100), 4);

                if ($taxAmount > 0) {
                    $taxTotals[] = [
                        'tax_id'         => $tax['tax_id'] ?? $tax['id'] ?? null,
                        'tax_amount'     => $taxAmount,
                        'percent'        => $percent,
                        'taxable_amount' => $result['taxable_amount'],
                    ];
                }
            }

            return [
                'unit_measure_id'         => $line['unit_measure_id'] ?? 70,
                'invoiced_quantity'        => $line['amount'] ?? 1,
                'line_extension_amount'    => $result['taxable_amount'],
                'allowance_charges'        => $result['discount_amount'] > 0
                    ? [[
                        'charge_indicator'          => false,
                        'allowance_charge_reason'   => 'Descuento',
                        'amount'                    => $result['discount_amount'],
                        'base_amount'               => round((float)($line['amount'] ?? 1) * (float)($line['sale_price'] ?? 0), 4),
                    ]]
                    : [],
                'tax_totals'               => $taxTotals,
                'description'              => $line['description'] ?? ($line['name'] ?? 'Producto'),
                'code'                     => $line['internal_code'] ?? $line['item_id'] ?? '',
                'type_item_identification_id' => $line['type_item_identification_id'] ?? 4,
                'price_amount'             => (float) ($line['sale_price'] ?? 0),
                'base_quantity'            => $line['amount'] ?? 1,
            ];
        }, $lines, array_keys($lines));
    }

    /**
     * Construye el JSON legal_monetary_totals para el builder DIAN (UBL 2.1).
     */
    private function buildLegalMonetaryTotalsJson(array $totals, array $invoiceLines): array
    {
        $lineExtensionAmount = array_sum(array_column($invoiceLines, 'line_extension_amount'));

        return [
            'line_extension_amount'  => round($lineExtensionAmount, 4),
            'tax_exclusive_amount'   => round($lineExtensionAmount, 4),
            'tax_inclusive_amount'   => round($totals['total'], 4),
            'allowance_total_amount' => round($totals['total_discount'], 4),
            'charge_total_amount'    => 0,
            'payable_amount'         => round($totals['total'], 4),
        ];
    }

    /**
     * Reserva el siguiente número de la resolución DIAN atómicamente.
     * Requiere que el modelo Resolution ya esté bloqueado con lockForUpdate().
     *
     * @return array{string|null, int}  [prefix, number]
     * @throws ValidationException si el rango está agotado
     */
    private function nextConsecutive(Resolution $resolution): array
    {
        $next = $resolution->current_number + 1;

        if ($next > $resolution->to) {
            throw ValidationException::withMessages([
                'resolution_id' => [
                    "La resolución {$resolution->resolution} ha agotado su rango hasta {$resolution->to}.",
                ],
            ]);
        }

        $resolution->update(['current_number' => $next]);

        return [$resolution->prefix, $next];
    }

    private function numberFromInternalCode(string $internalCode): int
    {
        if (preg_match('/-(\d+)$/', $internalCode, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }
}
