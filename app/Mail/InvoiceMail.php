<?php

namespace App\Mail;

use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Models\Document;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Document $document,
        public readonly Company  $company,
    ) {}

    public function envelope(): Envelope
    {
        $docNum  = ($this->document->prefix ?? '') . ($this->document->number ?? '');
        $company = $this->company->trade_name ?? $this->company->business_name ?? 'Empresa';

        return new Envelope(
            subject: "Documento {$docNum} — {$company}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'document' => $this->document,
                'company'  => $this->company,
                'docNum'   => ($this->document->prefix ?? '') . ($this->document->number ?? ''),
            ],
        );
    }

    public function attachments(): array
    {
        // Generar PDF y adjuntarlo
        $municipality = null;
        if ($this->company->municipality_id) {
            $municipality = DB::table('municipalities')
                ->where('id', $this->company->municipality_id)
                ->value('name');
        }

        $docTypes = [
            '1' => 'Factura de Venta', '91' => 'Nota Crédito',
            '92' => 'Nota Débito', '05' => 'Documento Soporte', '4' => 'Ticket POS',
        ];

        $resolutionText = null;
        if ($this->document->resolution) {
            $r = $this->document->resolution;
            $resolutionText = "No. {$r->resolution_number} · {$r->prefix}";
        }

        $pdfContent = Pdf::loadView('pdf.invoice', [
            'document'       => $this->document,
            'company'        => (object) array_merge(
                $this->company->toArray(),
                ['municipality_name' => $municipality]
            ),
            'third'          => $this->document->thirdParty,
            'lines'          => $this->document->lines->map(fn ($l) => (object) array_merge(
                $l->toArray(),
                ['taxes' => is_array($l->taxes) ? $l->taxes : json_decode($l->taxes ?? '[]', true)]
            )),
            'resolutionText' => $resolutionText,
            'docTypeName'    => $docTypes[(string) $this->document->type_document_id] ?? 'Documento',
        ])->setPaper('letter', 'portrait')->output();

        $filename = ($this->document->prefix ?? '') . ($this->document->number ?? 'doc') . '.pdf';

        return [
            Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
