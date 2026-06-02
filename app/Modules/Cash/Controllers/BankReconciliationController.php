<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Cash\Models\BankReconciliation;
use App\Modules\Cash\Models\BankReconciliationLine;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    /** Lista conciliaciones por cuenta bancaria. */
    public function index(Request $request): Response
    {
        $accountId = $request->input('account_id');

        $accounts = BankAccount::with('bank')
            ->where('state', true)
            ->orderBy('account_number')
            ->get(['id', 'account_number', 'bank_id']);

        $reconciliations = BankReconciliation::with('bankAccount.bank')
            ->when($accountId, fn ($q) => $q->where('bank_account_id', $accountId))
            ->orderByDesc('period')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Cash/Reconciliations/Index', [
            'accounts'        => $accounts,
            'reconciliations' => $reconciliations,
            'filters'         => ['account_id' => $accountId],
        ]);
    }

    /** Crea o abre una conciliación para un período y cuenta. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_account_id'  => 'required|uuid|exists:bank_accounts,id',
            'period'           => 'required|date_format:Y-m',
            'statement_date'   => 'required|date',
            'statement_balance'=> 'required|numeric',
            'notes'            => 'nullable|string|max:500',
        ]);

        // Calcular saldo en libros al fin del período
        $periodEnd = Carbon::parse($data['period'] . '-01')->endOfMonth()->toDateString();

        $bookBalance = BankAccountMovement::where('bank_account_id', $data['bank_account_id'])
            ->where('movement_date', '<=', $periodEnd)
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN amount ELSE -amount END), 0) as balance")
            ->value('balance') ?? 0;

        $reconciliation = BankReconciliation::firstOrCreate(
            ['bank_account_id' => $data['bank_account_id'], 'period' => $data['period']],
            [
                'statement_date'    => $data['statement_date'],
                'statement_balance' => $data['statement_balance'],
                'book_balance'      => $bookBalance,
                'difference'        => round((float) $data['statement_balance'] - (float) $bookBalance, 4),
                'status'            => BankReconciliation::STATUS_OPEN,
                'notes'             => $data['notes'] ?? null,
            ]
        );

        // Cargar automáticamente los movimientos de libros del período
        $this->loadBookMovements($reconciliation);

        return redirect()->route('cash.reconciliations.show', $reconciliation->id)
            ->with('success', "Conciliación {$data['period']} creada.");
    }

    /** Detalle de una conciliación con sus líneas. */
    public function show(BankReconciliation $reconciliation): Response
    {
        $reconciliation->load(['bankAccount.bank', 'lines' => fn ($q) => $q->orderBy('movement_date')]);

        $bookLines      = $reconciliation->lines->where('source', 'book')->values();
        $statementLines = $reconciliation->lines->where('source', 'statement')->values();

        $matchedBook      = $bookLines->where('matched', true)->sum('amount');
        $unmatchedBook    = $bookLines->where('matched', false)->sum('amount');
        $matchedStatement = $statementLines->where('matched', true)->sum('amount');

        return Inertia::render('Cash/Reconciliations/Show', [
            'reconciliation'  => $reconciliation,
            'bookLines'       => $bookLines,
            'statementLines'  => $statementLines,
            'summary' => [
                'statement_balance' => (float) $reconciliation->statement_balance,
                'book_balance'      => (float) $reconciliation->book_balance,
                'difference'        => (float) $reconciliation->difference,
                'matched_book'      => (float) $matchedBook,
                'unmatched_book'    => (float) $unmatchedBook,
                'matched_statement' => (float) $matchedStatement,
            ],
        ]);
    }

    /** Agrega una línea manual del extracto bancario. */
    public function addStatementLine(Request $request, BankReconciliation $reconciliation): RedirectResponse
    {
        $data = $request->validate([
            'movement_date' => 'required|date',
            'description'   => 'required|string|max:200',
            'amount'        => 'required|numeric',
        ]);

        BankReconciliationLine::create([
            'reconciliation_id' => $reconciliation->id,
            'movement_date'     => $data['movement_date'],
            'description'       => $data['description'],
            'amount'            => $data['amount'],
            'source'            => 'statement',
            'matched'           => false,
        ]);

        // Recalcular diferencia
        $this->recalculate($reconciliation);

        return back()->with('success', 'Movimiento del extracto agregado.');
    }

    /** Marca/desmarca una línea como conciliada. */
    public function toggleMatch(BankReconciliationLine $line): RedirectResponse
    {
        $line->update(['matched' => ! $line->matched]);
        $this->recalculate($line->reconciliation);
        return back()->with('success', 'Línea actualizada.');
    }

    /** Marca la conciliación como reconciliada (cerrada). */
    public function reconcile(BankReconciliation $reconciliation): RedirectResponse
    {
        if (abs((float) $reconciliation->difference) > 0.01) {
            return back()->withErrors(['reconcile' => 'No se puede cerrar la conciliación con diferencia mayor a $0.01.']);
        }

        $reconciliation->update([
            'status'          => BankReconciliation::STATUS_RECONCILED,
            'reconciled_by'   => auth()->id(),
            'reconciled_at'   => now(),
        ]);

        return back()->with('success', 'Conciliación cerrada exitosamente.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function loadBookMovements(BankReconciliation $reconciliation): void
    {
        $periodStart = Carbon::parse($reconciliation->period . '-01')->startOfMonth()->toDateString();
        $periodEnd   = Carbon::parse($reconciliation->period . '-01')->endOfMonth()->toDateString();

        $movements = BankAccountMovement::where('bank_account_id', $reconciliation->bank_account_id)
            ->whereBetween('movement_date', [$periodStart, $periodEnd])
            ->get();

        foreach ($movements as $mov) {
            BankReconciliationLine::firstOrCreate(
                ['reconciliation_id' => $reconciliation->id, 'bank_account_movement_id' => $mov->id],
                [
                    'movement_date' => $mov->movement_date,
                    'description'   => $mov->description ?? 'Movimiento bancario',
                    'amount'        => $mov->movement_type === 'IN' ? $mov->amount : -$mov->amount,
                    'source'        => 'book',
                    'matched'       => false,
                ]
            );
        }
    }

    private function recalculate(BankReconciliation $reconciliation): void
    {
        $reconciliation->refresh();
        $statementTotal = $reconciliation->lines->where('source', 'statement')->sum('amount');
        $diff           = round((float) $reconciliation->statement_balance - (float) $reconciliation->book_balance, 4);
        $reconciliation->update(['difference' => $diff]);
    }
}
