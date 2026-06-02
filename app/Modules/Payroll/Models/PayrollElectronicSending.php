<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracking de envíos de Nómina Electrónica DIAN (NES) por empleado.
 * Un registro por empleado por liquidación.
 */
class PayrollElectronicSending extends Model
{
    use HasUuids;

    protected $table = 'payroll_electronic_sendings';

    protected $fillable = [
        'payroll_run_id',
        'payroll_run_employee_id',
        'cune',
        'status',
        'attempts',
        'error_message',
        'response_api',
        'sent_at',
    ];

    protected $casts = [
        'error_message' => 'array',
        'response_api'  => 'array',
        'sent_at'       => 'datetime',
        'attempts'      => 'integer',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_FAILED   = 'failed';

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function runEmployee(): BelongsTo
    {
        return $this->belongsTo(PayrollRunEmployee::class, 'payroll_run_employee_id');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
