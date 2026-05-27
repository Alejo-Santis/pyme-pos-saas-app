<?php

use App\Models\User;

test('usuario autenticado puede ver la guia de primeros pasos', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $this->tenantGet('/setup')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Index')
            ->has('setup.steps')
        );
});
