<?php

use App\Models\User;
use App\Modules\Accounting\Models\ChartOfAccount;

// ── Tests de Contabilidad ─────────────────────────────────────────────────────

test('admin puede ver el diario contable', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->tenantGet('/accounting/journal')
         ->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Accounting/Journal'));
});

test('contador puede ver el balance de pruebas', function () {
    $user = User::factory()->create();
    $user->assignRole('contador');
    $this->actingAs($user);

    $this->tenantGet('/accounting/trial-balance')
         ->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Accounting/TrialBalance'));
});

test('vendedor no puede acceder a contabilidad', function () {
    $user = User::factory()->create();
    $user->assignRole('vendedor');
    $this->actingAs($user);

    // Solo admin y contador tienen acceso
    $this->tenantGet('/accounting/journal')->assertStatus(403);
});

test('PUC Colombia está cargado (mínimo 200 cuentas)', function () {
    // Verifica que el seeder del PUC se corrió al crear el tenant
    $count = ChartOfAccount::count();
    expect($count)->toBeGreaterThan(200);
});

test('las cuentas PUC tienen los niveles correctos', function () {
    // Clase (1 dígito)
    $clases = ChartOfAccount::whereRaw('LENGTH(code) = 1')->count();
    expect($clases)->toBeGreaterThan(0);

    // Grupos (2 dígitos)
    $grupos = ChartOfAccount::whereRaw('LENGTH(code) = 2')->count();
    expect($grupos)->toBeGreaterThan(0);
});
