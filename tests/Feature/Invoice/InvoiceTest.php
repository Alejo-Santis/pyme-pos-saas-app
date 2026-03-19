<?php

use App\Models\User;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;

// ── Factories de soporte ──────────────────────────────────────────────────────

function invoiceAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return $user;
}

function invoicePayload(array $overrides = []): array
{
    $third = ThirdParty::create([
        'name'                  => 'Cliente Test S.A.S',
        'identification_number' => '900000001-1',
        'type'                  => 'company',
        'fiscal_regime'         => '48',
        'state'                 => true,
    ]);

    $item = Item::create([
        'name'               => 'Producto Factura',
        'internal_code'      => 'FAC-001',
        'type'               => 'product',
        'default_sale_price' => 50000,
        'state'              => true,
    ]);

    return array_merge([
        'third_party_id'               => $third->id,
        'type_document_operation_id'   => 1,   // Factura de venta
        'date'                         => now()->toDateString(),
        'due_date'                     => now()->addDays(30)->toDateString(),
        'payment_forms'                => [
            ['payment_form_id' => 1, 'payment_method_id' => 10, 'value' => 59500],
        ],
        'lines' => [
            [
                'item_id'        => $item->id,
                'description'    => $item->name,
                'amount'         => 1,
                'sale_price'     => 50000,
                'discount'       => 0,
                'unit_measure_id'=> 70,
                'warehouse_out'  => null,
                'movement_type'  => 'OUT',
                'taxes'          => [['tax_id' => null, 'percent' => 19]],
            ],
        ],
        'taxes'  => [],
        'note'   => null,
    ], $overrides);
}

// ── Tests de Facturación ──────────────────────────────────────────────────────

test('admin puede ver el listado de facturas', function () {
    $this->actingAs(invoiceAdmin());

    $response = $this->tenantGet('/invoices');

    $response->assertStatus(200)
             ->assertInertia(fn ($page) => $page->component('Invoice/Index'));
});

test('formulario de nueva factura carga correctamente', function () {
    $this->actingAs(invoiceAdmin());

    $response = $this->tenantGet('/invoices/create');

    $response->assertStatus(200)
             ->assertInertia(fn ($page) => $page->component('Invoice/Form'));
});

test('factura requiere al menos una línea', function () {
    $this->actingAs(invoiceAdmin());

    $third = ThirdParty::create([
        'name'                  => 'Cliente Vacío',
        'identification_number' => '900000002-2',
        'type'                  => 'company',
        'fiscal_regime'         => '48',
        'state'                 => true,
    ]);

    $response = $this->tenantPost('/invoices', [
        'third_party_id' => $third->id,
        'date'           => now()->toDateString(),
        'lines'          => [],
        'payment_forms'  => [],
        'taxes'          => [],
    ]);

    $response->assertSessionHasErrors('lines');
});

test('factura creada tiene el total correcto (50000 + 19% IVA = 59500)', function () {
    $this->actingAs(invoiceAdmin());

    $response = $this->tenantPost('/invoices', invoicePayload());
    $response->assertRedirect();

    $doc = Document::latest()->first();
    expect($doc)->not->toBeNull();
    expect((float) $doc->total)->toBe(59500.0);
});

test('nota crédito requiere documento referenciado', function () {
    $this->actingAs(invoiceAdmin());

    $response = $this->tenantPost('/invoices', invoicePayload([
        'type_document_operation_id' => 91,  // Nota crédito
        'credit_note_reason_id'      => null,
        'document_reference_id'      => null,
    ]));

    $response->assertSessionHasErrors('document_reference_id');
});
