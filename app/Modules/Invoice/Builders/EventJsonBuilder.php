<?php

namespace App\Modules\Invoice\Builders;

use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentRadianEvent;

/**
 * Constructor del JSON para eventos RADIAN enviados a DIAN.
 * Endpoint Nextpyme: POST /ubl2.1/send-event-data
 *
 * Adaptado de xedoc-laravel-svelte/app/Services/Nextpyme/Builders/EventJsonBuilder.php
 *
 * RADIAN permite que el comprador registre eventos sobre una FEV recibida:
 *   030 → Acuse de recibo
 *   031 → Reclamo (requiere type_rejection_id)
 *   032 → Recibo del bien/servicio
 *   033 → Aceptación expresa
 */
class EventJsonBuilder
{
    /**
     * Construye el payload para el endpoint /ubl2.1/send-event-data.
     *
     * @param Document   $document        Documento que recibió la FEV (debe tener CUFE)
     * @param string     $eventKey        Clave interna: 'accuse' | 'claim' | 'received' | 'acceptance'
     * @param int|null   $typeRejectionId Solo para 'claim' (código de rechazo DIAN)
     * @param bool       $sendmail        Si DIAN debe notificar al emisor por email
     */
    public static function fromDocument(
        Document $document,
        string   $eventKey,
        ?int     $typeRejectionId = null,
        bool     $sendmail = true
    ): ?array {
        if (empty($document->cufe)) {
            return null;
        }

        $eventData = DocumentRadianEvent::eventData($eventKey);

        if (! $eventData) {
            return null;
        }

        // El event_id que espera Nextpyme es el ID de la tabla type_events
        // Posiciones fijas: accuse=1, claim=2, received=3, acceptance=4
        $eventIdMap = ['030' => 1, '031' => 2, '032' => 3, '033' => 4];
        $eventId    = $eventIdMap[$eventData['code']] ?? null;

        if (! $eventId) {
            return null;
        }

        return [
            'event_id'             => $eventId,
            'type_rejection_id'    => $typeRejectionId,
            'sendmail'             => $sendmail,
            'document_reference'   => [
                'cufe' => $document->cufe,
            ],
            'resend_consecutive'   => false,
            'allow_cash_documents' => false,
        ];
    }
}
