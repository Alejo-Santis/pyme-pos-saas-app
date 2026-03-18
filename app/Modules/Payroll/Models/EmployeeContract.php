<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeContract extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'created_by',
        'finished_by',
        'type_contract_id',
        'type_worker_id',
        'payroll_period_id',
        'contract_number',
        'job_title',
        'cost_center',
        'arl_risk_class',
        'salary',
        'is_comprehensive_salary',
        'has_transport_allowance',
        'voluntary_health_amount',
        'voluntary_pension_amount',
        'eps_name',
        'afp_name',
        'arl_name',
        'ccf_name',
        'has_income_tax_withholding',
        'income_tax_withholding_pct',
        'start_date',
        'end_date',
        'trial_end_date',
        'state',
    ];

    protected $casts = [
        'salary'                      => 'decimal:2',
        'voluntary_health_amount'     => 'decimal:2',
        'voluntary_pension_amount'    => 'decimal:2',
        'income_tax_withholding_pct'  => 'decimal:2',
        'is_comprehensive_salary'     => 'boolean',
        'has_transport_allowance'     => 'boolean',
        'has_income_tax_withholding'  => 'boolean',
        'state'                       => 'boolean',
        'start_date'                  => 'date',
        'end_date'                    => 'date',
        'trial_end_date'              => 'date',
    ];

    // Factores de ARL por clase de riesgo (Decreto 1772 de 1994 Colombia)
    public static array $arlRates = [
        1 => 0.00522,
        2 => 0.01044,
        3 => 0.02436,
        4 => 0.04350,
        5 => 0.06960,
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function getArlRateAttribute(): float
    {
        return self::$arlRates[$this->arl_risk_class] ?? 0.00522;
    }

    public function scopeActive($query)
    {
        return $query->where('state', true);
    }
}
