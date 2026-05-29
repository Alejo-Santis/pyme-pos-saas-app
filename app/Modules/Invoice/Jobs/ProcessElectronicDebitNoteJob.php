<?php

namespace App\Modules\Invoice\Jobs;

use App\Modules\Invoice\Builders\DebitNoteJsonBuilder;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Services\ApiNextpymeService;
use App\Modules\Invoice\Services\ElectronicDocumentsProcessorService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessElectronicDebitNoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $maxExceptions = 3;

    public function __construct(
        private readonly Document $document,
        private readonly int $currentAttempt = 1
    ) {
        $this->delay = $this->delayForAttempt($currentAttempt);
    }

    public function handle(DebitNoteJsonBuilder $builder, ApiNextpymeService $api): void
    {
        Log::info('Enviando Nota Débito a DIAN', [
            'document_id' => $this->document->id,
            'attempt' => $this->currentAttempt,
        ]);

        try {
            $processor = new ElectronicDocumentsProcessorService(
                $this->document,
                $this->currentAttempt,
                $api
            );

            $result = $processor->process($builder, 'debit_note', 'is_nd');

            if (! $result['success'] && $result['should_retry'] && $this->currentAttempt < $this->maxExceptions) {
                self::dispatch($this->document, $this->currentAttempt + 1)
                    ->delay(now()->addSeconds($this->delayForAttempt($this->currentAttempt + 1)));
            }
        } catch (Exception $e) {
            Log::error('Error en ProcessElectronicDebitNoteJob', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Job ND falló definitivamente', [
            'document_id' => $this->document->id,
            'error' => $e->getMessage(),
        ]);

        $this->document->update([
            'dian_status' => 'failed',
            'dian_error' => substr($e->getMessage(), 0, 250),
            'dian_attempts' => $this->currentAttempt,
        ]);
    }

    private function delayForAttempt(int $attempt): int
    {
        return match ($attempt) { 1 => 0, 2 => 5, 3 => 30, default => 60 };
    }
}
