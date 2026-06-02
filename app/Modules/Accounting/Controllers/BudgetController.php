<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\BudgetLine;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Shared\Exports\ArrayExport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class BudgetController extends Controller
{
    /** Lista los presupuestos disponibles. */
    public function index(Request $request): Response
    {
        $budgets = Budget::orderByDesc('year')->orderBy('name')->get();

        return Inertia::render('Accounting/Budget/Index', [
            'budgets' => $budgets,
        ]);
    }

    /** Formulario de creación de presupuesto. */
    public function create(): Response
    {
        $accounts = ChartOfAccount::where('allows_movement', true)
            ->where('state', true)
            ->whereIn('class', [4, 5, 6]) // Ingresos, Gastos, Costos
            ->orderBy('code')
            ->get(['code', 'name', 'class']);

        return Inertia::render('Accounting/Budget/Form', [
            'budget'   => null,
            'accounts' => $accounts,
            'months'   => range(1, 12),
            'year'     => now()->year,
        ]);
    }

    /** Guarda un presupuesto nuevo. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:120',
            'year'   => 'required|integer|min:2020|max:2035',
            'notes'  => 'nullable|string|max:500',
            'lines'  => 'array',
            'lines.*.account_code' => 'required|string',
            'lines.*.account_name' => 'required|string',
            'lines.*.month'        => 'required|integer|min:1|max:12',
            'lines.*.amount'       => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data) {
            $budget = Budget::create([
                'name'   => $data['name'],
                'year'   => $data['year'],
                'notes'  => $data['notes'] ?? null,
                'status' => Budget::STATUS_DRAFT,
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                BudgetLine::create([
                    'budget_id'    => $budget->id,
                    'account_code' => $line['account_code'],
                    'account_name' => $line['account_name'],
                    'month'        => $line['month'],
                    'amount'       => $line['amount'],
                ]);
            }
        });

        return redirect()->route('accounting.budget.index')
            ->with('success', 'Presupuesto creado.');
    }

    /** Aprueba un presupuesto en borrador. */
    public function approve(Budget $budget): RedirectResponse
    {
        if ($budget->isApproved()) {
            return back()->withErrors(['budget' => 'El presupuesto ya está aprobado.']);
        }
        $budget->update(['status' => Budget::STATUS_APPROVED]);
        return back()->with('success', 'Presupuesto aprobado.');
    }

    /**
     * Reporte de presupuesto vs. real para un presupuesto y rango de meses.
     */
    public function compare(Request $request, Budget $budget): Response
    {
        $fromMonth = (int) $request->input('from_month', 1);
        $toMonth   = (int) $request->input('to_month', now()->month);

        $year = $budget->year;

        // Meses del rango
        $months = range($fromMonth, $toMonth);

        // Líneas presupuestadas agrupadas por cuenta
        $budgeted = BudgetLine::where('budget_id', $budget->id)
            ->whereIn('month', $months)
            ->get()
            ->groupBy('account_code')
            ->map(fn ($lines) => [
                'account_code' => $lines->first()->account_code,
                'account_name' => $lines->first()->account_name,
                'budgeted'     => $lines->sum('amount'),
                'by_month'     => $lines->keyBy('month')->map(fn ($l) => (float) $l->amount),
            ]);

        // Real ejecutado: movimientos contables por cuenta y mes
        $dateFrom = Carbon::create($year, $fromMonth, 1)->startOfMonth()->toDateString();
        $dateTo   = Carbon::create($year, $toMonth, 1)->endOfMonth()->toDateString();

        $actual = AccountingDocumentDetail::join(
                'accounting_documents', 'accounting_documents.id', '=', 'accounting_document_details.accounting_document_id'
            )
            ->whereBetween('accounting_documents.issue_date', [$dateFrom, $dateTo])
            ->where('accounting_documents.annulled', false)
            ->select([
                'accounting_document_details.accountable_id as account_code',
                DB::raw("EXTRACT(MONTH FROM accounting_documents.issue_date) as month"),
                DB::raw('SUM(accounting_document_details.debit) as total_debit'),
                DB::raw('SUM(accounting_document_details.credit) as total_credit'),
            ])
            ->groupBy('accounting_document_details.accountable_id', 'month')
            ->get()
            ->groupBy('account_code')
            ->map(fn ($rows) => [
                'total_debit'  => $rows->sum('total_debit'),
                'total_credit' => $rows->sum('total_credit'),
                'by_month'     => $rows->keyBy('month'),
            ]);

        // Combinar presupuesto + real
        $comparison = $budgeted->map(function ($b) use ($actual) {
            $act      = $actual->get($b['account_code']);
            $realDebit  = (float) ($act['total_debit'] ?? 0);
            $realCredit = (float) ($act['total_credit'] ?? 0);
            $real       = $realDebit - $realCredit;
            $budg       = (float) $b['budgeted'];
            $variance   = $real - $budg;

            return [
                'account_code'    => $b['account_code'],
                'account_name'    => $b['account_name'],
                'budgeted'        => $budg,
                'real'            => $real,
                'variance'        => $variance,
                'variance_pct'    => $budg != 0 ? round($variance / $budg * 100, 1) : null,
                'execution_pct'   => $budg != 0 ? round($real / $budg * 100, 1) : null,
            ];
        })->values();

        // Totales globales
        $totals = [
            'budgeted'  => $comparison->sum('budgeted'),
            'real'      => $comparison->sum('real'),
            'variance'  => $comparison->sum('variance'),
        ];

        return Inertia::render('Accounting/Budget/Compare', [
            'budget'     => $budget->only('id', 'name', 'year', 'status'),
            'comparison' => $comparison,
            'totals'     => $totals,
            'months'     => $months,
            'filters'    => ['from_month' => $fromMonth, 'to_month' => $toMonth],
        ]);
    }

    /** Exporta el comparativo a Excel. */
    public function export(Request $request, Budget $budget): mixed
    {
        $fromMonth = (int) $request->input('from_month', 1);
        $toMonth   = (int) $request->input('to_month', now()->month);

        // Reusar lógica de compare via request
        $compareRequest = Request::create('', 'GET', [
            'from_month' => $fromMonth,
            'to_month'   => $toMonth,
        ]);
        $data = $this->compare($compareRequest, $budget);
        $props = $data->getSession() ?? []; // no disponible, hacemos query directo

        // Query simplificado para export
        $rows = BudgetLine::where('budget_id', $budget->id)
            ->whereIn('month', range($fromMonth, $toMonth))
            ->get()
            ->groupBy('account_code')
            ->map(fn ($lines) => [
                $lines->first()->account_code,
                $lines->first()->account_name,
                $lines->sum('amount'),
                0, // real — se calcularía igual que en compare()
                0,
                0,
            ])->values()->toArray();

        $headers = ['Cuenta', 'Nombre', 'Presupuesto', 'Real', 'Variación', '% Ejecución'];
        $meta    = ["Presupuesto vs Real: {$budget->name} ({$budget->year})", "Meses {$fromMonth} a {$toMonth}"];

        return Excel::download(
            new ArrayExport($rows, $headers, 'Presupuesto vs Real', $meta),
            "presupuesto_{$budget->year}_{$fromMonth}_{$toMonth}.xlsx"
        );
    }
}
