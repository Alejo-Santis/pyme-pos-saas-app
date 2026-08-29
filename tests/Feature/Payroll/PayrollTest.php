<?php

use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Services\PayrollCalculationService;

// ── Tests de Nómina ───────────────────────────────────────────────────────────

test('admin puede ver el listado de empleados', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->tenantGet('/payroll/employees')->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Payroll/Employees/Index'));
});

test('puede crear un empleado con su contrato', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $response = $this->tenantPost('/payroll/employees', [
        'document_type'          => 'CC',
        'identification_number'  => '1234567890',
        'first_name'             => 'Juan',
        'last_name'              => 'Pérez',
        'email'                  => 'juan@empresa.co',
        'gender'                 => 1,
        // datos del contrato inicial (EmployeeController::store los crea junto al empleado)
        'type_contract_id'       => 2,
        'type_worker_id'         => 1,
        'payroll_period_id'      => 1,
        'job_title'              => 'Vendedor',
        'arl_risk_class'         => 1,
        'salary'                 => PayrollCalculationService::SMMLV,
        'start_date'             => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('employees', ['identification_number' => '1234567890']);
    $this->assertDatabaseHas('employee_contracts', ['job_title' => 'Vendedor']);
});

test('no puede crear un empleado con salario menor al SMMLV', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $response = $this->tenantPost('/payroll/employees', [
        'document_type'          => 'CC',
        'identification_number'  => '1234567891',
        'first_name'             => 'Juan',
        'last_name'              => 'Pérez',
        'gender'                 => 1,
        'type_contract_id'       => 2,
        'type_worker_id'         => 1,
        'payroll_period_id'      => 1,
        'job_title'              => 'Vendedor',
        'arl_risk_class'         => 1,
        'salary'                 => PayrollCalculationService::SMMLV - 1000,
        'start_date'             => '2026-01-01',
    ]);

    $response->assertSessionHasErrors('salary');
});

// ── Prestaciones sociales ─────────────────────────────────────────────────────

test('calcula prima de servicios sobre un semestre completo', function () {
    $employee = Employee::create([
        'identification_number' => '900111222',
        'first_name'            => 'Ana',
        'last_name'             => 'Gómez',
    ]);

    $contract = EmployeeContract::create([
        'employee_id' => $employee->id,
        'created_by'  => User::factory()->create()->id,
        'job_title'   => 'Contadora',
        'salary'      => 2000000,
        'start_date'  => '2025-06-01', // antes del semestre a liquidar
        'state'       => true,
    ]);

    $benefits = (new PayrollCalculationService())->calculateSocialBenefits($contract, 2026, 1);

    $prima = collect($benefits)->firstWhere('type', 'prima');
    // Semestre completo (180 días) → prima = salario/30 × 180 = salario × 6
    expect((float) $prima->amount)->toBe(round(2000000 / 30 * 180, 2));

    $this->assertDatabaseHas('payroll_social_benefits', [
        'employee_id' => $employee->id,
        'type'        => 'prima',
    ]);
});

test('calcula cesantías proporcionales al tiempo trabajado en el semestre', function () {
    $employee = Employee::create([
        'identification_number' => '900111223',
        'first_name'            => 'Carlos',
        'last_name'             => 'Ruiz',
    ]);

    $contract = EmployeeContract::create([
        'employee_id' => $employee->id,
        'created_by'  => User::factory()->create()->id,
        'job_title'   => 'Auxiliar',
        'salary'      => 2000000,
        'start_date'  => '2026-04-01', // entra a mitad del semestre 1 (jul es semestre 2)
        'state'       => true,
    ]);

    $benefits = (new PayrollCalculationService())->calculateSocialBenefits($contract, 2026, 1);

    $cesantias = collect($benefits)->firstWhere('type', 'cesantias');
    // Del 1-abr al 30-jun = 91 días
    expect($cesantias->days_worked)->toBe(91);
    expect((float) $cesantias->amount)->toBe(round(2000000 * 91 / 360, 2));
});
