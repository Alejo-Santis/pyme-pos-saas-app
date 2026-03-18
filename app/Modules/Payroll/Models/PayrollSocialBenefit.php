<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollSocialBenefit extends Model
{
    use HasUuids;

    const TYPE_PRIMA              = 'prima';
    const TYPE_CESANTIAS          = 'cesantias';
    const TYPE_INTERESES_CESANTIAS = 'intereses_cesantias';
    const TYPE_VACACIONES         = 'vacaciones';

    protected $fillable = [
        'employee_id',
        'contract_id',
        'type',
        'year',
        'semester',
        'base_salary',
        'days_worked',
        'amount',
        'paid_amount',
        'pay_date',
        'is_paid',
    ];

    protected $casts = [
        'base_salary'  => 'decimal:2',
        'amount'       => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'pay_date'     => 'date',
        'is_paid'      => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_PRIMA               => 'Prima de Servicios',
            self::TYPE_CESANTIAS           => 'Cesantías',
            self::TYPE_INTERESES_CESANTIAS => 'Intereses sobre Cesantías',
            self::TYPE_VACACIONES          => 'Vacaciones',
            default                        => $type,
        };
    }
}
