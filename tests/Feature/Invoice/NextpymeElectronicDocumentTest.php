<?php

use App\Modules\Audit\Models\ApiLog;
use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Jobs\ProcessElectronicDebitNoteJob;
use App\Modules\Invoice\Jobs\ProcessElectronicInvoiceJob;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\SendingElectronicDocument;
use App\Modules\Invoice\Services\ApiNextpymeService;
use App\Modules\Invoice\Services\ElectronicDocumentsProcessorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function nextpymeCompany(array $overrides = []): Company
{
    Company::query()->delete();

    return Company::create(array_merge([
        'identification_number' => '901999888',
        'dv' => '1',
        'business_name' => 'Sandbox Nextpyme S.A.S.',
        'trade_name' => 'Sandbox Nextpyme',
        'type_document_identification_id' => 6,
        'type_organization_id' => 1,
        'type_regime_id' => 1,
        'type_liability_id' => 7,
        'country_id' => 46,
        'municipality_id' => 149,
        'email' => 'facturacion@sandbox.test',
        'phone' => '3001234567',
        'address' => 'Calle 10 20 30',
        'electronic_documents' => true,
        'dian_provider' => 'nextpyme',
        'api_path_fe' => 'https://sandbox.nextpyme.plus/api',
        'api_token_fe' => 'token-sandbox-test',
        'is_active' => true,
    ], $overrides));
}

function nextpymeDocument(array $overrides = []): Document
{
    return Document::create(array_merge([
        'internal_code' => 'FE-TEST-1',
        'type_document_id' => 1,
        'type_document_operation_id' => 1,
        'prefix' => 'SETT',
        'number' => 1,
        'currency_id' => 1,
        'subtotal' => 100000,
        'total_discount' => 0,
        'total_tax' => 19000,
        'total' => 119000,
        'balance' => 119000,
        'payment_forms' => [
            ['payment_form_id' => 1, 'payment_method_id' => 10, 'value' => 119000],
        ],
        'taxes' => [
            ['tax_id' => 1, 'tax_amount' => 19000, 'percent' => 19, 'taxable_amount' => 100000],
        ],
        'invoice_lines' => [
            [
                'description' => 'Producto sandbox DIAN',
                'amount' => 1,
                'sale_price' => 100000,
                'discount' => 0,
                'taxable_amount' => 100000,
                'tax_amount' => 19000,
                'taxes' => [['tax_id' => 1, 'percent' => 19, 'tax_amount' => 19000]],
                'unit_measure_id' => 70,
            ],
        ],
        'legal_monetary_totals' => [
            'line_extension_amount' => 100000,
            'tax_exclusive_amount' => 100000,
            'tax_inclusive_amount' => 119000,
            'allowance_total_amount' => 0,
            'payable_amount' => 119000,
        ],
        'issue_date' => now()->toDateString(),
        'paid' => false,
        'electronic' => false,
        'accounted' => false,
        'annulled' => false,
        'dian_status' => 'pending',
    ], $overrides));
}

test('cliente nextpyme construye endpoint ubl sin duplicarlo y registra logs sanitizados', function () {
    nextpymeCompany(['api_path_fe' => 'https://sandbox.nextpyme.plus/api/ubl2.1']);

    Http::fake([
        'https://sandbox.nextpyme.plus/api/ubl2.1/invoice' => Http::response([
            'success' => true,
            'message' => 'Invoice generated successfully',
            'cufe' => 'CUFE-SANDBOX-OK',
        ], 200),
    ]);

    $response = app(ApiNextpymeService::class)->makeRequest(
        method: 'POST',
        endpoint: '/ubl2.1/invoice',
        parameters: [
            'number' => 1,
            'api_key' => 'debe-ocultarse',
            'customer' => ['token' => 'tambien-oculto'],
        ],
        documentId: null,
        operation: 'send_invoice',
    );

    expect($response['statusCode'])->toBe(200);

    Http::assertSent(fn ($request) =>
        $request->url() === 'https://sandbox.nextpyme.plus/api/ubl2.1/invoice'
        && $request->hasHeader('Authorization', 'Bearer token-sandbox-test')
    );

    $log = ApiLog::query()->where('operation', 'send_invoice')->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->endpoint)->toBe('https://sandbox.nextpyme.plus/api/ubl2.1/invoice')
        ->and($log->success)->toBeTrue()
        ->and($log->request_payload['api_key'])->toBe('[REDACTED]')
        ->and($log->request_payload['customer']['token'])->toBe('[REDACTED]');
});

test('prueba de conexion nextpyme valida alcance y autorizacion sin emitir documento', function () {
    nextpymeCompany();

    Http::fake([
        'https://sandbox.nextpyme.plus/api/ubl2.1/invoice' => Http::response([
            'message' => 'Method Not Allowed',
        ], 405),
    ]);

    $result = app(ApiNextpymeService::class)->testConnection();

    expect($result['configured'])->toBeTrue()
        ->and($result['reachable'])->toBeTrue()
        ->and($result['authorized'])->toBeTrue()
        ->and($result['statusCode'])->toBe(405)
        ->and($result['url'])->toBe('https://sandbox.nextpyme.plus/api/ubl2.1/invoice');

    $log = ApiLog::query()->where('operation', 'nextpyme_connection_test')->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->http_status)->toBe(405)
        ->and($log->success)->toBeTrue();
});

test('procesador electronico marca factura como enviada cuando nextpyme aprueba', function () {
    $document = nextpymeDocument();

    $api = Mockery::mock(ApiNextpymeService::class);
    $api->shouldReceive('makeRequest')
        ->once()
        ->withArgs(fn (...$args) =>
            $args[0] === 'POST'
            && $args[1] === '/ubl2.1/invoice'
            && is_array($args[2])
            && $args[4] === $document->id
            && $args[5] === 'send_invoice'
            && $args[6] === 1
        )
        ->andReturn([
            'statusCode' => 200,
            'data' => [
                'success' => true,
                'message' => 'Invoice generated successfully',
                'cufe' => 'CUFE-SANDBOX-APPROVED',
                'dian_validation_date_time' => now()->toISOString(),
            ],
        ]);

    $builder = new class {
        public function fromDocument(Document $document, bool $sendmail = true): array
        {
            return [
                'number' => $document->number,
                'sendmail' => $sendmail,
                'invoice_lines' => $document->invoice_lines,
            ];
        }
    };

    $result = (new ElectronicDocumentsProcessorService($document, 1, $api))
        ->process($builder, 'invoice', 'is_invoice');

    $document->refresh();
    $sending = SendingElectronicDocument::query()->where('document_id', $document->id)->first();

    expect($result['success'])->toBeTrue()
        ->and($result['reason'])->toBe('direct_success')
        ->and($document->electronic)->toBeTrue()
        ->and($document->dian_status)->toBe('sent')
        ->and($document->cufe)->toBe('CUFE-SANDBOX-APPROVED')
        ->and($sending)->not->toBeNull()
        ->and($sending->is_invoice)->toBeTrue()
        ->and($sending->is_valid)->toBeTrue();
});

test('procesador electronico usa endpoint y operacion trazable por tipo de documento', function (
    string $endpointKey,
    string $typeElectronicDocument,
    string $expectedEndpoint,
    string $expectedOperation,
    string $expectedFlag,
) {
    $document = nextpymeDocument([
        'internal_code' => strtoupper($endpointKey) . '-TEST-1',
        'type_document_operation_id' => match ($endpointKey) {
            'credit_note' => 91,
            'debit_note' => 92,
            'support_document' => 5,
            'adjustment_note_support_document' => 95,
            default => 1,
        },
    ]);

    $api = Mockery::mock(ApiNextpymeService::class);
    $api->shouldReceive('makeRequest')
        ->once()
        ->withArgs(fn (...$args) =>
            $args[0] === 'POST'
            && $args[1] === $expectedEndpoint
            && is_array($args[2])
            && $args[4] === $document->id
            && $args[5] === $expectedOperation
            && $args[6] === 2
        )
        ->andReturn([
            'statusCode' => 200,
            'data' => [
                'success' => true,
                'message' => 'Invoice generated successfully',
                'cufe' => 'CUFE-' . strtoupper($endpointKey),
                'dian_validation_date_time' => now()->toISOString(),
            ],
        ]);

    $builder = new class {
        public function fromDocument(Document $document, bool $sendmail = true): array
        {
            return [
                'number' => $document->number,
                'sendmail' => $sendmail,
                'lines' => $document->invoice_lines,
            ];
        }
    };

    $result = (new ElectronicDocumentsProcessorService($document, 2, $api))
        ->process($builder, $endpointKey, $typeElectronicDocument);

    $sending = SendingElectronicDocument::query()->where('document_id', $document->id)->first();

    expect($result['success'])->toBeTrue()
        ->and($sending)->not->toBeNull()
        ->and((bool) $sending->{$expectedFlag})->toBeTrue();
})->with([
    'nota credito' => ['credit_note', 'is_nc', '/ubl2.1/credit-note', 'send_credit_note', 'is_nc'],
    'nota debito' => ['debit_note', 'is_nd', '/ubl2.1/debit-note', 'send_debit_note', 'is_nd'],
    'documento soporte' => ['support_document', 'is_ds', '/ubl2.1/support-document', 'send_support_document', 'is_ds'],
    'nota ajuste soporte' => ['adjustment_note_support_document', 'is_nds', '/ubl2.1/sd-credit-note', 'send_support_document_credit_note', 'is_nds'],
]);

test('procesador electronico marca rechazo sin reintentar cuando nextpyme responde validacion 422', function () {
    $document = nextpymeDocument();

    $api = Mockery::mock(ApiNextpymeService::class);
    $api->shouldReceive('makeRequest')
        ->once()
        ->andReturn([
            'statusCode' => 422,
            'message' => 'Campo obligatorio faltante',
            'data' => [
                'success' => false,
                'message' => 'Campo obligatorio faltante',
            ],
        ]);

    $builder = new class {
        public function fromDocument(Document $document, bool $sendmail = true): array
        {
            return ['number' => $document->number];
        }
    };

    $result = (new ElectronicDocumentsProcessorService($document, 1, $api))
        ->process($builder, 'invoice', 'is_invoice');

    $document->refresh();
    $sending = SendingElectronicDocument::query()->where('document_id', $document->id)->first();

    expect($result['success'])->toBeFalse()
        ->and($result['reason'])->toBe('VALIDATION_ERROR_422')
        ->and($result['should_retry'])->toBeFalse()
        ->and($document->dian_status)->toBe('rejected')
        ->and($document->dian_attempts)->toBe(1)
        ->and($sending->status)->toBeFalse()
        ->and($sending->error_message['type'])->toBe('VALIDATION_ERROR_422');
});

test('comando preflight dian valida tenant y prueba conexion nextpyme', function () {
    nextpymeCompany([
        'dian_software_id' => 'software-sandbox',
        'dian_software_security_code' => 'security-code-sandbox',
    ]);

    Http::fake([
        'https://sandbox.nextpyme.plus/api/ubl2.1/invoice' => Http::response([
            'message' => 'Method Not Allowed',
        ], 405),
    ]);

    $this->artisan('dian:preflight', [
        '--tenant' => 'testempresa',
        '--connection' => true,
    ])->assertExitCode(0);

    $log = ApiLog::query()->where('operation', 'nextpyme_connection_test')->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->http_status)->toBe(405)
        ->and($log->success)->toBeTrue();
});

test('reintento dian de nota debito despacha job de nota debito', function () {
    Queue::fake();
    $this->withoutMiddleware(\App\Http\Middleware\EnsureTenantCanOperate::class);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $document = nextpymeDocument([
        'internal_code' => 'ND-TEST-1',
        'type_document_operation_id' => 92,
        'dian_status' => 'failed',
        'dian_error' => 'Error previo',
    ]);

    $response = $this->tenantPost("/invoices/{$document->id}/retry-dian");

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $document->refresh();

    expect($document->dian_status)->toBe('pending')
        ->and($document->dian_error)->toBeNull();

    Queue::assertPushed(ProcessElectronicDebitNoteJob::class);
    Queue::assertNotPushed(ProcessElectronicInvoiceJob::class);
});
