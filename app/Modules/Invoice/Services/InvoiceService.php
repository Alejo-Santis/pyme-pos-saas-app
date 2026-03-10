<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Core\Models\Resolution;
use App\Modules\Invoice\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de facturación electrónica.
 * Encapsula la lógica de negocio para crear, actualizar y anular documentos.
 */
class InvoiceService
{
    /**
     * Crea un documento (factura, NC, ND, soporte) con sus líneas.
     * Calcula automáticamente subtotal, impuestos y total.
     */
    public function create(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            // 1. Asignar número correlativo desde resolución
            if (isset($data['resolution_id'])) {
                /** @var Resolution $resolution */
                $resolution = Resolution::lockForUpdate()->findOrFail($data['resolution_id']);
                $data['number'] = $this->nextNumber($resolution);
                $data['prefix'] = $data['prefix'] ?? $resolution->prefix;
            }

            // 2. Calcular totales desde las líneas
            $lines = $data['lines'] ?? [];
            $totals = $this->calculateTotals($lines);

            $data['subtotal']       = $totals['subtotal'];
            $data['total_tax']      = $totals['total_tax'];
            $data['total_discount'] = $totals['total_discount'];
            $data['total']          = $totals['total'];
            $data['balance']        = $totals['total']; // saldo inicial = total

            // 3. Crear el documento
            $document = Document::create(collect($data)->except('lines')->toArray());

            // 4. Crear las líneas del documento
            if (!empty($lines)) {
                $this->createLines($document, $lines);
            }

            // 5. Retornar documento con líneas cargadas
            return $document->load(['lines.item', 'thirdParty', 'resolution', 'company']);
        });
    }

    /**
     * Actualiza un documento existente (solo en estado borrador / no enviado a DIAN).
     */
    public function update(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            // Solo se pueden editar documentos no enviados a DIAN y no anulados
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

            // Recalcular totales si vienen líneas nuevas
            $lines = $data['lines'] ?? null;

            if ($lines !== null) {
                $totals = $this->calculateTotals($lines);
                $data['subtotal']       = $totals['subtotal'];
                $data['total_tax']      = $totals['total_tax'];
                $data['total_discount'] = $totals['total_discount'];
                $data['total']          = $totals['total'];
                $data['balance']        = $totals['total'];

                // Eliminar líneas anteriores y recrear
                $document->lines()->delete();
                $this->createLines($document, $lines);
            }

            $document->update(collect($data)->except('lines')->toArray());

            return $document->load(['lines.item', 'thirdParty', 'resolution', 'company']);
        });
    }

    /**
     * Anula un documento (solo si no fue enviado a DIAN).
     */
    public function annul(Document $document): Document
    {
        if ($document->electronic) {
            throw ValidationException::withMessages([
                'document' => ['Un documento enviado a la DIAN no puede anularse desde aquí. Use una nota crédito.'],
            ]);
        }

        $document->update(['annulled' => true]);

        return $document;
    }

    // ─── Métodos privados ─────────────────────────────────────────────────

    /**
     * Crea las líneas de detalle del documento.
     */
    private function createLines(Document $document, array $lines): void
    {
        $linesData = array_map(function (array $line) use ($document) {
            $lineTotal = $this->calcLineTotal($line);

            return [
                'document_id'    => $document->id,
                'item_id'        => $line['item_id'] ?? null,
                'amount'         => $line['amount'] ?? 1,
                'cost_value'     => $line['cost_value'] ?? 0,
                'sale_price'     => $line['sale_price'],
                'discount'       => $line['discount'] ?? 0,
                'taxable_amount' => $lineTotal['taxable_amount'],
                'tax_amount'     => $lineTotal['tax_amount'],
                'taxes'          => json_encode($line['taxes'] ?? []),
                'unit_measure_id'=> $line['unit_measure_id'] ?? null,
                'warehouse_out'  => $line['warehouse_out'] ?? null,
                'warehouse_in'   => $line['warehouse_in'] ?? null,
                'movement_type'  => $line['movement_type'] ?? 'NONE',
                'annulled'       => false,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }, $lines);

        $document->lines()->insert($linesData);
    }

    /**
     * Calcula los totales del documento a partir de las líneas.
     *
     * @param  array $lines  Arreglo de líneas con: amount, sale_price, discount, taxes[]
     * @return array{subtotal: float, total_discount: float, total_tax: float, total: float}
     */
    private function calculateTotals(array $lines): array
    {
        $subtotal       = 0.0;
        $totalDiscount  = 0.0;
        $totalTax       = 0.0;

        foreach ($lines as $line) {
            $lineResult    = $this->calcLineTotal($line);
            $subtotal      += $lineResult['subtotal'];
            $totalDiscount += $lineResult['discount_amount'];
            $totalTax      += $lineResult['tax_amount'];
        }

        $total = $subtotal - $totalDiscount + $totalTax;

        return [
            'subtotal'       => round($subtotal, 4),
            'total_discount' => round($totalDiscount, 4),
            'total_tax'      => round($totalTax, 4),
            'total'          => round($total, 4),
        ];
    }

    /**
     * Calcula los valores de una sola línea.
     *
     * @param  array $line  {amount, sale_price, discount (%), taxes[{percent}]}
     * @return array{subtotal, discount_amount, taxable_amount, tax_amount}
     */
    private function calcLineTotal(array $line): array
    {
        $amount       = (float) ($line['amount'] ?? 1);
        $salePrice    = (float) ($line['sale_price'] ?? 0);
        $discountPct  = (float) ($line['discount'] ?? 0);

        $subtotal       = $amount * $salePrice;
        $discountAmount = $subtotal * ($discountPct / 100);
        $taxableAmount  = $subtotal - $discountAmount;

        // Sumar porcentajes de todos los impuestos de la línea
        $taxPercent = 0.0;
        foreach ($line['taxes'] ?? [] as $tax) {
            $taxPercent += (float) ($tax['percent'] ?? 0);
        }

        $taxAmount = $taxableAmount * ($taxPercent / 100);

        return [
            'subtotal'       => $subtotal,
            'discount_amount'=> $discountAmount,
            'taxable_amount' => round($taxableAmount, 4),
            'tax_amount'     => round($taxAmount, 4),
        ];
    }

    /**
     * Obtiene el siguiente número de la resolución y lo reserva atómicamente.
     *
     * @throws ValidationException si el rango de la resolución está agotado.
     */
    private function nextNumber(Resolution $resolution): int
    {
        $next = $resolution->current_number + 1;

        if ($next > $resolution->to) {
            throw ValidationException::withMessages([
                'resolution_id' => [
                    "La resolución {$resolution->resolution} ha agotado su rango autorizado (hasta {$resolution->to}).",
                ],
            ]);
        }

        $resolution->update(['current_number' => $next]);

        return $next;
    }
}
