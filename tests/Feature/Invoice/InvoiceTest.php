<?php

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;

// ── Factories de soporte ──────────────────────────────────────────────────────

function invoiceAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function invoiceCompany(): Company
{
    return Company::firstOrCreate(
        ['identification_number' => '900123456'],
        [
            'dv'                               => '7',
            'business_name'                    => 'Empresa Test S.A.S',
            'type_document_identification_id'  => 6, // NIT
            'type_organization_id'             => 1, // Persona Jurídica
            'type_regime_id'                   => 1, // Responsable de IVA
            'type_liability_id'                => 7, // No aplica - Otros
            'country_id'                       => 46,  // Colombia
            'municipality_id'                  => 149, // Bogotá D.C.
            'email'                            => 'empresa@test.co',
            'address'                          => 'Calle Falsa 123',
        ]
    );
}

function invoiceThirdParty(array $overrides = []): ThirdParty
{
    return ThirdParty::create(array_merge([
        'type_document_identification_id' => 6, // NIT
        'identification_number'           => '900000001',
        'dv'                               => '1',
        'type_organization_id'             => 1, // Persona Jurídica
        'type_regime_id'                   => 1,
        'type_liability_id'                => 7,
        'type_third_id'                    => 1, // Cliente
        'name'                             => 'Cliente Test S.A.S',
        'is_active'                        => true,
    ], $overrides));
}

function invoicePayload(array $overrides = []): array
{
    $company = invoiceCompany();
    $third   = invoiceThirdParty();

    $item = Item::create([
        'name'               => 'Producto Factura',
        'internal_code'      => 'FAC-001',
        'type'               => 'product',
        'default_sale_price' => 50000,
        'is_active'          => true,
    ]);

    return array_merge([
        'company_id'                  => $company->id,
        'third_party_id'              => $third->id,
        'type_document_id'            => 1, // Factura Electrónica de Venta
        'type_document_operation_id'  => 1, // Factura de venta
        'issue_date'                  => now()->toDateString(),
        'payment_forms'               => [
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
                'movement_type'  => 'NONE',
                'taxes'          => [['tax_id' => null, 'percent' => 19]],
            ],
        ],
        'taxes' => [],
        'note'  => null,
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

    $response = $this->tenantPost('/invoices', invoicePayload(['lines' => []]));

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

test('nota crédito requiere un motivo de corrección válido', function () {
    $this->actingAs(invoiceAdmin());

    $this->tenantPost('/invoices', invoicePayload())->assertRedirect();
    $document = Document::latest()->first();

    // correction_concept fuera del rango permitido (1-5)
    $response = $this->tenantPost("/invoices/{$document->id}/credit-note", [
        'correction_concept' => 99,
        'note'               => 'Corrección de prueba',
        'selected_lines'     => [],
    ]);

    $response->assertSessionHasErrors('correction_concept');
});

test('nota crédito se emite correctamente sobre una factura existente', function () {
    $this->actingAs(invoiceAdmin());

    $this->tenantPost('/invoices', invoicePayload())->assertRedirect();
    $document = Document::latest()->first();
    $line     = $document->lines()->first();

    $response = $this->tenantPost("/invoices/{$document->id}/credit-note", [
        'correction_concept' => 1,
        'note'               => 'Devolución de mercancía',
        'selected_lines'     => [
            ['line_id' => $line->id, 'qty' => 1],
        ],
    ]);

    $response->assertRedirect();
    // type_document_operation_id 91 = Nota Crédito (código DIAN de operación,
    // no el catálogo type_documents — CreditNoteService la marca así).
    $this->assertDatabaseHas('documents', [
        'type_document_operation_id' => 91,
    ]);
});
