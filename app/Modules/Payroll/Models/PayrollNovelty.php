<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollNovelty extends Model
{
    use HasUuids;

    // Tipos de novedad
    const TYPE_OVERTIME      = 'overtime';       // horas extra / recargos
    const TYPE_DISABILITY    = 'disability';     // incapacidad
    const TYPE_UNPAID_LEAVE  = 'unpaid_leave';   // permiso no remunerado
    const TYPE_COMMISSION    = 'commission';     // comisión
    const TYPE_BONUS         = 'bonus';          // bonificación
    const TYPE_LOAN          = 'loan';           // libranza / préstamo
    const TYPE_VACATION      = 'vacation';       // vacaciones
    const TYPE_OTHER         = 'other';          // otro

    protected $fillable = [
        'employee_id',
        'contract_id',
        'payroll_run_employee_id',
        'created_by',
        'type',
        'overtime_type_id',
        'overtime_hours',
        'disability_type_id',
        'disability_days',
        'vacation_days',
        'unpaid_leave_days',
        'amount',
        'date_from',
        'date_to',
        'description',
        'is_processed',
    ];

    protected $casts = [
        'date_from'      => 'date',
        'date_to'        => 'date',
        'amount'         => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'is_processed'   => 'boolean',
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
            self::TYPE_OVERTIME     => 'Horas Extra / Recargo',
            self::TYPE_DISABILITY   => 'Incapacidad',
            self::TYPE_UNPAID_LEAVE => 'Permiso No Remunerado',
            self::TYPE_COMMISSION   => 'Comisión',
            self::TYPE_BONUS        => 'Bonificación',
            self::TYPE_LOAN         => 'Libranza / Préstamo',
            self::TYPE_VACATION     => 'Vacaciones',
            self::TYPE_OTHER        => 'Otro',
            default                 => $type,
        };
    }

    public function scopePending($query)
    {
        return $query->where('is_processed', false);
    }
}
