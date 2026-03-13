<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Core\Models\Establishment;
use App\Modules\Inventory\Models\Item;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\POS\Models\PosTerminal;
use App\Modules\POS\Models\PosTerminalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador principal del Punto de Venta.
 *
 * Flujo:
 *   1. /pos                  → lista de terminales activas
 *   2. /pos/{terminal}/open  → abrir turno + base de caja
 *   3. /pos/{terminal}       → pantalla de venta (SPA-like)
 *   4. /pos/{terminal}/sale  → procesar venta (crea Document type_document_operation_id=4)
 *   5. /pos/{terminal}/close → cerrar turno de caja
 */
class PosController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    // ── Paso 1: Selección de terminal ─────────────────────────────────────

    public function index(): Response
    {
        $user      = Auth::user();
        $terminals = PosTerminal::with([
            'resolution:id,prefix,from,to,current_number',
            'warehouse:id,name',
            'establishment:id,name',
            'activeTerminalUser.user:id,name',
        ])
        ->where('state', true)
        ->get();

        // Turno activo del usuario actual (si existe)
        $myShift = PosTerminalUser::where('user_id', $user->id)
            ->where('active_shift', true)
            ->with('terminal:id,name,serial_number')
            ->first();

        return Inertia::render('POS/Index', [
            'terminals' => $terminals,
            'myShift'   => $myShift,
        ]);
    }

    // ── Paso 2: Abrir turno ───────────────────────────────────────────────

    public function openShift(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $user = Auth::user();

        // Verificar que no tenga otro turno activo
        $existingShift = PosTerminalUser::where('user_id', $user->id)
            ->where('active_shift', true)
            ->first();

        if ($existingShift && $existingShift->pos_terminal_id !== $terminal->id) {
            return back()->withErrors(['terminal' => 'Ya tienes un turno activo en otra terminal. Ciérralo primero.']);
        }

        // Verificar que la terminal no tenga otro usuario activo
        $terminalBusy = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('active_shift', true)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($terminalBusy) {
            return back()->withErrors(['terminal' => 'Esta terminal ya está siendo usada por otro cajero.']);
        }

        $data = $request->validate([
            'initial_balance' => 'required|numeric|min:0',
        ]);

        // Crear o reactivar turno
        PosTerminalUser::updateOrCreate(
            ['pos_terminal_id' => $terminal->id, 'user_id' => $user->id],
            [
                'initial_balance'     => $data['initial_balance'],
                'current_balance'     => $data['initial_balance'],
                'active_shift'        => true,
                'cashier_session_key' => PosTerminalUser::generateSessionKey(),
                'state'               => true,
            ]
        );

        return redirect()
            ->route('pos.terminal', $terminal)
            ->with('success', "Turno abierto en {$terminal->name}.");
    }

    // ── Paso 3: Pantalla de venta ─────────────────────────────────────────

    public function terminal(PosTerminal $terminal): Response|RedirectResponse
    {
        $user = Auth::user();

        // Verificar que el usuario tenga turno activo en ESTA terminal
        $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('user_id', $user->id)
            ->where('active_shift', true)
            ->first();

        if (! $shift) {
            return redirect()->route('pos.index')
                ->withErrors(['shift' => 'No tienes un turno activo en esta terminal.']);
        }

        $warehouseId = $terminal->warehouse_id;

        $items = Item::with([
            'itemWarehouses' => fn ($q) => $q->where('warehouse_id', $warehouseId)
                ->select('item_id', 'warehouse_id', 'stock', 'average_cost'),
            'taxes',
        ])
        ->where('is_active', true)
        ->select('id', 'internal_code', 'name', 'barcode', 'default_sale_price', 'tax_category_id', 'unit_measure_id')
        ->get();

        $thirds = ThirdParty::where('is_active', true)
            ->select('id', 'identification_number', 'dv', 'name', 'surname', 'business_name', 'email', 'address', 'type_organization_id')
            ->get();

        // Últimas 10 ventas del día en esta terminal
        $recentSales = Document::where('pos_terminal_id', $terminal->id)
            ->whereDate('created_at', today())
            ->with('thirdParty:id,name,identification_number')
            ->orderByDesc('created_at')
            ->limit(10)
            ->select('id', 'internal_code', 'prefix', 'number', 'total', 'paid', 'electronic', 'cufe', 'third_party_id', 'created_at')
            ->get();

        return Inertia::render('POS/Terminal', [
            'terminal'    => $terminal->load(['resolution:id,prefix,from,to,current_number', 'warehouse:id,name', 'establishment:id,name']),
            'shift'       => $shift,
            'items'       => $items,
            'thirds'      => $thirds,
            'recentSales' => $recentSales,
            'taxes'       => DB::table('taxes')->get(['id', 'name', 'percent', 'code']),
            'company'     => Company::first(['id', 'business_name', 'identification_number', 'prices_with_taxes_included']),
        ]);
    }

    // ── Paso 4: Procesar venta POS ────────────────────────────────────────

    public function store(Request $request, PosTerminal $terminal): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        // Validar turno activo
        $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('user_id', $user->id)
            ->where('active_shift', true)
            ->firstOrFail();

        $data = $request->validate([
            'third_party_id'  => 'nullable|uuid|exists:third_parties,id',
            'payment_forms'   => 'required|array|min:1',
            'payment_forms.*.payment_form_id'   => 'required|integer',
            'payment_forms.*.payment_method_id' => 'required|integer',
            'payment_forms.*.value'             => 'required|numeric|min:0',
            'taxes'           => 'nullable|array',
            'note'            => 'nullable|string|max:500',
            'lines'           => 'required|array|min:1',
            'lines.*.item_id'         => 'nullable|uuid|exists:items,id',
            'lines.*.description'     => 'nullable|string|max:255',
            'lines.*.amount'          => 'required|numeric|min:0.0001',
            'lines.*.sale_price'      => 'required|numeric|min:0',
            'lines.*.discount'        => 'nullable|numeric|min:0|max:100',
            'lines.*.cost_value'      => 'nullable|numeric|min:0',
            'lines.*.unit_measure_id' => 'nullable|integer',
            'lines.*.taxes'           => 'nullable|array',
            'lines.*.warehouse_out'   => 'nullable|uuid|exists:warehouses,id',
        ]);

        // Asignar bodega de la terminal a cada línea que no tenga warehouse_out
        foreach ($data['lines'] as &$line) {
            if (empty($line['warehouse_out']) && $terminal->warehouse_id) {
                $line['warehouse_out'] = $terminal->warehouse_id;
                $line['movement_type'] = 'OUT';
            } else {
                $line['movement_type'] = $line['movement_type'] ?? 'NONE';
            }
        }
        unset($line);

        $invoiceData = [
            'company_id'                 => Company::first()?->id,
            'user_id'                    => $user->id,
            'third_party_id'             => $data['third_party_id'] ?? null,
            'type_document_id'           => 4,   // Factura POS (tipo DIAN)
            'type_document_operation_id' => 4,   // POS
            'resolution_id'              => $terminal->resolution_id,
            'prefix'                     => $terminal->resolution?->prefix,
            'payment_forms'              => $data['payment_forms'],
            'taxes'                      => $data['taxes'] ?? [],
            'note'                       => $data['note'] ?? null,
            'issue_date'                 => now()->toDateString(),
            'cashier_shift'              => $shift->cashier_session_key,
            'pos_terminal_id'            => $terminal->id,
            'lines'                      => $data['lines'],
        ];

        $document = $this->invoiceService->create($invoiceData);

        // Marcar como pagado si los pagos cubren el total
        $totalPaid = collect($data['payment_forms'])->sum('value');
        if ($totalPaid >= $document->total) {
            $document->update(['paid' => true]);
        }

        return response()->json([
            'success'       => true,
            'document_id'   => $document->id,
            'internal_code' => $document->internal_code,
            'total'         => $document->total,
            'change'        => max(0, $totalPaid - $document->total),
        ]);
    }

    // ── Paso 5: Cerrar turno ──────────────────────────────────────────────

    public function closeShift(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $user = Auth::user();

        $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('user_id', $user->id)
            ->where('active_shift', true)
            ->first();

        if (! $shift) {
            return back()->withErrors(['shift' => 'No hay turno activo para cerrar.']);
        }

        $shift->update([
            'active_shift' => false,
            'state'        => false,
        ]);

        return redirect()
            ->route('pos.index')
            ->with('success', "Turno cerrado en {$terminal->name}.");
    }

    // ── Gestión de terminales (admin) ─────────────────────────────────────

    public function storeTerminal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'resolution_id'    => 'nullable|uuid|exists:resolutions,id',
            'warehouse_id'     => 'nullable|uuid|exists:warehouses,id',
            'establishment_id' => 'nullable|uuid|exists:establishments,id',
            'location'         => 'nullable|string|max:150',
            'printer_name'     => 'nullable|string|max:100',
        ]);

        $data['serial_number'] = 'POS-' . strtoupper(substr(md5(uniqid()), 0, 8));
        PosTerminal::create($data);

        return back()->with('success', 'Terminal creada correctamente.');
    }

    public function updateTerminal(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'resolution_id'    => 'nullable|uuid|exists:resolutions,id',
            'warehouse_id'     => 'nullable|uuid|exists:warehouses,id',
            'establishment_id' => 'nullable|uuid|exists:establishments,id',
            'location'         => 'nullable|string|max:150',
            'printer_name'     => 'nullable|string|max:100',
            'state'            => 'boolean',
        ]);

        $terminal->update($data);

        return back()->with('success', 'Terminal actualizada.');
    }

    public function destroyTerminal(PosTerminal $terminal): RedirectResponse
    {
        $terminal->delete();

        return back()->with('success', 'Terminal eliminada.');
    }
}
