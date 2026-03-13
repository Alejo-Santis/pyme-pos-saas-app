<?php

namespace App\Modules\Invoice\DTO;

/**
 * DTO de impuestos para el JSON UBL 2.1.
 */
class Taxes
{
    protected array $data;

    public function __construct(array $taxes)
    {
        $this->data = $this->normalize($taxes);
    }

    protected function normalize(array $taxes): array
    {
        $normalized = [];

        foreach ($taxes as $tax) {
            $taxAmount     = (float) ($tax['tax_amount']    ?? 0);
            $taxableAmount = (float) ($tax['taxable_amount'] ?? 0);

            // Omitir líneas de impuesto en cero
            if (abs($taxAmount) < 0.0001 && abs($taxableAmount) < 0.0001) {
                continue;
            }

            $item = [
                'tax_id'         => (int) ($tax['tax_id'] ?? 1),
                'tax_amount'     => round($taxAmount, 2),
                'percent'        => $tax['percent'] ?? 0,
                'taxable_amount' => round($taxableAmount, 2),
            ];

            if (isset($tax['unit_measure_id']))   $item['unit_measure_id']   = $tax['unit_measure_id'];
            if (isset($tax['per_unit_amount']))    $item['per_unit_amount']   = $tax['per_unit_amount'];
            if (isset($tax['base_unit_measure']))  $item['base_unit_measure'] = $tax['base_unit_measure'];

            $normalized[] = $item;
        }

        return $normalized;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
