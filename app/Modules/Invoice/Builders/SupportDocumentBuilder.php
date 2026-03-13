<?php

namespace App\Modules\Invoice\Builders;

use App\Modules\Invoice\DTO\Customer;
use App\Modules\Invoice\DTO\InvoiceLines;
use App\Modules\Invoice\DTO\LegalMonetaryTotals;
use App\Modules\Invoice\DTO\PaymentForm;
use App\Modules\Invoice\DTO\Taxes;
use App\Modules\Invoice\Models\Document;
use Carbon\Carbon;

/**
 * Constructor del JSON UBL 2.1 para Documentos Soporte de Compra (DS).
 * type_document_operation_id == 5
 */
class SupportDocumentBuilder
{
    public static function fromDocument(Document $document, bool $sendmail = true): array
    {
        $third = $document->thirdParty;

        $json = [
            'number'            => $document->number,
            'type_document_id'  => $document->type_document_id ?? 5,
            'prefix'            => $document->prefix,
            'date'              => Carbon::parse($document->issue_date)->format('Y-m-d'),
            'time'              => Carbon::parse($document->issue_date)->format('H:i:s'),
            'notes'             => $document->note ?? null,
            'sendmail'          => false,   // DS no envía email
            'sendmailtome'      => false,

            // En DS el "customer" es el proveedor
            'customer'              => (new Customer($third?->toArray() ?? [], 5))->toArray(),
            'payment_form'          => (new PaymentForm($document->payment_forms ?? []))->toArray(),
            'legal_monetary_totals' => (new LegalMonetaryTotals($document->legal_monetary_totals ?? [], $document->invoice_lines ?? []))->toArray(),
            'invoice_lines'         => (new InvoiceLines($document->invoice_lines ?? [], 5))->toArray(),
        ];

        if (! empty($document->taxes)) {
            $taxTotals = (new Taxes($document->taxes))->toArray();
            if (! empty($taxTotals)) {
                $json['tax_totals'] = $taxTotals;
            }
        }

        if (! empty($document->withholdings_tax)) {
            $json['with_holding_tax_total'] = $document->withholdings_tax;
        }

        return $json;
    }
}
