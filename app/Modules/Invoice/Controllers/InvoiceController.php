<?php

namespace App\Modules\Invoice\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Modules\Core\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Jobs\ProcessElectronicCreditNoteJob;
use App\Modules\Invoice\Jobs\ProcessElectronicDebitNoteJob;
use App\Modules\Invoice\Jobs\ProcessElectronicInvoiceJob;
use App\Modules\Invoice\Jobs\ProcessElectronicSupportDocumentJob;
use App\Modules\Invoice\Jobs\SendRadianEventJob;
use App\Modules\Invoice\Models\DocumentRadianEvent;
use App\Modules\Invoice\Services\CreditNoteService;
use App\Modules\Invoice\Services\DebitNoteService;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\Tenant\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador de facturación electrónica (CRUD de documentos).
 * Solo recibe request, delega al InvoiceService y retorna respuesta.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService    $invoiceService,
        private readonly CreditNoteService $creditNoteService,
        private readonly DebitNoteService  $debitNoteService
    ) {}

    /**
     * Lista de documentos con filtros y paginación.
     */
    public function index(Request $request): Response
    {
        $query = Document::with(['thirdParty', 'resolution'])
            ->latest('issue_date');

        // Filtro por texto (número o tercero)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                  ->orWhere('internal_code', 'like', "%{$search}%")
                  ->orWhereHas('thirdParty', function ($q2) use ($search) {
                      $q2->where('name', 'ilike', "%{$search}%")
                         ->orWhere('identification_number', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por tipo de documento
        if ($type = $request->input('type_document_operation_id')) {
            $query->where('type_document_operation_id', $type);
        }

        // Filtro por estado
        if ($request->boolean('annulled')) {
            $query->where('annulled', true);
        } else {
            $query->where('annulled', false);
        }

        if ($status = $request->input('status')) {
            match ($status) {
                'paid'       => $query->where('paid', true),
                'pending'    => $query->where('paid', false),
                'electronic' => $query->where('electronic', true),
                'draft'      => $query->where('electronic', false),
                default      => null,
            };
        }

        // Filtro por rango de fechas
        if ($from = $request->input('date_from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('issue_date', '<=', $to);
        }

        $documents = $query->paginate(25)->withQueryString();

        return Inertia::render('Invoice/Index', [
            'documents'     => $documents,
            'filters'       => $request->only(['search', 'type_document_operation_id', 'status', 'date_from', 'date_to', 'annulled']),
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    /**
     * Formulario de creación de documento.
     */
    public function create(): Response
    {
        return Inertia::render('Invoice/Form', [
            'document'      => null,
            'company'       => Company::first(),
            'resolutions'   => Resolution::where('is_active', true)->get(),
            'thirds'        => ThirdParty::where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'identification_number', 'dv', 'name', 'surname', 'email', 'address']),
            'items'         => Item::where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'internal_code', 'name', 'default_sale_price', 'tax_category_id', 'unit_measure_id']),
            'taxes'         => DB::table('taxes')->get(['id', 'name', 'percent', 'code']),
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    /**
     * Almacena un nuevo documento.
     */
    public function store(Request $request): RedirectResponse
    {
        app(PlanLimitsService::class)->ensureCanCreateInvoice();

        $this->normalizeLegacyInvoicePayload($request);

        if ((int) $request->input('type_document_operation_id') === 91
            && ! $request->filled('document_reference_id')
            && ! $request->filled('reference_id')) {
            return back()->withErrors(['document_reference_id' => 'La nota crédito requiere un documento referenciado.'])->withInput();
        }

        $data = $request->validate([
            'third_party_id'              => 'nullable|uuid|exists:third_parties,id',
            'seller_id'                   => 'nullable|uuid|exists:users,id',
            'type_document_id'            => 'required|integer',
            'type_document_operation_id'  => 'required|integer',
            'resolution_id'               => 'nullable|uuid|exists:resolutions,id',
            'currency_id'                 => 'nullable|integer',
            'payment_forms'               => 'nullable|array',
            'taxes'                       => 'nullable|array',
            'issue_date'                  => 'required|date',
            'cashier_shift'               => 'nullable|string|max:50',
            'lines'                       => 'required|array|min:1',
            'lines.*.item_id'             => 'nullable|uuid|exists:items,id',
            'lines.*.amount'              => 'required|numeric|min:0.0001',
            'lines.*.sale_price'          => 'required|numeric|min:0',
            'lines.*.discount'            => 'nullable|numeric|min:0|max:100',
            'lines.*.cost_value'          => 'nullable|numeric|min:0',
            'lines.*.unit_measure_id'     => 'nullable|integer',
            'lines.*.warehouse_out'       => 'nullable|uuid|exists:warehouses,id',
            'lines.*.warehouse_in'        => 'nullable|uuid|exists:warehouses,id',
            'lines.*.movement_type'       => 'nullable|in:IN,OUT,NONE',
            'lines.*.taxes'               => 'nullable|array',
            'lines.*.taxes.*.percent'     => 'nullable|numeric|min:0',
        ]);

        // Asociar al usuario autenticado
        $data['user_id'] = $request->user()->id;

        $document = $this->invoiceService->create($data);

        return redirect()
            ->route('invoices.show', $document)
            ->with('success', 'Documento creado correctamente.');
    }

    private function normalizeLegacyInvoicePayload(Request $request): void
    {
        $request->merge([
            'type_document_id' => $request->input('type_document_id', 1),
            'issue_date'       => $request->input('issue_date', $request->input('date')),
            'currency_id'      => $request->input('currency_id', 1),
        ]);
    }

    /**
     * Muestra el detalle de un documento.
     */
    public function show(Document $document): Response
    {
        $document->load([
            'lines.item',
            'thirdParty',
            'resolution',
            'creditNotes.lines',
            'reference',
        ]);

        return Inertia::render('Invoice/Show', [
            'document'              => $document,
            'documentTypes'         => $this->documentTypes(),
            'correctionConcepts'    => CreditNoteService::CORRECTION_CONCEPTS,
            'debitNoteConcepts'     => DebitNoteService::CORRECTION_CONCEPTS,
            'taxes'                 => DB::table('taxes')->get(['id', 'name', 'percent']),
        ]);
    }

    /**
     * Emite una Nota Crédito sobre un documento existente.
     */
    public function storeCreditNote(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'correction_concept' => 'required|integer|between:1,5',
            'note'               => 'required|string|max:500',
            'selected_lines'     => 'nullable|array',
            'selected_lines.*.line_id' => 'required|uuid',
            'selected_lines.*.qty'     => 'required|numeric|min:0.001',
        ]);

        $nc = $this->creditNoteService->create(
            original:          $document,
            correctionConcept: (int) $data['correction_concept'],
            note:              $data['note'],
            selectedLines:     $data['selected_lines'] ?? [],
            userId:            $request->user()->id,
        );

        return redirect()
            ->route('invoices.show', $nc)
            ->with('success', "Nota Crédito {$nc->internal_code} emitida correctamente.");
    }

    /**
     * Emite una Nota Débito sobre un documento existente.
     * La ND agrega cargos adicionales (intereses, gastos, ajustes) a la factura original.
     */
    public function storeDebitNote(Request $request, Document $document): RedirectResponse
    {
        $data = $request->validate([
            'correction_concept'          => 'required|integer|between:1,4',
            'note'                        => 'required|string|max:500',
            'lines'                       => 'required|array|min:1',
            'lines.*.description'         => 'required|string|max:300',
            'lines.*.amount'              => 'required|numeric|min:0.001',
            'lines.*.sale_price'          => 'required|numeric|min:0',
            'lines.*.discount'            => 'nullable|numeric|min:0|max:100',
            'lines.*.taxes'               => 'nullable|array',
            'lines.*.taxes.*.percent'     => 'nullable|numeric|min:0',
        ]);

        $nd = $this->debitNoteService->create(
            original:          $document,
            correctionConcept: (int) $data['correction_concept'],
            note:              $data['note'],
            lines:             $data['lines'],
            userId:            $request->user()->id,
        );

        return redirect()
            ->route('invoices.show', $nd)
            ->with('success', "Nota Débito {$nd->internal_code} emitida correctamente.");
    }

    /**
     * Formulario de edición de un documento (solo borradores).
     */
    public function edit(Document $document): Response
    {
        $document->load(['lines.item', 'thirdParty', 'resolution']);

        return Inertia::render('Invoice/Form', [
            'document'      => $document,
            'company'       => Company::first(),
            'resolutions'   => Resolution::where('is_active', true)->get(),
            'thirds'        => ThirdParty::where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'identification_number', 'dv', 'name', 'surname', 'email', 'address']),
            'items'         => Item::where('is_active', true)
                                ->orderBy('name')
                                ->get(['id', 'internal_code', 'name', 'default_sale_price', 'tax_category_id', 'unit_measure_id']),
            'taxes'         => DB::table('taxes')->get(['id', 'name', 'percent', 'code']),
            'documentTypes' => $this->documentTypes(),
        ]);
    }

    /**
     * Actualiza un documento existente (solo borradores no enviados a DIAN).
     */
    public function update(Request $request, Document $document): RedirectResponse
    {
        // Solo se permite editar documentos no enviados a DIAN y no anulados
        if ($document->electronic) {
            return back()->withErrors(['document' => 'No se puede editar un documento ya enviado a la DIAN.']);
        }

        if ($document->annulled) {
            return back()->withErrors(['document' => 'No se puede editar un documento anulado.']);
        }

        $data = $request->validate([
            'third_party_id'              => 'nullable|uuid|exists:third_parties,id',
            'seller_id'                   => 'nullable|uuid|exists:users,id',
            'currency_id'                 => 'nullable|integer',
            'payment_forms'               => 'nullable|array',
            'taxes'                       => 'nullable|array',
            'issue_date'                  => 'required|date',
            'cashier_shift'               => 'nullable|string|max:50',
            'lines'                       => 'required|array|min:1',
            'lines.*.item_id'             => 'nullable|uuid|exists:items,id',
            'lines.*.amount'              => 'required|numeric|min:0.0001',
            'lines.*.sale_price'          => 'required|numeric|min:0',
            'lines.*.discount'            => 'nullable|numeric|min:0|max:100',
            'lines.*.cost_value'          => 'nullable|numeric|min:0',
            'lines.*.unit_measure_id'     => 'nullable|integer',
            'lines.*.warehouse_out'       => 'nullable|uuid|exists:warehouses,id',
            'lines.*.warehouse_in'        => 'nullable|uuid|exists:warehouses,id',
            'lines.*.movement_type'       => 'nullable|in:IN,OUT,NONE',
            'lines.*.taxes'               => 'nullable|array',
            'lines.*.taxes.*.percent'     => 'nullable|numeric|min:0',
        ]);

        $this->invoiceService->update($document, $data);

        return redirect()
            ->route('invoices.show', $document)
            ->with('success', 'Documento actualizado correctamente.');
    }

    /**
     * Elimina (soft-delete) un documento borrador.
     */
    public function destroy(Document $document): RedirectResponse
    {
        if ($document->electronic) {
            return back()->withErrors(['document' => 'No se puede eliminar un documento enviado a la DIAN.']);
        }

        if ($document->annulled) {
            return back()->withErrors(['document' => 'El documento ya está anulado.']);
        }

        $document->delete();

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Documento eliminado correctamente.');
    }

    /**
     * Reintenta el envío electrónico a la DIAN de un documento fallido.
     */
    public function retryDian(Document $document): RedirectResponse
    {
        if ($document->electronic) {
            return back()->withErrors(['dian' => 'El documento ya fue enviado exitosamente a la DIAN.']);
        }

        if ($document->annulled) {
            return back()->withErrors(['dian' => 'No se puede reintentar un documento anulado.']);
        }

        $allowedStatuses = ['failed', 'rejected', 'pending'];
        if (! in_array($document->dian_status, $allowedStatuses)) {
            return back()->withErrors(['dian' => 'El documento no está en un estado que permita reintento.']);
        }

        // Resetear estado a pending para indicar que se pondrá en cola
        $document->update([
            'dian_status'   => 'pending',
            'dian_error'    => null,
        ]);

        // Despachar el job correspondiente según el tipo de documento
        $typeOperation = (int) $document->type_document_operation_id;

        match (true) {
            in_array($typeOperation, [91])         => ProcessElectronicCreditNoteJob::dispatch($document, 1),
            in_array($typeOperation, [92])         => ProcessElectronicDebitNoteJob::dispatch($document, 1),
            in_array($typeOperation, [5, 14])       => ProcessElectronicSupportDocumentJob::dispatch($document, 1),
            default                                => ProcessElectronicInvoiceJob::dispatch($document, 1),
        };

        return back()->with('success', 'Documento encolado para reenvío a la DIAN. Esto puede tardar unos segundos.');
    }

    /**
     * Despacha un evento RADIAN para un documento que tiene CUFE.
     *
     * Eventos soportados:
     *   accuse     (030) → Acuse de recibo       — primer paso obligatorio
     *   received   (032) → Recibo del bien/servicio
     *   acceptance (033) → Aceptación expresa
     *   claim      (031) → Reclamo/rechazo (requiere type_rejection_id)
     */
    public function sendRadianEvent(Request $request, Document $document): RedirectResponse
    {
        $request->validate([
            'event_key'         => 'required|string|in:accuse,claim,received,acceptance',
            'type_rejection_id' => 'nullable|integer|min:1',
        ]);

        if (empty($document->cufe)) {
            return back()->withErrors(['radian' => 'El documento no tiene CUFE. Solo se pueden enviar eventos RADIAN a facturas aprobadas por la DIAN.']);
        }

        if ($document->annulled) {
            return back()->withErrors(['radian' => 'No se pueden enviar eventos RADIAN sobre documentos anulados.']);
        }

        $eventKey = $request->input('event_key');

        // Verificar que no haya un evento exitoso previo del mismo tipo
        $existing = $document->radianEvents()
            ->where('event_key', $eventKey)
            ->where('status', DocumentRadianEvent::STATUS_SENT)
            ->exists();

        if ($existing) {
            return back()->withErrors(['radian' => "El evento «{$eventKey}» ya fue enviado y aprobado por la DIAN para este documento."]);
        }

        SendRadianEventJob::dispatch(
            $document->id,
            $eventKey,
            $request->integer('type_rejection_id') ?: null
        );

        $labels = [
            'accuse'     => 'Acuse de recibo (030)',
            'claim'      => 'Reclamo (031)',
            'received'   => 'Recibo del bien (032)',
            'acceptance' => 'Aceptación expresa (033)',
        ];

        return back()->with('success', "Evento RADIAN «{$labels[$eventKey]}» encolado para envío a la DIAN.");
    }

    // ─── Helpers privados ─────────────────────────────────────────────────

    /**
     * Envía el documento por email al tercero.
     */
    public function sendEmail(Request $request, Document $document): RedirectResponse
    {
        $document->load(['lines.item', 'thirdParty', 'resolution']);

        // Email destino: el del request (manual) o el del tercero
        $email = $request->input('email') ?? $document->thirdParty?->email;

        if (! $email) {
            return back()->withErrors(['email' => 'El tercero no tiene email registrado. Ingresa uno manualmente.']);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'El correo electrónico no es válido.']);
        }

        $company = Company::first();

        Mail::to($email)->queue(new InvoiceMail($document, $company));

        return back()->with('success', "Documento enviado a {$email} correctamente.");
    }

    /**
     * Descarga el PDF de un documento.
     */
    public function downloadPdf(Document $document)
    {
        $document->load(['lines.item', 'thirdParty', 'resolution']);

        $company = Company::first();

        // Nombre del municipio si está disponible
        $municipality = null;
        if ($company?->municipality_id) {
            $municipality = DB::table('municipalities')
                ->where('id', $company->municipality_id)
                ->value('name');
        }

        // Texto de la resolución
        $resolutionText = null;
        if ($document->resolution) {
            $r = $document->resolution;
            $resolutionText = "No. {$r->resolution_number} · {$r->prefix} del {$r->date_from} al {$r->date_to}";
        }

        $docTypeName = collect($this->documentTypes())
            ->firstWhere('id', (string) $document->type_document_id)['name'] ?? 'Documento';

        $pdf = Pdf::loadView('pdf.invoice', [
            'document'       => $document,
            'company'        => (object) array_merge(
                $company ? $company->toArray() : [],
                ['municipality_name' => $municipality]
            ),
            'third'          => $document->thirdParty,
            'lines'          => $document->lines->map(function ($line) {
                $taxes = is_array($line->taxes) ? $line->taxes : json_decode($line->taxes ?? '[]', true);
                return (object) array_merge($line->toArray(), ['taxes' => $taxes]);
            }),
            'resolutionText' => $resolutionText,
            'docTypeName'    => $docTypeName,
        ])->setPaper('letter', 'portrait');

        $filename = ($document->prefix ?? '') . ($document->number ?? 'doc') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Todos los tipos de documento (para filtros y display en Index/Show).
     */
    private function documentTypes(): array
    {
        return [
            ['id' => '1',  'name' => 'Factura de Venta'],
            ['id' => '91', 'name' => 'Nota Crédito'],
            ['id' => '92', 'name' => 'Nota Débito'],
            ['id' => '05', 'name' => 'Documento Soporte'],
            ['id' => '4',  'name' => 'Ticket POS'],
        ];
    }
}
