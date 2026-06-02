<?php

namespace App\Console\Commands;

use App\Modules\Invoice\Jobs\ProcessElectronicCreditNoteJob;
use App\Modules\Invoice\Jobs\ProcessElectronicDebitNoteJob;
use App\Modules\Invoice\Jobs\ProcessElectronicInvoiceJob;
use App\Modules\Invoice\Jobs\ProcessElectronicSupportDocumentJob;
use App\Modules\Invoice\Jobs\SendRadianEventJob;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentRadianEvent;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Reintenta masivamente documentos y eventos RADIAN que fallaron en su envío a DIAN.
 *
 * Adaptado de xedoc-laravel-svelte/app/Console/Commands/RetryFailedElectronicInvoices.php
 *
 * Uso:
 *   php artisan dian:retry-failed
 *   php artisan dian:retry-failed --tenant=empresa-abc
 *   php artisan dian:retry-failed --type=radian
 *   php artisan dian:retry-failed --dry-run
 */
class RetryFailedElectronicDocumentsCommand extends Command
{
    protected $signature = 'dian:retry-failed
        {--tenant=    : ID del tenant específico (omitir = todos los tenants)}
        {--type=      : Tipo a reintentar: invoices | radian | all (default: all)}
        {--limit=50   : Máximo de documentos por tenant}
        {--dry-run    : Solo muestra lo que haría, sin despachar jobs}';

    protected $description = 'Reintenta masivamente documentos electrónicos y eventos RADIAN que fallaron en su envío a DIAN';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $type     = $this->option('type') ?? 'all';
        $limit    = (int) ($this->option('limit') ?? 50);
        $dryRun   = $this->option('dry-run');

        if (! in_array($type, ['invoices', 'radian', 'all'])) {
            $this->error("Tipo inválido: {$type}. Use invoices, radian o all.");
            return self::FAILURE;
        }

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No se encontraron tenants.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se despacharán jobs. Solo se mostrará el conteo.');
        }

        $totalDocs   = 0;
        $totalRadian = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $type, $limit, $dryRun, &$totalDocs, &$totalRadian) {
                $this->info("\nTenant: {$tenant->id}");

                if (in_array($type, ['invoices', 'all'])) {
                    $count = $this->retryDocuments($limit, $dryRun);
                    $totalDocs += $count;
                    $this->line("  → Documentos encolados: {$count}");
                }

                if (in_array($type, ['radian', 'all'])) {
                    $count = $this->retryRadianEvents($limit, $dryRun);
                    $totalRadian += $count;
                    $this->line("  → Eventos RADIAN encolados: {$count}");
                }
            });
        }

        $this->newLine();
        $this->info("Totales:");
        $this->line("  Documentos encolados : {$totalDocs}");
        $this->line("  Eventos RADIAN       : {$totalRadian}");

        return self::SUCCESS;
    }

    // ── Documentos electrónicos fallidos ─────────────────────────────────

    private function retryDocuments(int $limit, bool $dryRun): int
    {
        $documents = Document::query()
            ->whereIn('dian_status', ['failed', 'rejected'])
            ->where('electronic', false)
            ->where('annulled', false)
            ->whereNotNull('type_document_operation_id')
            ->limit($limit)
            ->get();

        if ($documents->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($documents as $document) {
            $this->line("    [{$document->dian_status}] {$document->prefix}{$document->number} — {$document->id}");

            if (! $dryRun) {
                $document->update(['dian_status' => 'pending', 'dian_error' => null]);

                $typeOp = (int) $document->type_document_operation_id;

                match (true) {
                    in_array($typeOp, [5, 6])  => ProcessElectronicSupportDocumentJob::dispatch($document, 1),
                    $typeOp === 3              => ProcessElectronicCreditNoteJob::dispatch($document, 1),
                    $typeOp === 4              => ProcessElectronicDebitNoteJob::dispatch($document, 1),
                    default                    => ProcessElectronicInvoiceJob::dispatch($document, 1),
                };
            }

            $count++;
        }

        return $count;
    }

    // ── Eventos RADIAN fallidos ───────────────────────────────────────────

    private function retryRadianEvents(int $limit, bool $dryRun): int
    {
        $events = DocumentRadianEvent::query()
            ->where('status', DocumentRadianEvent::STATUS_FAILED)
            ->where('attempts', '<', 5)
            ->limit($limit)
            ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($events as $event) {
            $this->line("    [RADIAN/{$event->event_key}] doc {$event->document_id}");

            if (! $dryRun) {
                // Solo reintentar si no tiene error de validación DIAN (regla de negocio)
                if ($this->hasValidationError($event)) {
                    $this->line("      ↳ Omitido: error de validación DIAN (no se puede reintentar)");
                    continue;
                }

                SendRadianEventJob::dispatch($event->document_id, $event->event_key);
            }

            $count++;
        }

        return $count;
    }

    private function hasValidationError(DocumentRadianEvent $event): bool
    {
        if (empty($event->error_message)) {
            return false;
        }
        $text = json_encode($event->error_message);
        return (bool) preg_match('/\b(LGC|FAD|FAJ|FAK|ZAA|ZAB|ZBA|AAH|AAI|AAJ)\d+\b/', $text);
    }
}
