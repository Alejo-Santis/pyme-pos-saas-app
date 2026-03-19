<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->in('Feature');

pest()->extend(Tests\Unit\UnitTestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations personalizadas
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidUuid', function () {
    return $this->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

expect()->extend('toReturnInertia', function (string $component) {
    $response = $this->value;
    $response->assertInertia(fn ($page) => $page->component($component));
    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers globales para tests
|--------------------------------------------------------------------------
*/

/**
 * Crea un usuario admin dentro del tenant activo y lo autentica.
 * Requiere que tenancy() esté inicializada.
 */
function loginAsAdmin(array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create(array_merge([
        'name'  => 'Admin Test',
        'email' => 'admin@test.com',
    ], $attributes));

    $user->assignRole('admin');
    \Illuminate\Support\Facades\Auth::login($user);

    return $user;
}

/**
 * Crea un usuario con rol contador dentro del tenant activo.
 */
function loginAsContador(array $attributes = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create(array_merge([
        'name'  => 'Contador Test',
        'email' => 'contador@test.com',
    ], $attributes));

    $user->assignRole('contador');
    \Illuminate\Support\Facades\Auth::login($user);

    return $user;
}
