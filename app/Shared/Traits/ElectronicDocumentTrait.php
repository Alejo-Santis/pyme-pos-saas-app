<?php

namespace App\Shared\Traits;

use App\Modules\Invoice\Models\SendingElectronicDocument;
use Carbon\Carbon;

/**
 * Helpers para gestionar el registro de envíos electrónicos DIAN.
 * Adaptado de xedoc-laravel-svelte/app/Traits/ElectronicDocumentTrait.php
 */
trait ElectronicDocumentTrait
{
    /**
     * Obtiene o crea el registro de envío electrónico para un documento.
     */
    public function getOrCreateSendingElectronicTrait(
        string $documentId,
        bool $isInvoice         = true,
        bool $isEvent           = false,
        bool $isPayroll         = false,
        bool $isSupportDocument = false,
        bool $isNc              = false,
        bool $isNd              = false,
        bool $isNds             = false,
        bool $isEqdocs          = false
    ): SendingElectronicDocument {
        return SendingElectronicDocument::firstOrCreate(
            ['document_id' => $documentId],
            [
                'is_invoice'  => $isInvoice,
                'is_event'    => $isEvent,
                'is_payroll'  => $isPayroll,
                'is_ds'       => $isSupportDocument,
                'is_nc'       => $isNc,
                'is_nd'       => $isNd,
                'is_nds'      => $isNds,
                'is_eqdocs'   => $isEqdocs,
                'attempts'    => 0,
                'status'      => true,
                'created_at'  => Carbon::now(),
            ]
        );
    }
}
