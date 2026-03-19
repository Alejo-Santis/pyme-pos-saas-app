<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// ── Tests de Login (contexto tenant) ─────────────────────────────────────────

test('muestra la página de login', function () {
    $response = $this->tenantGet('/login');

    $response->assertStatus(200)
             ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('login exitoso redirige al dashboard', function () {
    $user = User::factory()->create([
        'email'    => 'cajero@empresa.co',
        'password' => Hash::make('password123'),
    ]);
    $user->assignRole('admin');

    $response = $this->tenantPost('/login', [
        'email'    => 'cajero@empresa.co',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('credenciales incorrectas no autentican', function () {
    $response = $this->tenantPost('/login', [
        'email'    => 'noexiste@empresa.co',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('campos requeridos validan correctamente', function () {
    $response = $this->tenantPost('/login', []);

    $response->assertSessionHasErrors(['email', 'password']);
});

test('logout cierra la sesión', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->tenantPost('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
