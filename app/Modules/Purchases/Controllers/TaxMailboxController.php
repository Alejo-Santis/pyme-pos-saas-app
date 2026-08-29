<?php

namespace App\Modules\Purchases\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchases\Models\TaxMailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buzón tributario: documentos electrónicos recibidos de proveedores.
 *
 * La recepción automática desde la DIAN depende de la integración real con
 * el proveedor tecnológico (Nextpyme) — ver DianProviderInterface, todavía
 * no conectada. Mientras tanto este módulo permite cargar manualmente el XML
 * UBL 2.1 que llega por correo y dejarlo disponible para revisión/descarga;
 * cuando la integración quede lista, el job de sincronización solo tiene que
 * insertar filas en tax_mailboxes y esta pantalla funciona sin cambios.
 */
class TaxMailboxController extends Controller
{
    public function index(Request $request): Response
    {
        $mailbox = TaxMailbox::query()
            ->when($request->search, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('business_name_provider', 'ilike', "%{$s}%")
                          ->orWhere('identification_number_provider', 'ilike', "%{$s}%")
                          ->orWhere('cufe', 'ilike', "%{$s}%");
                });
            })
            ->when($request->status === 'pending', fn ($q) => $q->pending())
            ->when($request->status === 'processed', fn ($q) => $q->processed())
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/TaxMailbox/Index', [
            'mailbox' => $mailbox,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(TaxMailbox $taxMailbox): Response
    {
        return Inertia::render('Purchases/TaxMailbox/Show', [
            'item' => $taxMailbox->load('document'),
        ]);
    }

    /**
     * Carga manual de un XML UBL 2.1 recibido por fuera de la DIAN (ej. correo).
     * Extrae los datos básicos del emisor para mostrarlos en el listado; no
     * genera ningún documento de compra — eso requiere la integración DIAN.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xml|max:5120',
        ]);

        $file = $data['file'];
        $xml  = file_get_contents($file->getRealPath());
        $meta = $this->parseUblMetadata($xml);

        TaxMailbox::create(array_merge($meta, [
            'xml_file_name'           => $file->getClientOriginalName(),
            'subject'                 => $meta['business_name_provider'] ?? $file->getClientOriginalName(),
            'base64_attacheddocument' => [
                'file_name' => $file->getClientOriginalName(),
                'mime'      => 'application/xml',
                'content'   => base64_encode($xml),
            ],
        ]));

        return back()->with('success', 'Documento cargado en el buzón tributario correctamente.');
    }

    public function download(TaxMailbox $taxMailbox): HttpResponse
    {
        $attachment = $taxMailbox->base64_attacheddocument;

        abort_unless($attachment && !empty($attachment['content']), 404, 'El documento no tiene un archivo asociado.');

        return response(base64_decode($attachment['content']), 200, [
            'Content-Type'        => $attachment['mime'] ?? 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.($attachment['file_name'] ?? 'documento.xml').'"',
        ]);
    }

    public function destroy(TaxMailbox $taxMailbox): RedirectResponse
    {
        abort_if($taxMailbox->document_id, 422, 'No se puede eliminar un documento ya procesado.');

        $taxMailbox->delete();

        return back()->with('success', 'Documento eliminado del buzón.');
    }

    /**
     * Extrae identificación del emisor, CUFE, fecha y total de un XML UBL 2.1
     * (factura electrónica de venta). Best-effort: si el XML no trae alguno
     * de estos nodos simplemente queda null, nunca falla la carga por eso.
     */
    private function parseUblMetadata(string $xml): array
    {
        $result = [
            'identification_number_provider' => null,
            'business_name_provider'         => null,
            'cufe'                            => null,
            'date'                            => null,
            'tax_inclusive_amount'            => null,
        ];

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $result;
        }

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        $query = function (string $expr) use ($xpath) {
            $nodes = $xpath->query($expr);

            return ($nodes && $nodes->length > 0) ? trim($nodes->item(0)->nodeValue) : null;
        };

        $result['identification_number_provider'] = $query('//cac:AccountingSupplierParty//cac:PartyTaxScheme/cbc:CompanyID')
            ?? $query('//cac:AccountingSupplierParty//cbc:CompanyID');
        $result['business_name_provider'] = $query('//cac:AccountingSupplierParty//cac:PartyName/cbc:Name')
            ?? $query('//cac:AccountingSupplierParty//cac:PartyLegalEntity/cbc:RegistrationName');
        $result['cufe'] = $query('//cbc:UUID');

        $issueDate = $query('//cbc:IssueDate');
        $issueTime = $query('//cbc:IssueTime');
        if ($issueDate) {
            $result['date'] = trim($issueDate.' '.($issueTime ?? '00:00:00'));
        }

        $result['tax_inclusive_amount'] = $query('//cac:LegalMonetaryTotal/cbc:PayableAmount')
            ?? $query('//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount');

        return array_filter($result, fn ($v) => $v !== null);
    }
}
