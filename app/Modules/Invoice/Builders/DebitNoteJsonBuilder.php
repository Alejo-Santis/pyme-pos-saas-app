<?php

namespace App\Modules\Invoice\Builders;

use App\Modules\Invoice\DTO\Customer;
use App\Modules\Invoice\DTO\InvoiceLines;
use App\Modules\Invoice\DTO\LegalMonetaryTotals;
use App\Modules\Invoice\DTO\PaymentForm;
use App\Modules\Invoice\DTO\Taxes;
use App\Modules\Invoice\Models\Document;
use Carbon\Carbon;

class DebitNoteJsonBuilder
{
    public static function fromDocument(Document $document, bool $sendmail = true): array
    {
        $third = $document->thirdParty;

        $json = [
            'number'            => $document->number,
            'type_document_id'  => $document->type_document_id,
            'prefix'            => $document->prefix,
            'date'              => Carbon::parse($document->issue_date)->format('Y-m-d'),
            'time'              => Carbon::parse($document->issue_date)->format('H:i:s'),
            'notes'             => $document->note ?? null,
            'sendmail'          => $sendmail,
            'sendmailtome'      => false,
            'billing_reference' => [
                'number' => $document->reference?->number ?? null,
                'uuid'   => $document->reference?->cufe ?? null,
                'date'   => $document->reference?->issue_date
                    ? Carbon::parse($document->reference->issue_date)->format('Y-m-d')
                    : null,
            ],
            'discrepancy_response' => [
                'correction_concept_id' => $document->debit_note_discrepancy_id ?? 1,
                'description'           => $document->debit_note_reason ?? 'Cargo adicional',
            ],
            'customer'              => (new Customer($third?->toArray() ?? [], $document->type_document_operation_id))->toArray(),
            'payment_form'          => (new PaymentForm($document->payment_forms ?? []))->toArray(),
            'legal_monetary_totals' => (new LegalMonetaryTotals($document->legal_monetary_totals ?? [], $document->invoice_lines ?? []))->toArray(),
            'debit_note_lines'      => (new InvoiceLines($document->invoice_lines ?? [], $document->type_document_operation_id))->toArray(),
        ];

        if (! empty($document->taxes)) {
            $taxTotals = (new Taxes($document->taxes))->toArray();
            if (! empty($taxTotals)) {
                $json['tax_totals'] = $taxTotals;
            }
        }

        return $json;
    }
}
