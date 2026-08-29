<?php

use App\Models\User;
use App\Modules\POS\Models\PosTerminal;
use App\Modules\POS\Models\PosTerminalUser;
use App\Modules\Cash\Models\CashBox;

// ── Tests del módulo POS ──────────────────────────────────────────────────────

function posAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function posTerminal(): PosTerminal
{
    $caja = CashBox::create([
        'name'          => 'Caja POS Test',
        'internal_code' => 'CPT-01',
        'is_main'       => true,
        'state'         => true,
    ]);

    return PosTerminal::create([
        'name'          => 'Terminal Test',
        'serial_number' => 'POS-TEST001',
        'location'      => 'Mostrador',
        'cash_box_id'   => $caja->id,
        'state'         => true,
    ]);
}

// ── Listado de terminales ─────────────────────────────────────────────────────

test('cajero puede ver el listado de terminales POS', function () {
    $this->actingAs(posAdmin());

    $this->tenantGet('/pos')
         ->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('POS/Index'));
});

// ── Creación de terminal ──────────────────────────────────────────────────────

test('puede crear una terminal POS', function () {
    $this->actingAs(posAdmin());

    $caja = CashBox::create([
        'name'          => 'Caja Test Crear',
        'internal_code' => 'CTC-01',
        'is_main'       => false,
        'state'         => true,
    ]);

    $response = $this->tenantPost('/pos/terminals', [
        'name'         => 'Caja Principal',
        'location'     => 'Entrada',
        'cash_box_id'  => $caja->id,
        'printer_name' => '',
        'is_usb'       => true,
        'printer_type' => 'escpos',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('pos_terminals', ['name' => 'Caja Principal']);
});

test('terminal creada tiene número de serie generado', function () {
    $this->actingAs(posAdmin());

    $this->tenantPost('/pos/terminals', [
        'name'     => 'Caja Serie Test',
        'is_usb'   => true,
        'printer_type' => 'escpos',
    ]);

    $terminal = PosTerminal::where('name', 'Caja Serie Test')->first();
    expect($terminal)->not->toBeNull();
    expect($terminal->serial_number)->toStartWith('POS-');
});

// ── Apertura de turno ─────────────────────────────────────────────────────────

test('usuario puede abrir turno en terminal disponible', function () {
    $user = posAdmin();
    $this->actingAs($user);
    $terminal = posTerminal();

    $response = $this->tenantPost("/pos/{$terminal->id}/open", [
        'initial_balance' => 50000,
    ]);

    $response->assertRedirect();

    $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
                            ->where('user_id', $user->id)
                            ->where('active_shift', true)
                            ->first();

    expect($shift)->not->toBeNull();
    expect((float) $shift->initial_balance)->toBe(50000.0);
    expect($shift->cashier_session_key)->not->toBeEmpty();
});

test('usuario no puede abrir dos turnos simultáneos', function () {
    $user = posAdmin();
    $this->actingAs($user);
    $terminal1 = posTerminal();

    // Primer turno
    $this->tenantPost("/pos/{$terminal1->id}/open", ['initial_balance' => 0]);

    // Segundo turno en otra terminal → debe fallar
    $terminal2 = PosTerminal::create([
        'name'          => 'Terminal 2',
        'serial_number' => 'POS-TEST002',
        'state'         => true,
    ]);

    $response = $this->tenantPost("/pos/{$terminal2->id}/open", ['initial_balance' => 0]);
    $response->assertSessionHasErrors('terminal');
});

// ── Configuración de impresora ────────────────────────────────────────────────

test('puede configurar impresora USB en terminal', function () {
    $this->actingAs(posAdmin());

    $terminal = posTerminal();

    $response = $this->tenantPut("/pos/terminals/{$terminal->id}", [
        'name'         => $terminal->name,
        'printer_name' => 'XP-58',
        'printer_type' => 'escpos',
        'is_usb'       => true,
        'state'        => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('pos_terminals', [
        'id'           => $terminal->id,
        'printer_name' => 'XP-58',
        'is_usb'       => true,
    ]);
});

test('puede configurar impresora de red con IP y puerto', function () {
    $this->actingAs(posAdmin());
    $terminal = posTerminal();

    $response = $this->tenantPut("/pos/terminals/{$terminal->id}", [
        'name'         => $terminal->name,
        'printer_name' => 'Epson Red',
        'printer_type' => 'escpos',
        'printer_ip'   => '192.168.1.100',
        'printer_port' => 9100,
        'is_usb'       => false,
        'state'        => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('pos_terminals', [
        'id'           => $terminal->id,
        'printer_ip'   => '192.168.1.100',
        'printer_port' => 9100,
    ]);
});
