<?php

namespace App\Modules\Invoice\Jobs;

use App\Modules\Invoice\Builders\SupportDocumentBuilder;
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
 * Job para envío de Documentos Soporte de Compra a la DIAN.
 * Disparado por DocumentCreateObserver cuando type_document_operation_id == 5.
 */
class ProcessElectronicSupportDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout       = 120;
    public int $maxExceptions = 3;

    public function __construct(
        private readonly Document $document,
        private readonly int      $currentAttempt = 1
    ) {
        $this->delay = $this->delayForAttempt($currentAttempt);
    }

    public function handle(SupportDocumentBuilder $builder, ApiNextpymeService $api): void
    {
        Log::info('Enviando Documento Soporte a DIAN', [
            'document_id' => $this->document->id,
            'attempt'     => $this->currentAttempt,
        ]);

        try {
            $processor = new ElectronicDocumentsProcessorService(
                $this->document,
                $this->currentAttempt,
                $api
            );

            $result = $processor->process($builder, 'support_document', 'is_ds');

            if (! $result['success'] && $result['should_retry'] && $this->currentAttempt < $this->maxExceptions) {
                self::dispatch($this->document, $this->currentAttempt + 1)
                    ->delay(now()->addSeconds($this->delayForAttempt($this->currentAttempt + 1)));
            }
        } catch (Exception $e) {
            Log::error('Error en ProcessElectronicSupportDocumentJob', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job DS falló definitivamente', [
            'document_id' => $this->document->id,
            'error'       => $e->getMessage(),
        ]);

        $this->document->update([
            'dian_status'   => 'failed',
            'dian_error'    => substr($e->getMessage(), 0, 250),
            'dian_attempts' => $this->currentAttempt,
        ]);
    }

    private function delayForAttempt(int $attempt): int
    {
        return match ($attempt) { 1 => 0, 2 => 5, 3 => 30, default => 60 };
    }
}
