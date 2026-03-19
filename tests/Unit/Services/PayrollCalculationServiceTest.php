<?php

use App\Modules\Payroll\Services\PayrollCalculationService;

// ── Tests unitarios del motor de cálculo de nómina ───────────────────────────

test('calcula el período completo de 30 días sin novedades', function () {
    $service = new PayrollCalculationService();

    $result = $service->calculate([
        'salary'           => 1300000,
        'worked_days'      => 30,
        'transport'        => 162000,
        'health_deduction' => 4.0,
        'pension_deduction'=> 4.0,
        'novelties'        => [],
    ]);

    expect($result)->toHaveKeys([
        'gross_salary', 'health_deduction', 'pension_deduction', 'net_salary',
        'transport_allowance', 'total_deductions', 'total_earnings',
    ]);

    expect($result['health_deduction'])->toBe(52000.0);     // 1.300.000 × 4%
    expect($result['pension_deduction'])->toBe(52000.0);    // 1.300.000 × 4%
    expect($result['transport_allowance'])->toBe(162000.0);
    expect($result['net_salary'])->toBe(1358000.0);
});

test('período parcial proporciona el salario', function () {
    $service = new PayrollCalculationService();

    $result = $service->calculate([
        'salary'           => 3000000,
        'worked_days'      => 15,  // medio mes
        'transport'        => 0,
        'health_deduction' => 4.0,
        'pension_deduction'=> 4.0,
        'novelties'        => [],
    ]);

    $expectedGross = round(3000000 / 30 * 15);  // 1.500.000
    expect($result['gross_salary'])->toBe((float) $expectedGross);
});

test('novedades de bonificación incrementan el neto', function () {
    $service = new PayrollCalculationService();

    $result = $service->calculate([
        'salary'           => 2000000,
        'worked_days'      => 30,
        'transport'        => 0,
        'health_deduction' => 4.0,
        'pension_deduction'=> 4.0,
        'novelties'        => [
            ['type' => 'bonus', 'value' => 500000, 'taxable' => false],
        ],
    ]);

    // La bonificación suma al neto
    expect($result['net_salary'])->toBeGreaterThan(2000000 - (2000000 * 0.08));
});

test('novedades de descuento reducen el neto', function () {
    $service = new PayrollCalculationService();

    $result = $service->calculate([
        'salary'           => 2000000,
        'worked_days'      => 30,
        'transport'        => 0,
        'health_deduction' => 4.0,
        'pension_deduction'=> 4.0,
        'novelties'        => [
            ['type' => 'deduction', 'value' => 200000, 'taxable' => false],
        ],
    ]);

    // Las deducciones reducen el neto vs sin novedades
    $netSinNovedad = 2000000 - (2000000 * 0.08);
    expect($result['net_salary'])->toBeLessThan($netSinNovedad);
});
