<?php

use App\Models\User;
use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Models\Document;
use App\Modules\Purchases\Models\TaxMailbox;
use Illuminate\Http\UploadedFile;

// ── Helpers del archivo ───────────────────────────────────────────────────────

function mailboxAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function sampleUblXml(): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">
    <cbc:UUID>a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2</cbc:UUID>
    <cbc:IssueDate>2026-02-10</cbc:IssueDate>
    <cbc:IssueTime>14:30:00</cbc:IssueTime>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>Proveedor Test S.A.S</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme><cbc:CompanyID>800123456</cbc:CompanyID></cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:LegalMonetaryTotal>
        <cbc:PayableAmount currencyID="COP">119000</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
XML;
}

function uploadMailboxXml(string $filename = 'factura.xml'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'ubl_').'.xml';
    file_put_contents($path, sampleUblXml());

    return new UploadedFile($path, $filename, 'application/xml', null, true);
}

function mailboxRealDocument(): Document
{
    $company = Company::firstOrCreate(
        ['identification_number' => '900999888'],
        [
            'dv' => '1', 'business_name' => 'Empresa Buzón Test S.A.S',
            'type_document_identification_id' => 6, 'type_organization_id' => 1,
            'type_regime_id' => 1, 'type_liability_id' => 7,
            'country_id' => 46, 'municipality_id' => 149,
            'email' => 'buzon@test.co', 'address' => 'Calle Falsa 123',
        ]
    );

    return Document::create([
        'company_id'                 => $company->id,
        'type_document_id'           => 1,
        'type_document_operation_id' => 14, // Compra
        'issue_date'                 => now()->toDateString(),
    ]);
}

// ── Tests ──────────────────────────────────────────────────────────────────────

test('admin puede ver el listado del buzón tributario', function () {
    $this->actingAs(mailboxAdmin());

    $this->tenantGet('/tax-mailbox')
         ->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Purchases/TaxMailbox/Index'));
});

test('cargar un XML extrae los datos del emisor automáticamente', function () {
    $this->actingAs(mailboxAdmin());

    $response = $this->tenantPost('/tax-mailbox', ['file' => uploadMailboxXml()]);

    $response->assertRedirect();
    $this->assertDatabaseHas('tax_mailboxes', [
        'identification_number_provider' => '800123456',
        'business_name_provider'         => 'Proveedor Test S.A.S',
        'cufe'                            => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
    ]);

    $mailbox = TaxMailbox::first();
    expect((float) $mailbox->tax_inclusive_amount)->toBe(119000.0);
    expect($mailbox->document_id)->toBeNull();
});

test('un XML válido sin estructura UBL igual queda registrado en el buzón', function () {
    $this->actingAs(mailboxAdmin());

    // XML bien formado pero sin los nodos UBL esperados (cbc:UUID, PartyTaxScheme...).
    $path = tempnam(sys_get_temp_dir(), 'other_').'.xml';
    file_put_contents($path, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<root><nota>documento sin estructura de factura DIAN</nota></root>");
    $file = new UploadedFile($path, 'sin-estructura.xml', 'application/xml', null, true);

    $response = $this->tenantPost('/tax-mailbox', ['file' => $file]);

    $response->assertRedirect();
    $this->assertDatabaseHas('tax_mailboxes', ['xml_file_name' => 'sin-estructura.xml']);

    $mailbox = TaxMailbox::first();
    expect($mailbox->identification_number_provider)->toBeNull();
    expect($mailbox->cufe)->toBeNull();
});

test('un archivo que no es XML es rechazado por la validación', function () {
    $this->actingAs(mailboxAdmin());

    $path = tempnam(sys_get_temp_dir(), 'bad_').'.xml';
    file_put_contents($path, 'esto no es xml, es texto plano');
    $file = new UploadedFile($path, 'roto.xml', 'application/xml', null, true);

    $response = $this->tenantPost('/tax-mailbox', ['file' => $file]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseMissing('tax_mailboxes', ['xml_file_name' => 'roto.xml']);
});

test('admin puede ver el detalle de un documento del buzón', function () {
    $this->actingAs(mailboxAdmin());
    $this->tenantPost('/tax-mailbox', ['file' => uploadMailboxXml()]);
    $mailbox = TaxMailbox::first();

    $this->tenantGet("/tax-mailbox/{$mailbox->id}")
         ->assertStatus(200)
         ->assertInertia(fn ($p) => $p->component('Purchases/TaxMailbox/Show'));
});

test('admin puede descargar el XML cargado', function () {
    $this->actingAs(mailboxAdmin());
    $this->tenantPost('/tax-mailbox', ['file' => uploadMailboxXml('mi-factura.xml')]);
    $mailbox = TaxMailbox::first();

    $response = $this->tenantGet("/tax-mailbox/{$mailbox->id}/download");

    $response->assertStatus(200);
    expect($response->headers->get('content-disposition'))->toContain('mi-factura.xml');
    expect($response->getContent())->toContain('Proveedor Test S.A.S');
});

test('no se puede eliminar un documento ya procesado', function () {
    $this->actingAs(mailboxAdmin());

    // Un documento real vinculado (simula que ya fue convertido a compra).
    $document = mailboxRealDocument();

    $mailbox = TaxMailbox::create([
        'business_name_provider' => 'Proveedor Procesado',
        'document_id'            => $document->id,
    ]);

    $response = $this->tenantDelete("/tax-mailbox/{$mailbox->id}");

    $response->assertStatus(422);
    $this->assertDatabaseHas('tax_mailboxes', ['id' => $mailbox->id]);
});

test('admin puede eliminar un documento pendiente', function () {
    $this->actingAs(mailboxAdmin());
    $this->tenantPost('/tax-mailbox', ['file' => uploadMailboxXml()]);
    $mailbox = TaxMailbox::first();

    $this->tenantDelete("/tax-mailbox/{$mailbox->id}")->assertRedirect();

    $this->assertDatabaseMissing('tax_mailboxes', ['id' => $mailbox->id]);
});
