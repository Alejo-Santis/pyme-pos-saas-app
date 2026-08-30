<?php

use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

// ── Tests de autenticación del panel super-admin (dominio central) ───────────

test('admin login exitoso redirige al dashboard', function () {
    AdminUser::create([
        'name'      => 'Super Admin',
        'email'     => 'superadmin@test.co',
        'password'  => Hash::make('password123'),
        'is_active' => true,
    ]);

    $response = $this->post('/admin/login', [
        'email'    => 'superadmin@test.co',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('admin.dashboard'));
});

test('el login del panel admin se bloquea después de varios intentos fallidos', function () {
    $attempt = fn () => $this->post('/admin/login', [
        'email'    => 'ataque@test.co',
        'password' => 'wrongpassword',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $attempt()->assertSessionHasErrors('email');
    }

    $attempt()->assertStatus(429);
});
