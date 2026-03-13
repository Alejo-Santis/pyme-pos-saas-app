<?php

namespace App\Modules\Invoice\DTO;

use Carbon\Carbon;

/**
 * DTO de las líneas de factura para el JSON UBL 2.1 de Nextpyme.
 */
class InvoiceLines
{
    protected array $data;

    public function __construct(array $lines, int $typeDocumentOperationId = 1)
    {
        $this->data = $this->normalize($lines, $typeDocumentOperationId);
    }

    protected function normalize(array $lines, int $typeDocumentOperationId): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $qty        = (float) ($line['invoiced_quantity'] ?? $line['amount'] ?? 1);
            $lineExt    = (float) ($line['line_extension_amount'] ?? (($line['sale_price'] ?? 0) * $qty));
            $priceAmt   = $qty > 0 ? $this->round($lineExt / $qty) : 0;

            $item = [
                'code'                       => $line['code'] ?? $line['internal_code'] ?? null,
                'description'                => $line['description'] ?? $line['name'] ?? '',
                'price_amount'               => $priceAmt,
                'base_quantity'              => $line['base_quantity'] ?? $qty,
                'unit_measure_id'            => $line['unit_measure_id'] ?? 70,   // 70 = Unidad
                'invoiced_quantity'          => $qty,
                'line_extension_amount'      => $this->round($lineExt),
                'free_of_charge_indicator'   => $line['free_of_charge_indicator'] ?? false,
                'type_item_identification_id'=> $line['type_item_identification_id'] ?? 4,
            ];

            // Línea de servicio en documento soporte
            if ($typeDocumentOperationId === 5) {
                $item['type_generation_transmition_id'] = 1;
                $item['start_date'] = Carbon::now()->format('Y-m-d');
            }

            // Impuestos de la línea
            if (! empty($line['tax_totals'])) {
                $item['tax_totals'] = (new Taxes($line['tax_totals']))->toArray();
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private function round(float $value, int $decimals = 2): float
    {
        return round($value, $decimals);
    }
}
