<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasUuids;

    // Estados de una liquidación
    const STATUS_DRAFT     = 'draft';
    const STATUS_APPROVED  = 'approved';
    const STATUS_PAID      = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'created_by',
        'approved_by',
        'name',
        'period_start',
        'period_end',
        'payroll_period_id',
        'total_earned',
        'total_deductions',
        'total_net',
        'total_employer_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'period_start'         => 'date',
        'period_end'           => 'date',
        'total_earned'         => 'decimal:2',
        'total_deductions'     => 'decimal:2',
        'total_net'            => 'decimal:2',
        'total_employer_cost'  => 'decimal:2',
    ];

    public function details()
    {
        return $this->hasMany(PayrollRunEmployee::class, 'payroll_run_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT     => 'Borrador',
            self::STATUS_APPROVED  => 'Aprobada',
            self::STATUS_PAID      => 'Pagada',
            self::STATUS_CANCELLED => 'Anulada',
            default                => $status,
        };
    }
}
