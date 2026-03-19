<?php

namespace App\Modules\Invoice\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Services\CreditNoteService;
use App\Modules\Invoice\Services\DebitNoteService;
use App\Modules\Invoice\Services\InvoiceService;
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
        $data = $request->validate([
            'company_id'                  => 'required|uuid|exists:companies,id',
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

    /**
     * Muestra el detalle de un documento.
     */
    public function show(Document $document): Response
    {
        $document->load([
            'lines.item',
            'thirdParty',
            'resolution',
            'company',
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

    // ─── Helpers privados ─────────────────────────────────────────────────

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
