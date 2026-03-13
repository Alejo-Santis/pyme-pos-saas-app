<?php

namespace App\Modules\Invoice\Builders;

use App\Modules\Invoice\DTO\Customer;
use App\Modules\Invoice\DTO\InvoiceLines;
use App\Modules\Invoice\DTO\LegalMonetaryTotals;
use App\Modules\Invoice\DTO\PaymentForm;
use App\Modules\Invoice\DTO\Taxes;
use App\Modules\Invoice\Models\Document;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Constructor del JSON UBL 2.1 para Facturas de Venta.
 * Adaptado de xedoc-laravel-svelte/app/Services/Nextpyme/Builders/InvoiceJsonBuilder.php
 */
class InvoiceJsonBuilder
{
    /**
     * Construye el payload completo para el endpoint /ubl2.1/invoice de Nextpyme.
     *
     * @param Document $document  Documento con relaciones cargadas (thirdParty, resolution, lines)
     * @param bool     $sendmail  Si debe enviarse email al cliente
     */
    public static function fromDocument(Document $document, bool $sendmail = true): array
    {
        // Consumidor final (222...) no recibe email
        $third = $document->thirdParty;
        if ($third && ($third->identification_number ?? '') === '222222222222') {
            $sendmail = false;
        }

        $json = [
            'number'            => $document->number,
            'type_document_id'  => $document->type_document_id,
            'prefix'            => $document->prefix,
            'resolution_number' => $document->resolution?->resolution_number ?? $document->resolution?->resolution,
            'date'              => Carbon::parse($document->issue_date)->format('Y-m-d'),
            'time'              => Carbon::parse($document->issue_date)->format('H:i:s'),
            'notes'             => $document->note ?? null,
            'sendmail'          => $sendmail,
            'sendmailtome'      => false,
            'foot_note'         => 'Generado por ' . config('app.name') . ' | Software Propio',
            'customer'          => (new Customer(
                $third?->toArray() ?? self::consumerFinal(),
                $document->type_document_operation_id
            ))->toArray(),
            'payment_form'      => (new PaymentForm(
                $document->payment_forms ?? []
            ))->toArray(),
            'legal_monetary_totals' => (new LegalMonetaryTotals(
                $document->legal_monetary_totals ?? [],
                $document->invoice_lines         ?? []
            ))->toArray(),
            'invoice_lines'     => (new InvoiceLines(
                $document->invoice_lines ?? [],
                $document->type_document_operation_id
            ))->toArray(),
        ];

        // Usar la fecha actual si el documento es de días anteriores
        if (! empty($document->issue_date) && ! Carbon::parse($document->issue_date)->isToday()) {
            $json['date'] = now()->format('Y-m-d');
        }

        // Impuestos globales
        if (! empty($document->taxes)) {
            $taxTotals = (new Taxes($document->taxes))->toArray();
            if (! empty($taxTotals)) {
                $json['tax_totals'] = $taxTotals;
            }
        }

        // Retenciones
        if (! empty($document->withholdings_tax)) {
            $json['with_holding_tax_total'] = $document->withholdings_tax;
        }

        // Referencia de orden de compra
        if (! empty($document->order_reference['id_order'] ?? null)) {
            $json['order_reference'] = $document->order_reference;
        }

        Log::debug('InvoiceJsonBuilder: JSON construido', [
            'document_id' => $document->id,
            'lines'       => count($json['invoice_lines']),
        ]);

        return $json;
    }

    /**
     * Datos de consumidor final DIAN.
     */
    private static function consumerFinal(): array
    {
        return [
            'identification_number'           => '222222222222',
            'dv'                              => 2,
            'name'                            => 'Consumidor Final',
            'type_document_identification_id' => 13,  // 13 = Sin identificación
            'type_organization_id'            => 1,
            'type_regime_id'                  => 2,
            'type_liability_id'               => 14,
            'municipality_id'                 => 149,  // 149 = Bogotá
            'address'                         => 'Sin dirección',
        ];
    }
}
