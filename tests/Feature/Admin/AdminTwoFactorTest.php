<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Facade as Google2FA;

// ── Helpers del archivo ───────────────────────────────────────────────────────

function twoFactorAdmin(): AdminUser
{
    return AdminUser::create([
        'name'      => 'Admin 2FA',
        'email'     => 'admin2fa@test.co',
        'password'  => Hash::make('password123'),
        'is_active' => true,
    ]);
}

// ── Activación ─────────────────────────────────────────────────────────────────

test('activar 2FA genera un secreto y muestra el QR', function () {
    $admin = twoFactorAdmin();
    $this->actingAs($admin, 'admin');

    $this->post('/admin/security/enable')->assertRedirect();

    $admin->refresh();
    expect($admin->two_factor_secret)->not->toBeNull();
    expect($admin->two_factor_enabled)->toBeFalse();

    $this->get('/admin/security')
         ->assertInertia(fn ($p) => $p->component('Admin/Security')
             ->where('setup.secret', $admin->two_factor_secret));
});

test('confirmar con un código inválido no activa 2FA', function () {
    $admin = twoFactorAdmin();
    $this->actingAs($admin, 'admin');
    $this->post('/admin/security/enable');
    $admin->refresh();

    $response = $this->post('/admin/security/confirm', ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect($admin->fresh()->two_factor_enabled)->toBeFalse();
});

test('confirmar con el código correcto activa 2FA y entrega códigos de recuperación', function () {
    $admin = twoFactorAdmin();
    $this->actingAs($admin, 'admin');
    $this->post('/admin/security/enable');
    $admin->refresh();

    $validCode = Google2FA::getCurrentOtp($admin->two_factor_secret);

    $response = $this->post('/admin/security/confirm', ['code' => $validCode]);

    $response->assertRedirect();
    $response->assertSessionHas('recoveryCodes');
    expect(session('recoveryCodes'))->toHaveCount(8);

    $admin->refresh();
    expect($admin->two_factor_enabled)->toBeTrue();
    expect($admin->two_factor_confirmed_at)->not->toBeNull();
});

// ── Login con 2FA activo ─────────────────────────────────────────────────────

test('login con 2FA activo no autentica hasta pasar el segundo factor', function () {
    $admin = twoFactorAdmin();
    $admin->update([
        'two_factor_secret'  => Google2FA::generateSecretKey(),
        'two_factor_enabled' => true,
    ]);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password123']);

    // Todavía no está autenticado con el guard admin.
    $this->assertGuest('admin');

    $response = $this->get('/admin/two-factor');
    $response->assertStatus(200)
             ->assertInertia(fn ($p) => $p->component('Admin/TwoFactorChallenge'));
});

test('el código TOTP correcto completa el login', function () {
    $admin = twoFactorAdmin();
    $secret = Google2FA::generateSecretKey();
    $admin->update(['two_factor_secret' => $secret, 'two_factor_enabled' => true]);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password123']);

    $response = $this->post('/admin/two-factor', ['code' => Google2FA::getCurrentOtp($secret)]);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');
});

test('un código de recuperación válido completa el login y se consume', function () {
    $admin = twoFactorAdmin();
    $secret = Google2FA::generateSecretKey();
    $admin->update([
        'two_factor_secret'         => $secret,
        'two_factor_enabled'        => true,
        'two_factor_recovery_codes' => [Hash::make('AAAA-BBBB')],
    ]);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password123']);

    $response = $this->post('/admin/two-factor', ['recovery_code' => 'AAAA-BBBB']);

    $response->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin, 'admin');

    // El código ya usado no debe volver a servir.
    Auth::guard('admin')->logout();
    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password123']);
    $second = $this->post('/admin/two-factor', ['recovery_code' => 'AAAA-BBBB']);
    $second->assertSessionHasErrors('code');
});

test('un código TOTP incorrecto no completa el login', function () {
    $admin = twoFactorAdmin();
    $secret = Google2FA::generateSecretKey();
    $admin->update(['two_factor_secret' => $secret, 'two_factor_enabled' => true]);

    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password123']);

    $response = $this->post('/admin/two-factor', ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    $this->assertGuest('admin');
});

// ── Desactivación ──────────────────────────────────────────────────────────────

test('desactivar 2FA requiere la contraseña correcta', function () {
    $admin = twoFactorAdmin();
    $admin->update([
        'two_factor_secret'  => Google2FA::generateSecretKey(),
        'two_factor_enabled' => true,
    ]);
    $this->actingAs($admin, 'admin');

    $this->post('/admin/security/disable', ['password' => 'wrongpassword'])
         ->assertSessionHasErrors('password');
    expect($admin->fresh()->two_factor_enabled)->toBeTrue();

    $this->post('/admin/security/disable', ['password' => 'password123'])->assertRedirect();
    expect($admin->fresh()->two_factor_enabled)->toBeFalse();
    expect($admin->fresh()->two_factor_secret)->toBeNull();
});
