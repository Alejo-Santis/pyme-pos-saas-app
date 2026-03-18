<?php

namespace App\Modules\Invoice\Observers;

use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Jobs\ProcessElectronicInvoiceJob;
use App\Modules\Invoice\Jobs\ProcessElectronicSupportDocumentJob;
use App\Modules\Invoice\Models\Document;
use Illuminate\Support\Facades\Log;

/**
 * Observer del modelo Document.
 * Dispara automáticamente el envío electrónico a DIAN al crear un documento.
 *
 * Flujo:
 *   Document::create() → Observer::created() → Job → ElectronicDocumentsProcessorService → DIAN
 */
class DocumentCreateObserver
{
    public function created(Document $document): void
    {
        // Si ya tiene CUFE es un documento importado — no reprocesar
        if ($document->electronic && $document->cufe) {
            Log::info('Documento ya es electrónico, omitiendo observer', [
                'document_id' => $document->id,
                'cufe'        => $document->cufe,
            ]);

            return;
        }

        // Verificar que la empresa tenga FE activa antes de disparar cualquier job
        $company = Company::first();
        if (! $company?->electronic_documents) {
            Log::debug('FE no activa en la empresa — omitiendo envío a DIAN', [
                'document_id' => $document->id,
            ]);

            return;
        }

        // type_document_operation_id == 1 → Factura de Venta (FE)
        // Aplica tanto a facturas del módulo Invoice como a ventas POS con FE activada
        if ($document->type_document_operation_id == 1) {
            ProcessElectronicInvoiceJob::dispatch($document, 1);
            Log::info('ProcessElectronicInvoiceJob despachado', ['document_id' => $document->id]);
        }

        // type_document_operation_id == 5 → Documento Soporte
        if ($document->type_document_operation_id == 5) {
            ProcessElectronicSupportDocumentJob::dispatch($document, 1);
            Log::info('ProcessElectronicSupportDocumentJob despachado', ['document_id' => $document->id]);
        }
    }

    public function updated(Document $document): void
    {
        // Reservado para lógica futura (ej. cambio de estado)
    }

    public function deleted(Document $document): void
    {
        // Reservado para cancelar jobs pendientes si es necesario
    }

    public function restored(Document $document): void {}

    public function forceDeleted(Document $document): void {}
}
