<?php

namespace App\Modules\Invoice\Jobs;

use App\Modules\Invoice\Builders\EventJsonBuilder;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentRadianEvent;
use App\Modules\Invoice\Services\ApiNextpymeService;
use App\Shared\Traits\ToolTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envía un evento RADIAN a la DIAN vía Nextpyme.
 *
 * RADIAN permite que el comprador registre eventos sobre una FEV que recibió:
 *   accuse     (030) → Acuse de recibo       — primer paso obligatorio
 *   received   (032) → Recibo del bien       — requiere 030 previo
 *   acceptance (033) → Aceptación expresa    — requiere 030 previo
 *   claim      (031) → Reclamo / rechazo     — requiere 030 previo
 *
 * Adaptado de xedoc-laravel-svelte/app/Jobs/SendSingleRadianEventJob.php
 */
class SendRadianEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ToolTrait;

    public int $timeout  = 120;
    public int $tries    = 2;
    public array $backoff = [30, 60];

    public function __construct(
        private readonly string $documentId,
        private readonly string $eventKey,
        private readonly ?int   $typeRejectionId = null
    ) {}

    public function handle(ApiNextpymeService $api): void
    {
        $document = Document::find($this->documentId);

        if (! $document) {
            Log::warning("SendRadianEventJob: documento no encontrado [{$this->documentId}]");
            return;
        }

        if (empty($document->cufe)) {
            Log::warning("SendRadianEventJob: documento sin CUFE [{$document->id}]");
            return;
        }

        $eventData = DocumentRadianEvent::eventData($this->eventKey);

        if (! $eventData) {
            Log::error("SendRadianEventJob: clave de evento inválida [{$this->eventKey}]");
            return;
        }

        // Obtener o crear el registro de seguimiento
        $record = DocumentRadianEvent::firstOrCreate(
            [
                'document_id' => $document->id,
                'event_code'  => $eventData['code'],
            ],
            [
                'event_key'  => $this->eventKey,
                'event_name' => $eventData['name'],
                'status'     => DocumentRadianEvent::STATUS_PENDING,
                'attempts'   => 0,
            ]
        );

        // Si ya fue enviado con éxito, no repetir
        if ($record->isSent()) {
            Log::debug("SendRadianEventJob: evento {$this->eventKey} ya enviado para doc [{$document->id}]");
            return;
        }

        // Si ya tiene error de validación DIAN (regla de negocio), no reintentar
        if ($record->isFailed() && $this->hasValidationError($record)) {
            Log::debug("SendRadianEventJob: evento {$this->eventKey} con error de validación DIAN. No se reintentará.");
            return;
        }

        $attempt = $this->attempts() ?: 1;

        Log::info("SendRadianEventJob: enviando {$this->eventKey} (intento {$attempt}/{$this->tries})", [
            'document_id' => $document->id,
            'cufe'        => substr($document->cufe, 0, 20) . '...',
        ]);

        // Construir payload
        $payload = EventJsonBuilder::fromDocument($document, $this->eventKey, $this->typeRejectionId);

        if (! $payload) {
            Log::error("SendRadianEventJob: EventJsonBuilder devolvió null para [{$document->id}]");
            return;
        }

        // Enviar a Nextpyme
        $response = $api->makeRequest(
            method:     'POST',
            endpoint:   '/ubl2.1/send-event-data',
            parameters: $payload,
            documentId: $document->id,
            operation:  "send_radian_{$this->eventKey}",
            attempt:    $attempt,
        );

        $record->increment('attempts');

        if ($response['statusCode'] !== 200) {
            // Error de conexión/servidor → el Job reintentará automáticamente
            $record->update([
                'status'       => DocumentRadianEvent::STATUS_FAILED,
                'error_message'=> ['http_error' => $response['statusCode'], 'message' => $response['message'] ?? null],
                'response_api' => $response['data'] ?? [],
            ]);
            throw new \Exception("Error HTTP {$response['statusCode']} en Nextpyme para evento {$this->eventKey}");
        }

        $responseData = $response['data'] ?? [];

        // "Ya se registró" → tratarlo como éxito
        if (! ($responseData['success'] ?? false) && str_contains($responseData['message'] ?? '', 'Ya se registro')) {
            $this->markSuccess($record, $document, $responseData);
            return;
        }

        if (! ($responseData['success'] ?? false)) {
            $this->handleDianError($record, $document, $responseData, $attempt);
            return;
        }

        // Verificar IsValid dentro de la respuesta DIAN
        $isValid = $responseData['ResponseDian']['Envelope']['Body']['SendEventUpdateStatusResponse']
            ['SendEventUpdateStatusResult']['IsValid'] ?? null;

        if ($isValid === 'true' || $isValid === true) {
            $this->markSuccess($record, $document, $responseData);
        } else {
            $this->handleDianError($record, $document, $responseData, $attempt);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("SendRadianEventJob: fallo definitivo [{$this->eventKey}]", [
            'document_id' => $this->documentId,
            'error'       => $exception->getMessage(),
        ]);

        DocumentRadianEvent::where('document_id', $this->documentId)
            ->where('event_key', $this->eventKey)
            ->update([
                'status'        => DocumentRadianEvent::STATUS_FAILED,
                'error_message' => ['message' => 'Fallo definitivo tras reintentos: ' . $exception->getMessage()],
            ]);
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    private function markSuccess(DocumentRadianEvent $record, Document $document, array $responseData): void
    {
        $cude = $responseData['cude'] ?? null;

        $record->update([
            'status'       => DocumentRadianEvent::STATUS_SENT,
            'cude'         => $cude,
            'response_api' => $responseData,
            'sent_at'      => now(),
            'error_message'=> null,
        ]);

        $this->createDocumentHistory(
            $document,
            null,
            "Evento RADIAN enviado: {$record->event_name}" . ($cude ? " | CUDE: {$cude}" : '')
        );

        Log::info("SendRadianEventJob: evento {$this->eventKey} enviado con éxito", [
            'document_id' => $document->id,
            'cude'        => $cude,
        ]);
    }

    private function handleDianError(DocumentRadianEvent $record, Document $document, array $responseData, int $attempt): void
    {
        $errorMessage = $responseData['ResponseDian']['Envelope']['Body']['SendEventUpdateStatusResponse']
            ['SendEventUpdateStatusResult']['ErrorMessage'] ?? null;

        $errors = $errorMessage['string'] ?? [];
        $errors = is_array($errors) ? $errors : [$errors];

        $errorJson   = $this->buildErrorJson($errors);
        $errorSummary = $this->buildErrorSummary($errors);

        $record->update([
            'status'        => DocumentRadianEvent::STATUS_FAILED,
            'error_message' => $errorJson,
            'response_api'  => $responseData,
        ]);

        $this->createDocumentHistory(
            $document,
            null,
            "Rechazo DIAN RADIAN ({$this->eventKey}): {$errorSummary}"
        );

        Log::warning("SendRadianEventJob: rechazo DIAN para {$this->eventKey} (intento {$attempt})", [
            'document_id' => $document->id,
            'errors'      => $errorSummary,
        ]);
    }

    private function hasValidationError(DocumentRadianEvent $record): bool
    {
        if (empty($record->error_message)) {
            return false;
        }

        $errorText = json_encode($record->error_message);

        // Códigos de reglas DIAN que no tienen sentido reintentar
        return (bool) preg_match('/\b(LGC|FAD|FAJ|FAK|ZAA|ZAB|ZBA|AAH|AAI|AAJ)\d+\b/', $errorText);
    }

    private function buildErrorSummary(array $errors): string
    {
        $rules = [];

        foreach ($errors as $text) {
            if (! is_string($text)) {
                continue;
            }
            if (preg_match('/Regla:\s*([A-Z0-9]+),\s*(.+)/', $text, $m)) {
                $rules[] = $m[1] . ': ' . preg_replace('/^Rechazo:\s*/i', '', trim($m[2]));
            } else {
                $rules[] = $text;
            }
        }

        return $rules ? implode(' | ', $rules) : 'Error desconocido';
    }

    private function buildErrorJson(array $errors): array
    {
        $parsed = [];

        foreach ($errors as $text) {
            if (! is_string($text)) {
                continue;
            }
            if (preg_match('/Regla:\s*([A-Z0-9]+),\s*(.+)/', $text, $m)) {
                $parsed[] = [
                    'rule'        => $m[1],
                    'description' => preg_replace('/^Rechazo:\s*/i', '', trim($m[2])),
                ];
            } else {
                $parsed[] = ['message' => $text];
            }
        }

        return ['errors' => $parsed ?: ['Error desconocido']];
    }
}
