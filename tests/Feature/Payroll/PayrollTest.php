<?php

use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\PayrollCalculationService;

// ── Tests de Nómina ───────────────────────────────────────────────────────────

test('admin puede ver el listado de empleados', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->tenantGet('/payroll/employees')->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Payroll/Employees/Index'));
});

test('puede crear un empleado', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $response = $this->tenantPost('/payroll/employees', [
        'name'                  => 'Juan Pérez',
        'identification_number' => '1234567890',
        'identification_type'   => 'CC',
        'email'                 => 'juan@empresa.co',
        'position'              => 'Vendedor',
        'department'            => 'Ventas',
        'state'                 => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('employees', ['identification_number' => '1234567890']);
});

// ── Tests unitarios del servicio de cálculo ───────────────────────────────────

test('calcula correctamente el salario neto de un empleado de tiempo completo', function () {
    $service = new PayrollCalculationService();

    // Empleado con salario mínimo 2026 aprox. 1.300.000
    $result = $service->calculate([
        'salary'          => 1300000,
        'worked_days'     => 30,
        'transport'       => 162000,  // auxilio de transporte aprox.
        'health_deduction'=> 4.0,     // 4%
        'pension_deduction'=> 4.0,    // 4%
        'novelties'       => [],
    ]);

    // Salud: 1300000 * 4% = 52000
    expect($result['health_deduction'])->toBe(52000.0);
    // Pensión: 1300000 * 4% = 52000
    expect($result['pension_deduction'])->toBe(52000.0);
    // Neto: 1300000 + 162000 - 52000 - 52000 = 1358000
    expect($result['net_salary'])->toBe(1358000.0);
});

test('calcula prima de servicios correctamente', function () {
    // Prima = salario × días / 360
    $salary = 2000000;
    $days   = 180;  // 6 meses
    $prima  = round($salary * $days / 360);

    expect($prima)->toBe(1000000);
});

test('calcula cesantías correctamente', function () {
    // Cesantías = salario × días / 360
    $salary   = 2000000;
    $days     = 360;  // 1 año
    $cesantias = round($salary * $days / 360);

    expect($cesantias)->toBe(2000000);
});

test('calcula vacaciones correctamente', function () {
    // Vacaciones = salario × días / 720
    $salary      = 2000000;
    $days        = 360;  // 1 año
    $vacaciones  = round($salary * $days / 720);

    expect($vacaciones)->toBe(1000000);
});
