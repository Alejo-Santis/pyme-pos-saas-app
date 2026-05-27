<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayrollNovelty;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Models\PayrollSocialBenefit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PayrollCalculationService
{
    // ──────────────────────────────────────────────────────────────
    // Valores fallback. En operación normal se leen de payroll_legal_parameters.
    // ──────────────────────────────────────────────────────────────

    const SMMLV = 1750905;

    const TRANSPORT_ALLOWANCE = 249095;

    // ── Aportes empleado ──
    const HEALTH_EMPLOYEE_RATE  = 0.04;   // 4%
    const PENSION_EMPLOYEE_RATE = 0.04;   // 4%

    // Fondo de solidaridad pensional (salarios > 4 SMMLV)
    const SOLIDARITY_BASE_RATE  = 0.01;   // 1% base
    const SOLIDARITY_EXTRA_RATE = 0.005;  // 0.5% adicional por cada 2 SMMLV por encima de 16

    // ── Aportes empleador ──
    const HEALTH_EMPLOYER_RATE  = 0.085;  // 8.5%
    const PENSION_EMPLOYER_RATE = 0.12;   // 12%
    const CCF_RATE              = 0.04;   // 4% Caja Compensación
    const SENA_RATE             = 0.02;   // 2% SENA
    const ICBF_RATE             = 0.03;   // 3% ICBF

    // ── Prestaciones sociales ──
    const PRIMA_DAYS_PER_SEMESTER     = 15;   // 15 días por semestre
    const VACATION_DAYS_PER_YEAR      = 15;   // 15 días hábiles por año
    const CESANTIAS_INTEREST_RATE     = 0.12; // 12% anual sobre cesantías

    // UVT 2025 para retención en la fuente
    const UVT = 52374;

    public static function legalParameters(?int $year = null): array
    {
        $year ??= (int) now()->year;

        $fallback = [
            'year'                       => $year,
            'smmlv'                      => (float) self::SMMLV,
            'transport_allowance'        => (float) self::TRANSPORT_ALLOWANCE,
            'uvt'                        => (float) self::UVT,
            'health_employee_rate'       => (float) self::HEALTH_EMPLOYEE_RATE,
            'pension_employee_rate'      => (float) self::PENSION_EMPLOYEE_RATE,
            'solidarity_base_rate'       => (float) self::SOLIDARITY_BASE_RATE,
            'health_employer_rate'       => (float) self::HEALTH_EMPLOYER_RATE,
            'pension_employer_rate'      => (float) self::PENSION_EMPLOYER_RATE,
            'ccf_rate'                   => (float) self::CCF_RATE,
            'sena_rate'                  => (float) self::SENA_RATE,
            'icbf_rate'                  => (float) self::ICBF_RATE,
            'cesantias_interest_rate'    => (float) self::CESANTIAS_INTEREST_RATE,
        ];

        try {
            if (! Schema::hasTable('payroll_legal_parameters')) {
                return $fallback;
            }

            $row = DB::table('payroll_legal_parameters')
                ->where('year', $year)
                ->where('is_active', true)
                ->first();

            if (! $row) {
                return $fallback;
            }

            return array_merge($fallback, [
                'smmlv'                   => (float) $row->smmlv,
                'transport_allowance'     => (float) $row->transport_allowance,
                'uvt'                     => (float) $row->uvt,
                'health_employee_rate'    => (float) $row->health_employee_rate,
                'pension_employee_rate'   => (float) $row->pension_employee_rate,
                'solidarity_base_rate'    => (float) $row->solidarity_base_rate,
                'health_employer_rate'    => (float) $row->health_employer_rate,
                'pension_employer_rate'   => (float) $row->pension_employer_rate,
                'ccf_rate'                => (float) $row->ccf_rate,
                'sena_rate'               => (float) $row->sena_rate,
                'icbf_rate'               => (float) $row->icbf_rate,
                'cesantias_interest_rate' => (float) $row->cesantias_interest_rate,
            ]);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function smmlv(?int $year = null): float
    {
        return self::legalParameters($year)['smmlv'];
    }

    public static function transportAllowance(?int $year = null): float
    {
        return self::legalParameters($year)['transport_allowance'];
    }

    public function calculate(array $data): array
    {
        $salary = (float) ($data['salary'] ?? 0);
        $workedDays = max(0, min(30, (int) ($data['worked_days'] ?? 30)));
        $grossSalary = round($salary * $workedDays / 30, 2);

        $transport = round((float) ($data['transport'] ?? 0) * $workedDays / 30, 2);
        $healthRate = ((float) ($data['health_deduction'] ?? 4)) / 100;
        $pensionRate = ((float) ($data['pension_deduction'] ?? 4)) / 100;

        $extraEarnings = 0;
        $extraDeductions = 0;

        foreach (($data['novelties'] ?? []) as $novelty) {
            $value = (float) ($novelty['value'] ?? 0);
            match ($novelty['type'] ?? '') {
                'bonus', 'commission', 'earning' => $extraEarnings += $value,
                'deduction', 'loan'              => $extraDeductions += $value,
                default                          => null,
            };
        }

        $healthDeduction = round($grossSalary * $healthRate, 2);
        $pensionDeduction = round($grossSalary * $pensionRate, 2);
        $totalDeductions = $healthDeduction + $pensionDeduction + $extraDeductions;
        $totalEarnings = $grossSalary + $transport + $extraEarnings;

        return [
            'gross_salary'        => $grossSalary,
            'transport_allowance' => $transport,
            'health_deduction'    => $healthDeduction,
            'pension_deduction'   => $pensionDeduction,
            'total_deductions'    => $totalDeductions,
            'total_earnings'      => $totalEarnings,
            'net_salary'          => round($totalEarnings - $totalDeductions, 2),
        ];
    }

    /**
     * Calcula y persiste la liquidación completa de nómina para todos
     * los empleados activos en el período dado.
     */
    public function processRun(PayrollRun $run): PayrollRun
    {
        $contracts = EmployeeContract::with('employee')
            ->where('state', true)
            ->whereDate('start_date', '<=', $run->period_end)
            ->where(function ($q) use ($run) {
                $q->whereNull('end_date')
                  ->orWhereDate('end_date', '>=', $run->period_start);
            })
            ->get();

        DB::transaction(function () use ($run, $contracts) {
            $runTotals = [
                'total_earned'        => 0,
                'total_deductions'    => 0,
                'total_net'           => 0,
                'total_employer_cost' => 0,
            ];

            foreach ($contracts as $contract) {
                $detail = $this->calculateEmployee($run, $contract);
                $detail->save();

                $runTotals['total_earned']        += $detail->total_earned;
                $runTotals['total_deductions']    += $detail->total_deductions;
                $runTotals['total_net']           += $detail->net_pay;
                $runTotals['total_employer_cost'] += $detail->total_employer_cost;

                // Marcar novedades del período como procesadas
                PayrollNovelty::where('employee_id', $contract->employee_id)
                    ->where('is_processed', false)
                    ->whereDate('date_from', '>=', $run->period_start)
                    ->whereDate('date_from', '<=', $run->period_end)
                    ->update([
                        'is_processed'             => true,
                        'payroll_run_employee_id'  => $detail->id,
                    ]);
            }

            $run->update($runTotals + ['status' => PayrollRun::STATUS_APPROVED]);
        });

        return $run->fresh();
    }

    /**
     * Calcula la liquidación de UN empleado para el período del run.
     * Retorna un PayrollRunEmployee sin guardar (permite previsualización).
     */
    public function calculateEmployee(PayrollRun $run, EmployeeContract $contract): PayrollRunEmployee
    {
        $periodStart = Carbon::parse($run->period_start);
        $periodEnd   = Carbon::parse($run->period_end);
        $params      = self::legalParameters((int) $periodEnd->year);

        // Días trabajados en el período (Colombia usa mes de 30 días)
        $workedDays = $this->workedDays($contract, $periodStart, $periodEnd);

        $salary     = (float) $contract->salary;
        $isIntegral = $contract->is_comprehensive_salary;

        // ── DEVENGADOS ──────────────────────────────────────────
        $basicSalary = round($salary * $workedDays / 30, 2);

        // Auxilio de transporte (solo si salario ≤ 2 SMMLV y no es integral)
        $transportAllowance = 0;
        if (!$isIntegral && $contract->has_transport_allowance && $salary <= ($params['smmlv'] * 2)) {
            $transportAllowance = round($params['transport_allowance'] * $workedDays / 30, 2);
        }

        // Novedades del período (horas extra, comisiones, bonificaciones, incapacidades)
        $novelties = $this->getNovelties($contract, $periodStart, $periodEnd, $salary);

        $overtimeAmount  = $novelties['overtime_amount'];
        $commissions     = $novelties['commissions'];
        $bonuses         = $novelties['bonuses'];
        $disabilityAmt   = $novelties['disability_amount'];
        $vacationAmt     = $novelties['vacation_amount'];
        $unpaidDays      = $novelties['unpaid_days'];

        // Ajustar salario por días no remunerados
        if ($unpaidDays > 0) {
            $basicSalary = round($salary * ($workedDays - $unpaidDays) / 30, 2);
        }

        $totalEarned = $basicSalary + $transportAllowance + $overtimeAmount
                     + $commissions + $bonuses + $disabilityAmt + $vacationAmt;

        // ── BASE PARA APORTES ────────────────────────────────────
        // Para salario integral: base = 70% del salario (solo sobre factor prestacional)
        $contributionBase = $isIntegral
            ? round($salary * 0.70, 2)
            : $salary;

        // Proporcional al período si no trabajó el mes completo
        $proportionalBase = round($contributionBase * $workedDays / 30, 2);

        // ── DEDUCCIONES EMPLEADO ─────────────────────────────────
        $healthEmployee  = round($proportionalBase * $params['health_employee_rate'], 2);
        $pensionEmployee = round($proportionalBase * $params['pension_employee_rate'], 2);
        $solidarityFund  = $this->calculateSolidarityFund($salary, $params);

        // Aportes voluntarios (AFC + pensión voluntaria)
        $voluntaryHealth  = (float) $contract->voluntary_health_amount;
        $voluntaryPension = (float) $contract->voluntary_pension_amount;

        // Retención en la fuente
        $incomeTax = $contract->has_income_tax_withholding
            ? $this->calculateIncomeTax($totalEarned, $healthEmployee, $pensionEmployee, $solidarityFund, $params)
            : 0;

        $totalDeductions = $healthEmployee + $pensionEmployee + $solidarityFund
                         + $incomeTax + $voluntaryHealth + $voluntaryPension;

        $netPay = round($totalEarned - $totalDeductions, 2);

        // ── APORTES EMPLEADOR (costo, no descuento al empleado) ──
        $healthEmployer  = round($proportionalBase * $params['health_employer_rate'], 2);
        $pensionEmployer = round($proportionalBase * $params['pension_employer_rate'], 2);
        $arlEmployer     = round($proportionalBase * $contract->arl_rate, 2);
        $ccfEmployer     = round($proportionalBase * $params['ccf_rate'], 2);

        // SENA e ICBF: exonerados si salario ≤ 10 SMMLV (Ley 1607/2012)
        $senaEmployer = $salary <= ($params['smmlv'] * 10)
            ? 0
            : round($proportionalBase * $params['sena_rate'], 2);
        $icbfEmployer = $salary <= ($params['smmlv'] * 10)
            ? 0
            : round($proportionalBase * $params['icbf_rate'], 2);

        $totalEmployerCost = $salary + $transportAllowance
                           + $healthEmployer + $pensionEmployer + $arlEmployer
                           + $ccfEmployer + $senaEmployer + $icbfEmployer;

        return new PayrollRunEmployee([
            'payroll_run_id'           => $run->id,
            'employee_id'              => $contract->employee_id,
            'contract_id'              => $contract->id,
            'worked_days'              => $workedDays,
            'salary'                   => $salary,
            'is_comprehensive_salary'  => $isIntegral,
            // devengados
            'basic_salary'             => $basicSalary,
            'transport_allowance'      => $transportAllowance,
            'overtime_amount'          => $overtimeAmount,
            'commissions'              => $commissions,
            'bonuses'                  => $bonuses,
            'vacation_amount'          => $vacationAmt,
            'disability_amount'        => $disabilityAmt,
            'total_earned'             => $totalEarned,
            // deducciones
            'health_employee'          => $healthEmployee,
            'pension_employee'         => $pensionEmployee,
            'solidarity_fund'          => $solidarityFund,
            'income_tax_withholding'   => $incomeTax,
            'voluntary_health_deduction'  => $voluntaryHealth,
            'voluntary_pension_deduction' => $voluntaryPension,
            'total_deductions'         => $totalDeductions,
            // neto
            'net_pay'                  => $netPay,
            // empleador
            'health_employer'          => $healthEmployer,
            'pension_employer'         => $pensionEmployer,
            'arl_employer'             => $arlEmployer,
            'ccf_employer'             => $ccfEmployer,
            'sena_employer'            => $senaEmployer,
            'icbf_employer'            => $icbfEmployer,
            'total_employer_cost'      => $totalEmployerCost,
            'novelties_detail'         => $novelties['detail'],
        ]);
    }

    /**
     * Calcula la vista previa (preview) de la liquidación SIN persistir.
     * Útil para mostrar al usuario antes de aprobar.
     */
    public function previewRun(string $periodStart, string $periodEnd): array
    {
        $run = new PayrollRun([
            'period_start' => $periodStart,
            'period_end'   => $periodEnd,
        ]);

        $contracts = EmployeeContract::with('employee')
            ->where('state', true)
            ->whereDate('start_date', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $periodStart);
            })
            ->get();

        $employees = [];
        $totals = ['total_earned' => 0, 'total_deductions' => 0, 'total_net' => 0, 'total_employer_cost' => 0];

        foreach ($contracts as $contract) {
            $detail = $this->calculateEmployee($run, $contract);
            $row = $detail->toArray();
            $row['employee_name'] = $contract->employee->full_name ?? '';
            $employees[] = $row;

            $totals['total_earned']        += $detail->total_earned;
            $totals['total_deductions']    += $detail->total_deductions;
            $totals['total_net']           += $detail->net_pay;
            $totals['total_employer_cost'] += $detail->total_employer_cost;
        }

        return compact('employees', 'totals');
    }

    // ──────────────────────────────────────────────────────────────
    // PRESTACIONES SOCIALES
    // ──────────────────────────────────────────────────────────────

    /**
     * Calcula y persiste las prestaciones sociales del semestre indicado.
     * prima: se paga en junio y diciembre
     * cesantías: se paga en febrero (año siguiente) o en retiro
     * intereses: se pagan en enero (12% anual sobre las cesantías del año)
     * vacaciones: se pagan al disfrutarlas o en la liquidación
     */
    public function calculateSocialBenefits(EmployeeContract $contract, int $year, int $semester): array
    {
        $salary      = (float) $contract->salary;
        $daysWorked  = $this->daysWorkedInSemester($contract, $year, $semester);
        $params      = self::legalParameters($year);
        $benefits    = [];

        // Prima de servicios: salario/30 * días trabajados en el semestre
        $primaAmount = round($salary / 30 * $daysWorked, 2);
        $benefits[] = PayrollSocialBenefit::updateOrCreate(
            ['employee_id' => $contract->employee_id, 'contract_id' => $contract->id, 'type' => 'prima', 'year' => $year, 'semester' => $semester],
            ['base_salary' => $salary, 'days_worked' => $daysWorked, 'amount' => $primaAmount]
        );

        // Cesantías: salario * días / 360
        $cesantiasAmount = round($salary * $daysWorked / 360, 2);
        $benefits[] = PayrollSocialBenefit::updateOrCreate(
            ['employee_id' => $contract->employee_id, 'contract_id' => $contract->id, 'type' => 'cesantias', 'year' => $year, 'semester' => $semester],
            ['base_salary' => $salary, 'days_worked' => $daysWorked, 'amount' => $cesantiasAmount]
        );

        // Intereses sobre cesantías: 12% anual (proporcional al tiempo)
        $interesesAmount = round($cesantiasAmount * $params['cesantias_interest_rate'] * $daysWorked / 360, 2);
        $benefits[] = PayrollSocialBenefit::updateOrCreate(
            ['employee_id' => $contract->employee_id, 'contract_id' => $contract->id, 'type' => 'intereses_cesantias', 'year' => $year, 'semester' => $semester],
            ['base_salary' => $salary, 'days_worked' => $daysWorked, 'amount' => $interesesAmount]
        );

        return $benefits;
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Días trabajados en el período (máx 30, Colombia).
     * Se descuentan los días de incapacidad no remunerada y permisos no pagos.
     */
    private function workedDays(EmployeeContract $contract, Carbon $periodStart, Carbon $periodEnd): int
    {
        // Ajustar inicio/fin al contrato
        $start = $contract->start_date->gt($periodStart) ? $contract->start_date : $periodStart;
        $end   = $contract->end_date && $contract->end_date->lt($periodEnd) ? $contract->end_date : $periodEnd;

        if ($start->gt($end)) {
            return 0;
        }

        // Colombia: mes normalizado a 30 días (el día 31 = día 30)
        $startDay = min($start->day, 30);
        $endDay   = min($end->day, 30);
        $days     = (($end->year - $start->year) * 12 + ($end->month - $start->month)) * 30
                  + ($endDay - $startDay + 1);

        return max(0, min(30, $days));
    }

    /**
     * Obtiene y calcula novedades pendientes del período.
     */
    private function getNovelties(EmployeeContract $contract, Carbon $start, Carbon $end, float $salary): array
    {
        $novelties = PayrollNovelty::where('employee_id', $contract->employee_id)
            ->where('is_processed', false)
            ->whereDate('date_from', '>=', $start)
            ->whereDate('date_from', '<=', $end)
            ->with('contract')
            ->get();

        $overtime   = 0;
        $commissions = 0;
        $bonuses    = 0;
        $disability = 0;
        $vacation   = 0;
        $unpaidDays = 0;
        $detail     = [];

        $hourlyRate = round($salary / 30 / 8, 2); // valor hora ordinaria

        foreach ($novelties as $novelty) {
            switch ($novelty->type) {
                case PayrollNovelty::TYPE_OVERTIME:
                    // Calcular horas extra según factor del tipo
                    $factor  = DB::table('payroll_type_overtime_surcharges')->where('id', $novelty->overtime_type_id)->value('factor') ?? 1.25;
                    $value   = round((float) $novelty->overtime_hours * $hourlyRate * $factor, 2);
                    $overtime += $value;
                    $detail[] = ['type' => 'overtime', 'hours' => $novelty->overtime_hours, 'factor' => $factor, 'amount' => $value, 'description' => $novelty->description];
                    break;

                case PayrollNovelty::TYPE_COMMISSION:
                    $commissions += (float) $novelty->amount;
                    $detail[] = ['type' => 'commission', 'amount' => $novelty->amount, 'description' => $novelty->description];
                    break;

                case PayrollNovelty::TYPE_BONUS:
                    $bonuses += (float) $novelty->amount;
                    $detail[] = ['type' => 'bonus', 'amount' => $novelty->amount, 'description' => $novelty->description];
                    break;

                case PayrollNovelty::TYPE_DISABILITY:
                    // Empresa paga días 1-2, EPS desde día 3 al 90 (66.67% del salario)
                    // Para enfermedad general (tipo 1)
                    $days     = (int) $novelty->disability_days;
                    $dailyRate = round($salary / 30, 2);
                    $companyDays = min($days, 2); // empresa paga máx 2 días
                    $value    = round($dailyRate * $companyDays * 0.6667, 2);
                    $disability += $value;
                    $detail[] = ['type' => 'disability', 'days' => $days, 'company_days' => $companyDays, 'amount' => $value, 'description' => $novelty->description];
                    break;

                case PayrollNovelty::TYPE_VACATION:
                    // 15 días hábiles anuales = salario / 2 por mes completo
                    $days  = (int) $novelty->vacation_days;
                    $value = round($salary / 30 * $days, 2);
                    $vacation += $value;
                    $detail[] = ['type' => 'vacation', 'days' => $days, 'amount' => $value];
                    break;

                case PayrollNovelty::TYPE_UNPAID_LEAVE:
                    $unpaidDays += (int) $novelty->unpaid_leave_days;
                    $detail[] = ['type' => 'unpaid_leave', 'days' => $novelty->unpaid_leave_days, 'amount' => 0];
                    break;

                case PayrollNovelty::TYPE_OTHER:
                    if ($novelty->amount > 0) {
                        $bonuses += (float) $novelty->amount;
                        $detail[] = ['type' => 'other', 'amount' => $novelty->amount, 'description' => $novelty->description];
                    }
                    break;
            }
        }

        return compact('overtime', 'commissions', 'bonuses', 'disability', 'vacation', 'unpaidDays', 'detail') + [
            'overtime_amount'  => $overtime,
            'disability_amount' => $disability,
            'vacation_amount'  => $vacation,
        ];
    }

    /**
     * Fondo de solidaridad pensional.
     * Aplica para salarios > 4 SMMLV.
     * 1% base + 0.2% adicional por cada SMMLV por encima de 16 SMMLV.
     */
    private function calculateSolidarityFund(float $salary, array $params): float
    {
        if ($salary <= ($params['smmlv'] * 4)) {
            return 0;
        }

        $rate = $params['solidarity_base_rate'];

        // Adicional por salarios > 16 SMMLV
        if ($salary > $params['smmlv'] * 16) {
            $smmlvMultiple = floor($salary / $params['smmlv']);
            $extraBlocks   = floor(($smmlvMultiple - 16) / 2);
            $rate += $extraBlocks * 0.002;
            $rate  = min($rate, 0.02); // máx 2%
        }

        return round($salary * $rate, 2);
    }

    /**
     * Retención en la fuente por ingresos laborales.
     * Método de la tabla del Art. 383 E.T. (UVT 2025 = $49,799).
     * Base gravable = ingresos laborales - aportes obligatorios - deducciones.
     */
    private function calculateIncomeTax(
        float $totalEarned,
        float $healthEmployee,
        float $pensionEmployee,
        float $solidarityFund,
        array $params
    ): float {
        // Base gravable mensual (sin auxilio de transporte para retención)
        $deductions  = $healthEmployee + $pensionEmployee + $solidarityFund;
        $baseGravable = max(0, $totalEarned - $deductions);

        // Anualizar para aplicar tabla del Art. 383
        $annualBase = $baseGravable * 12;
        $uvt        = $params['uvt'] ?: self::UVT;

        // Tabla simplificada Art. 383 E.T. (rangos en UVT anuales)
        $annualUvt = $annualBase / $uvt;

        $annualTax = match(true) {
            $annualUvt <= 95   => 0,
            $annualUvt <= 150  => ($annualUvt - 95) * $uvt * 0.19,
            $annualUvt <= 360  => ($annualUvt - 150) * $uvt * 0.28 + 55 * $uvt * 0.19,
            $annualUvt <= 640  => ($annualUvt - 360) * $uvt * 0.33 + 210 * $uvt * 0.28 + 55 * $uvt * 0.19,
            $annualUvt <= 945  => ($annualUvt - 640) * $uvt * 0.35 + 280 * $uvt * 0.33 + 210 * $uvt * 0.28 + 55 * $uvt * 0.19,
            $annualUvt <= 2300 => ($annualUvt - 945) * $uvt * 0.37 + 305 * $uvt * 0.35 + 280 * $uvt * 0.33 + 210 * $uvt * 0.28 + 55 * $uvt * 0.19,
            default            => ($annualUvt - 2300) * $uvt * 0.39 + 1355 * $uvt * 0.37 + 305 * $uvt * 0.35 + 280 * $uvt * 0.33 + 210 * $uvt * 0.28 + 55 * $uvt * 0.19,
        };

        return round($annualTax / 12, 2);
    }

    /**
     * Días trabajados en un semestre para prestaciones sociales.
     */
    private function daysWorkedInSemester(EmployeeContract $contract, int $year, int $semester): int
    {
        $semesterStart = Carbon::create($year, $semester === 1 ? 1 : 7, 1);
        $semesterEnd   = Carbon::create($year, $semester === 1 ? 6 : 12, 30);

        $start = $contract->start_date->gt($semesterStart) ? $contract->start_date : $semesterStart;
        $end   = $contract->end_date && $contract->end_date->lt($semesterEnd) ? $contract->end_date : $semesterEnd;

        if ($start->gt($end)) {
            return 0;
        }

        $days = $start->diffInDays($end) + 1;
        return min(180, max(0, $days)); // semestre = 180 días
    }
}
