<?php

use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayrollNovelty;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\PayrollCalculationService;

// ── Tests unitarios del motor de cálculo de nómina ───────────────────────────
//
// PayrollCalculationService::calculateEmployee() opera sobre modelos reales
// (PayrollRun + EmployeeContract), no sobre arrays sueltos — así se usa desde
// processRun()/previewRun(). Estos tests arman esos modelos directamente.

function payrollContract(array $overrides = []): EmployeeContract
{
    $employee = Employee::create([
        'identification_number' => (string) fake()->unique()->numerify('##########'),
        'first_name'            => 'Empleado',
        'last_name'             => 'Test',
    ]);

    return EmployeeContract::create(array_merge([
        'employee_id'             => $employee->id,
        'created_by'              => User::factory()->create()->id,
        'job_title'               => 'Auxiliar',
        'salary'                  => 1300000,
        'has_transport_allowance' => true,
        'arl_risk_class'          => 1,
        'start_date'              => '2025-01-01',
        'state'                   => true,
    ], $overrides));
}

function payrollRun(string $start, string $end): PayrollRun
{
    return new PayrollRun([
        'period_start' => $start,
        'period_end'   => $end,
    ]);
}

test('calcula el período completo de 30 días sin novedades', function () {
    $contract = payrollContract(['salary' => 1300000]);
    $run      = payrollRun('2026-01-01', '2026-01-30');

    $result = (new PayrollCalculationService())->calculateEmployee($run, $contract);

    expect($result->worked_days)->toBe(30);
    expect((float) $result->basic_salary)->toBe(1300000.0);
    // Auxilio de transporte: 1.300.000 ≤ 2 SMMLV (2.847.000) → aplica completo
    expect((float) $result->transport_allowance)->toBe(202033.0);
    // Salud/pensión del empleado: 4% cada una sobre el salario base
    expect((float) $result->health_employee)->toBe(round(1300000 * 0.04, 2));
    expect((float) $result->pension_employee)->toBe(round(1300000 * 0.04, 2));

    $expectedNet = round(
        1300000 + 202033
        - round(1300000 * 0.04, 2)
        - round(1300000 * 0.04, 2),
        2
    );
    expect((float) $result->net_pay)->toBe($expectedNet);
});

test('período parcial paga proporcional a los días trabajados', function () {
    // El contrato inicia a mitad del período → solo 15 de 30 días trabajados.
    $contract = payrollContract(['salary' => 3000000, 'start_date' => '2026-01-16', 'has_transport_allowance' => false]);
    $run      = payrollRun('2026-01-01', '2026-01-30');

    $result = (new PayrollCalculationService())->calculateEmployee($run, $contract);

    expect($result->worked_days)->toBe(15);
    expect((float) $result->basic_salary)->toBe(round(3000000 / 30 * 15, 2));
});

test('el auxilio de transporte no aplica sobre 2 SMMLV', function () {
    $contract = payrollContract(['salary' => 3000000, 'has_transport_allowance' => true]);
    $run      = payrollRun('2026-01-01', '2026-01-30');

    $result = (new PayrollCalculationService())->calculateEmployee($run, $contract);

    expect((float) $result->transport_allowance)->toBe(0.0);
});

test('novedad de bonificación incrementa el neto exactamente en su valor', function () {
    $contract = payrollContract(['salary' => 2000000, 'has_transport_allowance' => false]);
    $run      = payrollRun('2026-01-01', '2026-01-30');
    $service  = new PayrollCalculationService();

    $baseline = $service->calculateEmployee($run, $contract);

    PayrollNovelty::create([
        'employee_id' => $contract->employee_id,
        'contract_id' => $contract->id,
        'created_by'  => $contract->created_by,
        'type'        => PayrollNovelty::TYPE_BONUS,
        'amount'      => 500000,
        'date_from'   => '2026-01-15',
        'is_processed'=> false,
    ]);

    $withBonus = $service->calculateEmployee($run, $contract);

    expect((float) $withBonus->net_pay)->toBe(round((float) $baseline->net_pay + 500000, 2));
});

test('novedad de préstamo reduce el neto exactamente en su valor', function () {
    $contract = payrollContract(['salary' => 2000000, 'has_transport_allowance' => false]);
    $run      = payrollRun('2026-01-01', '2026-01-30');
    $service  = new PayrollCalculationService();

    $baseline = $service->calculateEmployee($run, $contract);

    PayrollNovelty::create([
        'employee_id' => $contract->employee_id,
        'contract_id' => $contract->id,
        'created_by'  => $contract->created_by,
        'type'        => PayrollNovelty::TYPE_LOAN,
        'amount'      => 200000,
        'date_from'   => '2026-01-15',
        'is_processed'=> false,
    ]);

    $withLoan = $service->calculateEmployee($run, $contract);

    expect((float) $withLoan->net_pay)->toBe(round((float) $baseline->net_pay - 200000, 2));
});

test('salario integral cotiza sobre el 70% de base', function () {
    $contract = payrollContract([
        'salary'                  => 15000000,
        'is_comprehensive_salary' => true,
        'has_transport_allowance' => false,
    ]);
    $run = payrollRun('2026-01-01', '2026-01-30');

    $result = (new PayrollCalculationService())->calculateEmployee($run, $contract);

    $expectedBase = round(15000000 * 0.70, 2);
    expect((float) $result->health_employee)->toBe(round($expectedBase * 0.04, 2));
});
