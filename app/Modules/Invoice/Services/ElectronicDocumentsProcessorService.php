<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\SendingElectronicDocument;
use App\Shared\Traits\ElectronicDocumentTrait;
use App\Shared\Traits\ToolTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Orquestador del flujo de facturación electrónica DIAN.
 * Recibe cualquier builder (Invoice, CreditNote, SupportDocument),
 * llama a ApiNextpymeService, procesa la respuesta y actualiza la BD.
 *
 * Adaptado de xedoc-laravel-svelte/app/Services/ElectronicInvoicing/ElectronicDocumentsProcessorService.php
 */
class ElectronicDocumentsProcessorService
{
    use ElectronicDocumentTrait, ToolTrait;

    protected int $maxAttempts = 3;

    protected array $endpoints = [
        'invoice'                          => ['url' => '/ubl2.1/invoice',          'method' => 'POST'],
        'credit_note'                      => ['url' => '/ubl2.1/credit-note',       'method' => 'POST'],
        'debit_note'                       => ['url' => '/ubl2.1/debit-note',        'method' => 'POST'],
        'support_document'                 => ['url' => '/ubl2.1/support-document',  'method' => 'POST'],
        'adjustment_note_support_document' => ['url' => '/ubl2.1/sd-credit-note',   'method' => 'POST'],
        'events'                           => ['url' => '/ubl2.1/send-event-data',  'method' => 'POST'],
    ];

    public function __construct(
        protected Document          $document,
        protected int               $currentAttempt,
        protected ApiNextpymeService $apiService
    ) {}

    /**
     * Procesa el envío del documento electrónico.
     *
     * @param object $builder              Builder que implementa fromDocument()
     * @param string $endpointKey          Clave del endpoint (ej: 'invoice')
     * @param string $typeElectronicDocument Tipo para el registro (ej: 'is_invoice')
     */
    public function process(object $builder, string $endpointKey, string $typeElectronicDocument): array
    {
        $sending = $this->getOrCreateSendingRecord($typeElectronicDocument);

        $this->logAttempt();

        // Construir JSON UBL 2.1
        $json = $builder->fromDocument($this->document, true);

        if (! $json) {
            return $this->handleError($sending, 'Error al construir el JSON del documento', 'JSON_BUILD_ERROR');
        }

        if (! isset($this->endpoints[$endpointKey])) {
            return $this->handleError($sending, "Endpoint no configurado: {$endpointKey}", 'INVALID_ENDPOINT');
        }

        $endpoint = $this->endpoints[$endpointKey];

        Log::info('Enviando documento a DIAN', [
            'document_id' => $this->document->id,
            'endpoint'    => $endpoint['url'],
            'attempt'     => $this->currentAttempt,
        ]);

        // Llamada HTTP
        $response = $this->apiService->makeRequest($endpoint['method'], $endpoint['url'], $json);

        // Guardar respuesta filtrada (sin XMLs pesados)
        $sending->update([
            'response_api' => $this->filterResponse($response),
            'attempts'     => $this->currentAttempt,
        ]);

        return match ($response['statusCode']) {
            200     => $this->handleSuccessResponse($sending, $response['data'] ?? []),
            422     => $this->handleError($sending, 'Error de validación en datos', 'VALIDATION_ERROR_422', 422),
            503     => $this->handleError($sending, 'Servicio DIAN no disponible', 'SERVICE_UNAVAILABLE_503', 503),
            default => $this->handleError(
                $sending,
                $response['message'] ?? 'Error desconocido',
                $response['statusCode'] >= 500 ? 'SERVER_ERROR' : 'CLIENT_ERROR',
                $response['statusCode']
            ),
        };
    }

    // ── Procesamiento de respuesta ────────────────────────────────────────

    protected function handleSuccessResponse(SendingElectronicDocument $sending, array $data): array
    {
        // Respuesta estándar DIAN con ResponseDian
        if (isset($data['ResponseDian'])) {
            return $this->processDianResponse($sending, $data);
        }

        // Respuesta con success=false
        if (isset($data['success']) && ! $this->isTrue($data['success'])) {
            return $this->handleFailedSuccess($sending, $data);
        }

        // Éxito directo con CUFE (Regla 90)
        if ($this->isSuccessMessage($data['message'] ?? '') && ! empty($data['cufe'] ?? $data['uuid_dian'] ?? null)) {
            return $this->handleDirectSuccess($sending, $data);
        }

        return $this->handleError($sending, 'Estructura de respuesta desconocida', 'UNKNOWN_RESPONSE');
    }

    protected function processDianResponse(SendingElectronicDocument $sending, array $data): array
    {
        try {
            $result  = $data['ResponseDian']['Envelope']['Body']['SendBillSyncResponse']['SendBillSyncResult'];
            $isValid = $this->isTrue($result['IsValid'] ?? false);
            $error   = $result['ErrorMessage'] ?? null;
            $uuid    = $data['uuid_dian'] ?? null;

            // Regla 90 — documento ya procesado anteriormente
            if (! $isValid && $this->isRule90Error($error)) {
                $isValid = $this->validateRule90($sending, $data, $uuid);
            }

            $sending->update([
                'is_valid'                  => $isValid,
                'status_code'               => $result['StatusCode'] ?? null,
                'status_message'            => $result['StatusMessage'] ?? null,
                'status_description'        => $result['StatusMessage'] ?? null,
                'xml_document_key'          => $uuid,
                'qr_str'                    => $data['QRStr'] ?? null,
                'dian_validation_date_time' => $this->parseDateTime($data['dian_validation_date_time'] ?? null),
                'status'                    => $isValid,
                'error_message'             => $isValid ? null : $error,
            ]);

            if ($isValid) {
                $this->updateDocumentElectronic($uuid, $data['dian_validation_date_time'] ?? null);
                $this->logSuccess();

                return ['success' => true, 'reason' => 'approved', 'should_retry' => false];
            }

            $this->logFailure($this->summarizeError($error));

            return $this->handleError($sending, $error, 'DIAN_VALIDATION_ERROR', $result['StatusCode'] ?? null);
        } catch (Exception $e) {
            return $this->handleError($sending, $e->getMessage(), 'DIAN_RESPONSE_PARSE_ERROR');
        }
    }

    protected function handleFailedSuccess(SendingElectronicDocument $sending, array $data): array
    {
        $error = $data['message'] ?? $data['ErrorMessage'] ?? 'Respuesta no exitosa';

        if ($this->isRule90Error($error)) {
            $uuid = $data['uuid_dian'] ?? $data['CUFE'] ?? null;
            if ($this->validateRule90($sending, $data, $uuid)) {
                return ['success' => true, 'reason' => 'rule_90_validated', 'should_retry' => false];
            }

            return $this->handleError($sending, $error, 'RULE_90_NO_RETRY');
        }

        return $this->handleError($sending, $error, 'API_SUCCESS_FALSE');
    }

    protected function handleDirectSuccess(SendingElectronicDocument $sending, array $data): array
    {
        $uuid = $data['cufe'] ?? $data['uuid_dian'] ?? null;

        if ($this->validateRule90($sending, $data, $uuid)) {
            return ['success' => true, 'reason' => 'direct_success', 'should_retry' => false];
        }

        return $this->handleError($sending, 'Éxito sin validación', 'SUCCESS_NO_VALIDATION');
    }

    protected function validateRule90(SendingElectronicDocument $sending, array $data, ?string $uuid): bool
    {
        if (empty($uuid)) {
            return false;
        }

        $third = $this->document->thirdParty;

        // Sin tercero o consumidor final → confiar directamente
        if (! $third || $this->isConsumerFinal($third)) {
            $this->markRule90Valid($sending, $uuid, $data['dian_validation_date_time'] ?? null, 'consumer_final');

            return true;
        }

        // Con NIT: validar coincidencia
        $nitDoc      = preg_replace('/[^0-9]/', '', $third->identification_number ?? '');
        $nitResponse = $this->extractNitFromResponse($data);

        if (! empty($nitDoc) && $nitDoc === $nitResponse) {
            $this->markRule90Valid($sending, $uuid, $data['dian_validation_date_time'] ?? null, 'nit_match');

            return true;
        }

        // UUID presente sin NIT → confiar en DIAN
        if (empty($nitResponse)) {
            $this->markRule90Valid($sending, $uuid, $data['dian_validation_date_time'] ?? null, 'dian_trust');

            return true;
        }

        return false;
    }

    protected function markRule90Valid(SendingElectronicDocument $sending, string $uuid, $dateTime, string $method): void
    {
        $sending->update([
            'is_valid'                  => true,
            'status'                    => true,
            'xml_document_key'          => $uuid,
            'dian_validation_date_time' => $this->parseDateTime($dateTime),
            'error_message'             => ['type' => 'RULE_90_VALIDATED', 'method' => $method],
        ]);

        $this->updateDocumentElectronic($uuid, $dateTime);
        $this->logSuccess("Regla 90 ({$method})");
    }

    protected function handleError(
        SendingElectronicDocument $sending,
        string|array $message,
        string $type,
        ?int $statusCode = null
    ): array {
        $shouldRetry  = $this->shouldRetry($type, $statusCode);
        $messageStr   = is_array($message) ? $this->summarizeError($message) : $message;

        $sending->update([
            'attempts'      => $this->currentAttempt,
            'status'        => false,
            'error_message' => [
                'type'        => $type,
                'message'     => $messageStr,
                'status_code' => $statusCode,
                'attempt'     => $this->currentAttempt,
                'timestamp'   => now()->toISOString(),
            ],
        ]);

        $this->logFailure($messageStr);

        return [
            'success'      => false,
            'reason'       => $type,
            'should_retry' => $shouldRetry && $this->currentAttempt < $this->maxAttempts,
            'message'      => $messageStr,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function getOrCreateSendingRecord(string $type): SendingElectronicDocument
    {
        $flags = match ($type) {
            'is_invoice'                          => ['isInvoice' => true],
            'is_nc'                               => ['isNc' => true],
            'is_nd'                               => ['isNd' => true],
            'is_ds'                               => ['isSupportDocument' => true],
            'is_nds'                              => ['isNds' => true],
            'is_event'                            => ['isEvent' => true],
            default                               => throw new \InvalidArgumentException("Tipo inválido: {$type}"),
        };

        return $this->getOrCreateSendingElectronicTrait(
            $this->document->id,
            $flags['isInvoice']         ?? false,
            $flags['isEvent']           ?? false,
            $flags['isPayroll']         ?? false,
            $flags['isSupportDocument'] ?? false,
            $flags['isNc']              ?? false,
            $flags['isNd']              ?? false,
            $flags['isNds']             ?? false,
        );
    }

    protected function updateDocumentElectronic(string $uuid, $dateTime): void
    {
        $update = ['cufe' => $uuid, 'electronic' => true];

        if ($dateTime) {
            $update['dian_validation_date_time'] = $this->prepareDianDateTime($dateTime);
        }

        $this->document->update($update);
    }

    protected function prepareDianDateTime($dateTime): ?array
    {
        if (! $dateTime) {
            return null;
        }

        try {
            if (is_array($dateTime) && isset($dateTime['date'])) {
                return $dateTime;
            }

            $carbon = is_string($dateTime) ? Carbon::parse($dateTime) : $dateTime;

            return [
                'date'          => $carbon->format('Y-m-d H:i:s.u'),
                'timezone_type' => 1,
                'timezone'      => $carbon->format('P'),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    protected function filterResponse(array $response): array
    {
        if (! isset($response['data'])) {
            return $response;
        }

        $filtered = $response['data'];
        foreach (['invoicexml', 'zipinvoicexml', 'unsignedinvoicexml', 'reqfe', 'rptafe'] as $field) {
            unset($filtered[$field]);
        }

        return ['statusCode' => $response['statusCode'], 'data' => $filtered];
    }

    protected function isRule90Error($error): bool
    {
        if (empty($error)) {
            return false;
        }

        $text = is_array($error) ? json_encode($error) : (string) $error;

        foreach (['Regla: 90', 'Rule: 90', 'procesado anteriormente', 'previously processed', 'duplicado'] as $pattern) {
            if (stripos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isConsumerFinal($third): bool
    {
        $nit = trim($third->identification_number ?? '');

        return in_array($nit, ['222222222222', '22222222', '']) || strlen($nit) < 8;
    }

    protected function extractNitFromResponse(array $data): string
    {
        $xml = $data['ResponseDian']['Envelope']['Body']['SendBillSyncResponse']['SendBillSyncResult']['XmlBase64Bytes'] ?? '';

        if ($xml && base64_encode(base64_decode($xml, true)) === $xml) {
            $xml = base64_decode($xml);
        }

        if (preg_match('/<cbc:CompanyID[^>]*>([^<]+)<\/cbc:CompanyID>/', $xml, $m)) {
            return preg_replace('/[^0-9]/', '', trim($m[1]));
        }

        return '';
    }

    protected function shouldRetry(string $type, ?int $statusCode): bool
    {
        $noRetry = ['VALIDATION_ERROR_422', 'RULE_90_NO_RETRY', 'CLIENT_ERROR', 'INVALID_ENDPOINT'];

        return ! in_array($type, $noRetry) && ! in_array($statusCode, [422]);
    }

    protected function isTrue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'si']);
    }

    protected function isSuccessMessage(string $message): bool
    {
        return stripos($message, 'generada con éxito') !== false
            || stripos($message, 'generated successfully') !== false;
    }

    protected function parseDateTime($dateTime)
    {
        if (! $dateTime) {
            return now();
        }

        try {
            return is_array($dateTime) ? Carbon::parse($dateTime['date'] ?? null) : Carbon::parse($dateTime);
        } catch (Exception $e) {
            return now();
        }
    }

    protected function summarizeError($error): string
    {
        $text = is_array($error) ? ($error['string'][0] ?? json_encode($error)) : (string) $error;

        return substr($text, 0, 120);
    }

    protected function logAttempt(): void
    {
        $this->createDocumentHistory($this->document, null, "Intento No.{$this->currentAttempt} de envío a la DIAN");
    }

    protected function logSuccess(?string $extra = null): void
    {
        $msg = "Intento No.{$this->currentAttempt} EXITOSO";
        $this->createDocumentHistory($this->document, null, $extra ? "$msg — $extra" : $msg);
    }

    protected function logFailure(string $reason): void
    {
        $this->createDocumentHistory($this->document, null, "Intento No.{$this->currentAttempt} FALLIDO — $reason");
    }
}
