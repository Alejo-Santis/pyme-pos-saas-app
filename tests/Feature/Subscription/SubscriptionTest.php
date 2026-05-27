<?php

use App\Models\User;

test('usuario autenticado puede ver su suscripcion tenant', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $this->tenantGet('/subscription')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Subscription/Show'));
});
