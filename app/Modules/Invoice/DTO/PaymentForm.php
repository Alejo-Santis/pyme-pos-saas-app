<?php

namespace App\Modules\Invoice\DTO;

use Carbon\Carbon;

/**
 * DTO de la forma de pago para el JSON UBL 2.1.
 */
class PaymentForm
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $this->normalize($data);
    }

    protected function normalize(array $data): array
    {
        $first = $data[0] ?? $data;

        $dueDate = isset($first['payment_due_date'])
            ? Carbon::parse($first['payment_due_date'])->format('Y-m-d')
            : now()->format('Y-m-d');

        $duration = (int) Carbon::now()->startOfDay()
            ->diffInDays(Carbon::parse($dueDate)->startOfDay(), false);

        return [
            'payment_form_id'   => $first['payment_form_id']   ?? 1,   // 1=Contado
            'payment_method_id' => $first['payment_method_id'] ?? 10,  // 10=Efectivo
            'payment_due_date'  => $dueDate,
            'duration_measure'  => $duration,
        ];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
