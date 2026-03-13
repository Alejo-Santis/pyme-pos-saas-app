<?php

namespace App\Modules\Invoice\Jobs;

use App\Modules\Invoice\Builders\CreditNoteJsonBuilder;
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
 * Job para envío de Notas Crédito a la DIAN.
 * Se despacha manualmente desde TransactionController al crear una NC.
 */
class ProcessElectronicCreditNoteJob implements ShouldQueue
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

    public function handle(CreditNoteJsonBuilder $builder, ApiNextpymeService $api): void
    {
        Log::info('Enviando Nota Crédito a DIAN', [
            'document_id' => $this->document->id,
            'attempt'     => $this->currentAttempt,
        ]);

        try {
            $processor = new ElectronicDocumentsProcessorService(
                $this->document,
                $this->currentAttempt,
                $api
            );

            $result = $processor->process($builder, 'credit_note', 'is_nc');

            if (! $result['success'] && $result['should_retry'] && $this->currentAttempt < $this->maxExceptions) {
                self::dispatch($this->document, $this->currentAttempt + 1)
                    ->delay(now()->addSeconds($this->delayForAttempt($this->currentAttempt + 1)));
            }
        } catch (Exception $e) {
            Log::error('Error en ProcessElectronicCreditNoteJob', [
                'document_id' => $this->document->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job NC falló definitivamente', [
            'document_id' => $this->document->id,
            'error'       => $e->getMessage(),
        ]);
    }

    private function delayForAttempt(int $attempt): int
    {
        return match ($attempt) { 1 => 0, 2 => 5, 3 => 30, default => 60 };
    }
}
