<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PayrollRunEmployee extends Model
{
    use HasUuids;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'contract_id',
        'worked_days',
        'salary',
        'is_comprehensive_salary',
        // devengados
        'basic_salary',
        'transport_allowance',
        'overtime_amount',
        'commissions',
        'bonuses',
        'vacation_amount',
        'prima_amount',
        'severance_amount',
        'severance_interests',
        'disability_amount',
        'other_income',
        'total_earned',
        // deducciones empleado
        'health_employee',
        'pension_employee',
        'solidarity_fund',
        'income_tax_withholding',
        'voluntary_health_deduction',
        'voluntary_pension_deduction',
        'loans_deduction',
        'other_deductions',
        'total_deductions',
        // neto
        'net_pay',
        // aportes empleador
        'health_employer',
        'pension_employer',
        'arl_employer',
        'ccf_employer',
        'sena_employer',
        'icbf_employer',
        'total_employer_cost',
        'novelties_detail',
    ];

    protected $casts = [
        'is_comprehensive_salary' => 'boolean',
        'novelties_detail'        => 'array',
        'salary'                  => 'decimal:2',
        'basic_salary'            => 'decimal:2',
        'transport_allowance'     => 'decimal:2',
        'overtime_amount'         => 'decimal:2',
        'commissions'             => 'decimal:2',
        'bonuses'                 => 'decimal:2',
        'vacation_amount'         => 'decimal:2',
        'prima_amount'            => 'decimal:2',
        'severance_amount'        => 'decimal:2',
        'severance_interests'     => 'decimal:2',
        'disability_amount'       => 'decimal:2',
        'other_income'            => 'decimal:2',
        'total_earned'            => 'decimal:2',
        'health_employee'         => 'decimal:2',
        'pension_employee'        => 'decimal:2',
        'solidarity_fund'         => 'decimal:2',
        'income_tax_withholding'  => 'decimal:2',
        'total_deductions'        => 'decimal:2',
        'net_pay'                 => 'decimal:2',
        'health_employer'         => 'decimal:2',
        'pension_employer'        => 'decimal:2',
        'arl_employer'            => 'decimal:2',
        'ccf_employer'            => 'decimal:2',
        'sena_employer'           => 'decimal:2',
        'icbf_employer'           => 'decimal:2',
        'total_employer_cost'     => 'decimal:2',
    ];

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract()
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id');
    }
}
