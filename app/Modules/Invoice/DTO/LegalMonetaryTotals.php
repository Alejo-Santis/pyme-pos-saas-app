<?php

namespace App\Modules\Invoice\DTO;

/**
 * DTO de totales monetarios legales para el JSON UBL 2.1.
 * Si se pasan invoice_lines, recalcula los totales desde las líneas.
 */
class LegalMonetaryTotals
{
    protected array $data;

    public function __construct(array $data, array $invoiceLines = [])
    {
        $this->data = ! empty($invoiceLines)
            ? $this->computeFromLines($invoiceLines)
            : $this->normalize($data);
    }

    protected function normalize(array $data): array
    {
        return [
            'line_extension_amount'  => $this->r($data['line_extension_amount']  ?? 0),
            'tax_exclusive_amount'   => $this->r($data['tax_exclusive_amount']   ?? 0),
            'tax_inclusive_amount'   => $this->r($data['tax_inclusive_amount']   ?? 0),
            'allowance_total_amount' => $this->r($data['allowance_total_amount'] ?? 0),
            'charge_total_amount'    => $this->r($data['charge_total_amount']    ?? 0),
            'pre_paid_amount'        => $this->r($data['pre_paid_amount']        ?? 0),
            'payable_amount'         => $this->r($data['payable_amount']         ?? 0),
        ];
    }

    protected function computeFromLines(array $lines): array
    {
        $lineExt   = 0;
        $taxExcl   = 0;
        $taxIncl   = 0;
        $allowance = 0;
        $taxTotal  = 0;

        foreach ($lines as $line) {
            $ext      = (float) ($line['line_extension_amount'] ?? 0);
            $taxAmt   = (float) ($line['tax_total']             ?? 0);
            $catId    = $line['tax_category_id'] ?? null;
            $hasTaxes = ! empty($line['tax_totals']);

            $lineExt += $ext;
            $taxIncl += $ext + $taxAmt;

            if ($hasTaxes && in_array($catId, [1, 3])) {
                $taxExcl += $ext;
            }

            if (! empty($line['allowance_charges'])) {
                foreach ($line['allowance_charges'] as $charge) {
                    $allowance += (float) ($charge['amount'] ?? 0);
                }
            }

            $taxTotal += $taxAmt;
        }

        $payable = $lineExt + $taxTotal;

        return [
            'line_extension_amount'  => $this->r($lineExt),
            'tax_exclusive_amount'   => $this->r($taxExcl),
            'tax_inclusive_amount'   => $this->r($taxIncl),
            'allowance_total_amount' => $this->r($allowance),
            'charge_total_amount'    => 0.00,
            'pre_paid_amount'        => 0.00,
            'payable_amount'         => $this->r($payable),
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private function r(float $v, int $d = 2): float
    {
        return round($v, $d);
    }
}
