<?php

namespace App\Modules\POS\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingConcept;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
use App\Modules\Audit\Services\AuditService;
use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Cash\Models\CashBox;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Services\PaymentSettlementService;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly InvoiceService $invoiceService,
        private readonly PaymentSettlementService $paymentSettlementService
    ) {}

    // ── Paso 1: Selección de terminal ─────────────────────────────────────

    public function index(): Response
    {
        $user      = Auth::user();
        $terminals = PosTerminal::with([
            'resolution:id,prefix,from,to,current_number',
            'warehouse:id,name',
            'establishment:id,name',
            'cashBox:id,name,internal_code',
            'activeTerminalUser.user:id,name',
        ])
        ->where('state', true)
        ->get(['id', 'name', 'location', 'serial_number', 'resolution_id', 'warehouse_id',
               'establishment_id', 'cash_box_id', 'printer_name', 'printer_ip', 'printer_port',
               'printer_type', 'is_usb', 'state']);

        // Turno activo del usuario actual (si existe)
        $myShift = PosTerminalUser::where('user_id', $user->id)
            ->where('active_shift', true)
            ->with('terminal:id,name,serial_number')
            ->first();

        return Inertia::render('POS/Index', [
            'terminals' => $terminals,
            'myShift'   => $myShift,
            'cashBoxes' => CashBox::where('state', true)->get(['id', 'name', 'internal_code']),
        ]);
    }

    // ── Paso 2: Abrir turno ───────────────────────────────────────────────

    public function openShift(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'initial_balance' => 'required|numeric|min:0',
        ]);

        $result = DB::transaction(function () use ($request, $terminal, $user, $data) {
            // Bloquea la fila de la terminal para serializar la apertura de
            // turno — sin esto, dos clics rápidos o dos pestañas pueden pasar
            // ambas verificaciones antes de que cualquiera cree su turno,
            // dejando dos cajeros con la misma terminal simultáneamente.
            PosTerminal::where('id', $terminal->id)->lockForUpdate()->first();

            // Verificar que no tenga otro turno activo
            $existingShift = PosTerminalUser::where('user_id', $user->id)
                ->where('active_shift', true)
                ->first();

            if ($existingShift && $existingShift->pos_terminal_id !== $terminal->id) {
                $message = 'Ya tienes un turno activo en otra terminal. Ciérralo primero.';
                return ['error' => $message];
            }

            // Verificar que la terminal no tenga otro usuario activo
            $terminalBusy = PosTerminalUser::where('pos_terminal_id', $terminal->id)
                ->where('active_shift', true)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($terminalBusy) {
                return ['error' => 'Esta terminal ya está siendo usada por otro cajero.'];
            }

            // Crear o reactivar turno
            $shift = PosTerminalUser::updateOrCreate(
                ['pos_terminal_id' => $terminal->id, 'user_id' => $user->id],
                [
                    'initial_balance'     => $data['initial_balance'],
                    'current_balance'     => $data['initial_balance'],
                    'final_balance'       => 0,
                    'total_sales'         => 0,
                    'total_cash'          => 0,
                    'total_card'          => 0,
                    'total_transfer'      => 0,
                    'active_shift'        => true,
                    'cashier_session_key' => PosTerminalUser::generateSessionKey(),
                    'shift_opened_at'     => now(),
                    'shift_closed_at'     => null,
                    'state'               => true,
                ]
            );

            AuditService::created($shift, "Turno POS abierto en {$terminal->name}.", 'POS');

            return ['shift' => $shift];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['terminal' => $result['error'], 'shift' => $result['error']]);
        }

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
        ->select('id', 'internal_code', 'name', 'barcode_one', 'default_sale_price', 'tax_category_id', 'unit_measure_id')
        ->get();

        $thirds = ThirdParty::where('is_active', true)
            ->select('id', 'identification_number', 'dv', 'name', 'surname', 'email', 'address', 'type_organization_id')
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
            'bankAccounts' => BankAccount::with('bank:id,name')
                ->where('state', true)
                ->orderBy('name')
                ->get(['id', 'bank_id', 'name', 'type', 'account_bank_number']),
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
            'payment_forms.*.cash_box_id'       => 'nullable|uuid|exists:cash_boxes,id',
            'payment_forms.*.bank_account_id'   => 'nullable|uuid|exists:bank_accounts,id',
            'payment_forms.*.transaction_reference' => 'nullable|string|max:100',
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

        $company = Company::first();

        // Determinar tipo de operación:
        //   op_id = 1  → Factura Electrónica (FE DIAN) — cuando la empresa tiene FE activa
        //   op_id = 4  → Documento interno POS — cuando la empresa aún no tiene FE
        $feActive = $company?->electronic_documents && $terminal->resolution_id;
        $typeDocumentId          = $feActive ? 1 : 4;   // 1=FE, 4=POS ticket
        $typeDocumentOperationId = $feActive ? 1 : 4;   // 1=Venta → Observer → DIAN

        $invoiceData = [
            'user_id'                    => $user->id,
            'third_party_id'             => $data['third_party_id'] ?? null,
            'type_document_id'           => $typeDocumentId,
            'type_document_operation_id' => $typeDocumentOperationId,
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

        $totalPaid = collect($data['payment_forms'])->sum('value');

        // Marcar como pagado si los pagos cubren el total
        if ($totalPaid >= $document->total) {
            $document->update(['paid' => true]);
        }

        // ── Registrar movimientos de caja / banco ─────────────────────────
        $this->paymentSettlementService->registerSalePayments($terminal, $shift, $document, $data['payment_forms']);

        AuditService::created($document, "Venta POS {$document->internal_code} registrada en {$terminal->name}.", 'POS');

        return response()->json([
            'success'       => true,
            'document_id'   => $document->id,
            'internal_code' => $document->internal_code,
            'total'         => $document->total,
            'change'        => max(0, $totalPaid - $document->total),
        ]);
    }

    // ── Paso 5a: Resumen del turno (para modal de cuadre) ─────────────────

    /**
     * Retorna los totales en vivo del turno activo para el modal de cuadre.
     * GET /pos/{terminal}/shift-summary → JSON
     */
    public function shiftSummary(Request $request, PosTerminal $terminal): JsonResponse
    {
        $user  = Auth::user();
        $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('user_id', $user->id)
            ->where('active_shift', true)
            ->first();

        if (! $shift) {
            return response()->json(['error' => 'No hay turno activo.'], 404);
        }

        $salesData = Document::where('pos_terminal_id', $terminal->id)
            ->where('cashier_shift', $shift->cashier_session_key)
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales, COUNT(*) as sales_count')
            ->first();

        $cashTotal = CashMovement::where('cash_box_id', $terminal->cash_box_id)
            ->where('reference', $shift->cashier_session_key)
            ->where('state', true)
            ->sum('debit');

        $bankTotal = BankAccountMovement::whereHas(
            'bankAccount',
            fn ($q) => $q->where('state', true)
        )
            ->where('reference', $shift->cashier_session_key)
            ->where('state', true)
            ->sum('debit');

        return response()->json([
            'initial_balance' => (float) $shift->initial_balance,
            'total_sales'     => (float) ($salesData->total_sales ?? 0),
            'sales_count'     => (int)   ($salesData->sales_count ?? 0),
            'total_cash'      => (float) $cashTotal,
            'total_transfer'  => (float) $bankTotal,
            'expected_cash'   => (float) $shift->initial_balance + $cashTotal,
            'opened_at'       => $shift->shift_opened_at?->format('d/m/Y H:i'),
            'session_key'     => $shift->cashier_session_key,
        ]);
    }

    // ── Paso 5b: Cerrar turno con cuadre de caja ──────────────────────────
    // Retorna JSON para que el cliente maneje el toast y haga partial reload
    // sin que Inertia haga un swap completo de componente (evita "recarga").

    public function closeShift(Request $request, PosTerminal $terminal): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'close_notes'  => 'nullable|string|max:500',
        ]);

        $user  = Auth::user();
        $shift = PosTerminalUser::where('pos_terminal_id', $terminal->id)
            ->where('user_id', $user->id)
            ->where('active_shift', true)
            ->first();

        if (! $shift) {
            return response()->json(['success' => false, 'message' => 'No hay turno activo para cerrar.'], 422);
        }

        // Calcular totales del sistema
        $salesData = Document::where('pos_terminal_id', $terminal->id)
            ->where('cashier_shift', $shift->cashier_session_key)
            ->selectRaw('COALESCE(SUM(total), 0) as total_sales, COUNT(*) as sales_count')
            ->first();

        $cashTotal = CashMovement::where('cash_box_id', $terminal->cash_box_id)
            ->where('reference', $shift->cashier_session_key)
            ->where('state', true)
            ->sum('debit');

        $bankTotal = BankAccountMovement::whereHas(
            'bankAccount',
            fn ($q) => $q->where('state', true)
        )
            ->where('reference', $shift->cashier_session_key)
            ->where('state', true)
            ->sum('debit');

        $expectedCash = (float) $shift->initial_balance + (float) $cashTotal;
        $countedCash  = (float) $data['counted_cash'];
        $difference   = $countedCash - $expectedCash;  // + sobrante, - faltante

        $original = $shift->getOriginal();
        $closingAccounting = null;

        DB::transaction(function () use (
            $shift,
            $terminal,
            $salesData,
            $cashTotal,
            $bankTotal,
            $expectedCash,
            $countedCash,
            $difference,
            $data,
            $user,
            &$closingAccounting
        ) {
            $cashMovement = $this->createPosClosingCashMovement($terminal, $shift, $user->id, $difference);
            $closingAccounting = $this->createPosClosingAccounting($terminal, $shift, $user->id, $difference);

            $shift->update([
                'active_shift'                  => false,
                'state'                         => false,
                'total_sales'                   => $salesData->total_sales ?? 0,
                'total_cash'                    => $cashTotal,
                'total_transfer'                => $bankTotal,
                'final_balance'                 => $countedCash,
                'counted_cash'                  => $countedCash,
                'difference'                    => $difference,
                'close_notes'                   => $data['close_notes'] ?? null,
                'closing_cash_movement_id'      => $cashMovement?->id,
                'closing_accounting_document_id'=> $closingAccounting?->id,
                'shift_closed_at'               => now(),
            ]);
        });

        AuditService::updated($shift->refresh(), $original, "Turno POS cerrado en {$terminal->name}.", 'POS');

        $diffLabel = $difference >= 0
            ? 'Sobrante $' . number_format($difference, 0, ',', '.')
            : 'Faltante $' . number_format(abs($difference), 0, ',', '.');

        return response()->json([
            'success'     => true,
            'total_sales' => (float) ($salesData->total_sales ?? 0),
            'difference'  => $difference,
            'accounting_document_id' => $closingAccounting?->id,
            'message'     => "Turno cerrado en {$terminal->name} · Ventas: $" . number_format((float)($salesData->total_sales ?? 0), 0, ',', '.') . " · {$diffLabel}",
        ]);
    }

    private function createPosClosingCashMovement(
        PosTerminal $terminal,
        PosTerminalUser $shift,
        string $userId,
        float $difference
    ): ?CashMovement {
        if (abs($difference) < 0.01) {
            return null;
        }

        $cashBoxId = $terminal->cash_box_id ?? CashBox::getMain()?->id;
        if (! $cashBoxId) {
            return null;
        }

        $isSurplus = $difference > 0;

        return CashMovement::create([
            'cash_box_id' => $cashBoxId,
            'user_id' => $userId,
            'debit' => $isSurplus ? abs($difference) : 0,
            'credit' => $isSurplus ? 0 : abs($difference),
            'issue_date' => now()->toDateString(),
            'description' => ($isSurplus ? 'Sobrante' : 'Faltante') . " cierre POS {$shift->cashier_session_key}",
            'reference' => $shift->cashier_session_key,
            'state' => true,
        ]);
    }

    private function createPosClosingAccounting(
        PosTerminal $terminal,
        PosTerminalUser $shift,
        string $userId,
        float $difference
    ): ?AccountingDocument {
        if (abs($difference) < 0.01) {
            return null;
        }

        $amount = round(abs($difference), 4);
        $isSurplus = $difference > 0;
        $cashAccount = $this->posClosingAccount('CAJA');
        $counterpartAccount = $this->posClosingAccount($isSurplus ? 'SOBRANTE' : 'FALTANTE');

        $voucher = AccountingDocument::create([
            'uuid' => Str::uuid(),
            'internal_code' => 'COMP-CIERRE-POS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
            'user_id' => $userId,
            'type_document_operation_id' => 97,
            'prefix' => 'CPOS',
            'debit' => $amount,
            'credit' => $amount,
            'total' => $amount,
            'notes' => ($isSurplus ? 'Sobrante' : 'Faltante') . " de cierre POS {$shift->cashier_session_key} en {$terminal->name}.",
            'issue_date' => now()->toDateString(),
            'annulled' => false,
        ]);

        if ($isSurplus) {
            $this->createPosClosingAccountingLine($voucher, $cashAccount, $amount, 0, $shift->cashier_session_key);
            $this->createPosClosingAccountingLine($voucher, $counterpartAccount, 0, $amount, $shift->cashier_session_key);
        } else {
            $this->createPosClosingAccountingLine($voucher, $counterpartAccount, $amount, 0, $shift->cashier_session_key);
            $this->createPosClosingAccountingLine($voucher, $cashAccount, 0, $amount, $shift->cashier_session_key);
        }

        return $voucher;
    }

    private function createPosClosingAccountingLine(
        AccountingDocument $voucher,
        string $accountCode,
        float $debit,
        float $credit,
        string $sessionKey
    ): void {
        AccountingDocumentDetail::create([
            'accounting_document_id' => $voucher->id,
            'accountable_id' => $accountCode,
            'accountable_type' => 'chart_account',
            'document_number' => $sessionKey,
            'taxable_amount' => max($debit, $credit),
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'issue_date' => $voucher->issue_date,
        ]);
    }

    private function posClosingAccount(string $slug): string
    {
        $account = AccountingConcept::getAccountCode(97, "CIERRE_POS_{$slug}");
        if ($account) {
            return $account;
        }

        return match ($slug) {
            'SOBRANTE' => '42959501',
            'FALTANTE' => '51959501',
            default => '11050501',
        };
    }

    // ── Gestión de terminales (admin) ─────────────────────────────────────

    public function storeTerminal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'resolution_id'    => 'nullable|uuid|exists:resolutions,id',
            'warehouse_id'     => 'nullable|uuid|exists:warehouses,id',
            'establishment_id' => 'nullable|uuid|exists:establishments,id',
            'cash_box_id'      => 'nullable|uuid|exists:cash_boxes,id',
            'location'         => 'nullable|string|max:150',
            // Configuración de impresora
            'printer_name'     => 'nullable|string|max:150',
            'printer_ip'       => 'nullable|ip',
            'printer_port'     => 'nullable|integer|min:1|max:65535',
            'printer_type'     => 'nullable|string|in:escpos,star,80mm',
            'is_usb'           => 'boolean',
        ]);

        $data['serial_number'] = 'POS-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $terminal = PosTerminal::create($data);

        AuditService::created($terminal, "Terminal POS {$terminal->name} creada.", 'POS');

        return back()->with('success', 'Terminal creada correctamente.');
    }

    public function updateTerminal(Request $request, PosTerminal $terminal): RedirectResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'resolution_id'    => 'nullable|uuid|exists:resolutions,id',
            'warehouse_id'     => 'nullable|uuid|exists:warehouses,id',
            'establishment_id' => 'nullable|uuid|exists:establishments,id',
            'cash_box_id'      => 'nullable|uuid|exists:cash_boxes,id',
            'location'         => 'nullable|string|max:150',
            // Configuración de impresora
            'printer_name'     => 'nullable|string|max:150',
            'printer_ip'       => 'nullable|ip',
            'printer_port'     => 'nullable|integer|min:1|max:65535',
            'printer_type'     => 'nullable|string|in:escpos,star,80mm',
            'is_usb'           => 'boolean',
            'state'            => 'boolean',
        ]);

        $original = $terminal->getOriginal();

        $terminal->update($data);

        AuditService::updated($terminal, $original, "Terminal POS {$terminal->name} actualizada.", 'POS');

        return back()->with('success', 'Terminal actualizada.');
    }

    public function destroyTerminal(PosTerminal $terminal): RedirectResponse
    {
        AuditService::deleted($terminal, "Terminal POS {$terminal->name} eliminada.", 'POS');

        $terminal->delete();

        return back()->with('success', 'Terminal eliminada.');
    }

}
