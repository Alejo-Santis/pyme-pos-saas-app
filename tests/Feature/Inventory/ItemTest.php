<?php

use App\Models\User;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Core\Models\Warehouse;

// ── Helpers del archivo ───────────────────────────────────────────────────────

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function createWarehouse(): Warehouse
{
    return Warehouse::create([
        'name'          => 'Bodega Test',
        'internal_code' => 'BOD-01',
        'is_default'    => true,
        'state'         => true,
    ]);
}

function createCategory(): ItemCategory
{
    return ItemCategory::create([
        'name'  => 'Categoría Test',
        'state' => true,
    ]);
}

// ── Tests de listado ──────────────────────────────────────────────────────────

test('admin puede ver el listado de artículos', function () {
    $this->actingAs(adminUser());

    $response = $this->tenantGet('/inventory/items');

    $response->assertStatus(200)
             ->assertInertia(fn ($page) => $page->component('Inventory/Items'));
});

test('usuario sin autenticación es redirigido al login', function () {
    $response = $this->tenantGet('/inventory/items');
    $response->assertRedirect('/login');
});

// ── Tests de creación ─────────────────────────────────────────────────────────

test('admin puede crear un artículo', function () {
    $this->actingAs(adminUser());
    $cat = createCategory();

    $response = $this->tenantPost('/inventory/items', [
        'name'              => 'Artículo Test',
        'internal_code'     => 'ART-001',
        'item_category_id'  => $cat->id,
        'default_sale_price'=> 10000,
        'default_cost_price'=> 5000,
        'type'              => 'product',
        'state'             => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('items', ['internal_code' => 'ART-001']);
});

test('artículo con código duplicado falla validación', function () {
    $this->actingAs(adminUser());
    $cat = createCategory();

    Item::create([
        'name'          => 'Existente',
        'internal_code' => 'DUP-001',
        'type'          => 'product',
    ]);

    $response = $this->tenantPost('/inventory/items', [
        'name'          => 'Nuevo',
        'internal_code' => 'DUP-001',
        'type'          => 'product',
    ]);

    $response->assertSessionHasErrors('internal_code');
});

// ── Tests de edición ──────────────────────────────────────────────────────────

test('admin puede actualizar un artículo', function () {
    $this->actingAs(adminUser());

    $item = Item::create([
        'name'          => 'Original',
        'internal_code' => 'UPD-001',
        'type'          => 'product',
        'state'         => true,
    ]);

    $response = $this->tenantPut("/inventory/items/{$item->id}", [
        'name'          => 'Actualizado',
        'internal_code' => 'UPD-001',
        'type'          => 'product',
        'state'         => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Actualizado']);
});

// ── Tests de eliminación ──────────────────────────────────────────────────────

test('admin puede eliminar un artículo (soft delete)', function () {
    $this->actingAs(adminUser());

    $item = Item::create([
        'name'          => 'Para eliminar',
        'internal_code' => 'DEL-001',
        'type'          => 'product',
    ]);

    $this->tenantDelete("/inventory/items/{$item->id}")->assertRedirect();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});
