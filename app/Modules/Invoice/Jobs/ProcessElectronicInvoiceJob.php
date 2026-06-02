<?php

namespace App\Modules\Invoice\Jobs;

use App\Modules\Audit\Services\NotificationService;
use App\Modules\Invoice\Builders\InvoiceJsonBuilder;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Services\ElectronicDocumentsProcessorService;
use App\Modules\Invoice\Services\ApiNextpymeService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para envío de Facturas Electrónicas de Venta a la DIAN.
 * Disparado por DocumentCreateObserver cuando type_document_operation_id == 1.
 *
 * Reintentos con backoff: 0s → 5s → 30s (máx 3 intentos)
 */
class ProcessElectronicInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout     = 120;
    public int $maxExceptions = 3;

    public function __construct(
        private readonly Document $document,
        private readonly int      $currentAttempt = 1
    ) {
        $this->delay = $this->delayForAttempt($currentAttempt);
    }

    public function handle(InvoiceJsonBuilder $builder, ApiNextpymeService $api): void
    {
        Log::info('Iniciando envío FE a DIAN', [
            'document_id' => $this->document->id,
            'attempt'     => $this->currentAttempt,
        ]);

        try {
            $processor = new ElectronicDocumentsProcessorService(
                $this->document,
                $this->currentAttempt,
                $api
            );

            $result = $processor->process($builder, 'invoice', 'is_invoice');

            if (! $result['success'] && $result['should_retry'] && $this->currentAttempt < $this->maxExceptions) {
                $this->scheduleRetry();
            }
        } catch (Exception $e) {
            Log::error('Error crítico en ProcessElectronicInvoiceJob', [
                'document_id' => $this->document->id,
                'attempt'     => $this->currentAttempt,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job FEV falló definitivamente', [
            'document_id' => $this->document->id,
            'attempts'    => $this->currentAttempt,
            'error'       => $e->getMessage(),
        ]);

        $this->document->update([
            'dian_status'   => 'failed',
            'dian_error'    => substr($e->getMessage(), 0, 250),
            'dian_attempts' => $this->currentAttempt,
        ]);

        NotificationService::dianRejection($this->document, substr($e->getMessage(), 0, 250));
    }

    private function scheduleRetry(): void
    {
        $next = $this->currentAttempt + 1;
        Log::info('Programando reintento FEV', ['document_id' => $this->document->id, 'attempt' => $next]);
        self::dispatch($this->document, $next)->delay(now()->addSeconds($this->delayForAttempt($next)));
    }

    private function delayForAttempt(int $attempt): int
    {
        return match ($attempt) { 1 => 0, 2 => 5, 3 => 30, default => 60 };
    }
}
