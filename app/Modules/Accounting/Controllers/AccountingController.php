<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Core\Models\Company;
use App\Shared\Exports\ArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Controlador del módulo de Contabilidad.
 * Expone: Libro Diario, Libro Mayor, Balance de Prueba.
 * Los estados financieros (P&G, Balance General) están en FinancialReportController.
 */
class AccountingController extends Controller
{
    /**
     * Libro Diario — listado de comprobantes contables con sus líneas.
     */
    public function journal(Request $request): Response
    {
        $query = AccountingDocument::with(['document', 'lines'])
            ->orderBy('issue_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($from = $request->input('date_from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('issue_date', '<=', $to);
        }
        if ($type = $request->input('type')) {
            $query->where('type_document_operation_id', $type);
        }
        if ($request->boolean('annulled') === false) {
            $query->where('annulled', false);
        }

        $vouchers = $query->paginate(30)->withQueryString();

        // Totales del período filtrado
        $totals = AccountingDocument::where('annulled', false)
            ->when($request->input('date_from'), fn ($q, $v) => $q->whereDate('issue_date', '>=', $v))
            ->when($request->input('date_to'),   fn ($q, $v) => $q->whereDate('issue_date', '<=', $v))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit, COUNT(*) as total_vouchers')
            ->first();

        return Inertia::render('Accounting/Journal', [
            'vouchers' => $vouchers,
            'totals'   => $totals,
            'filters'  => $request->only(['date_from', 'date_to', 'type']),
        ]);
    }

    /**
     * Libro Mayor — movimientos agrupados por cuenta PUC.
     * Muestra débitos, créditos y saldo para cada cuenta en el período.
     */
    public function ledger(Request $request): Response
    {
        $from      = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to        = $request->input('date_to',   now()->toDateString());
        $accountCode = $request->input('account_code');

        // Movimientos del período agrupados por cuenta
        $query = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->where('v.annulled', false)
            ->whereBetween('v.issue_date', [$from, $to])
            ->select(
                'd.accountable_id as code',
                DB::raw('SUM(d.debit) as total_debit'),
                DB::raw('SUM(d.credit) as total_credit'),
                DB::raw('SUM(d.debit) - SUM(d.credit) as balance'),
                DB::raw('COUNT(DISTINCT v.id) as voucher_count')
            )
            ->groupBy('d.accountable_id')
            ->orderBy('d.accountable_id');

        if ($accountCode) {
            $query->where('d.accountable_id', 'like', "{$accountCode}%");
        }

        $movements = $query->get();

        // Enriquecer con nombres del PUC
        $codes      = $movements->pluck('code')->unique()->filter();
        $accounts   = ChartOfAccount::whereIn('code', $codes)->get()->keyBy('code');

        $movements = $movements->map(function ($row) use ($accounts) {
            $acc         = $accounts->get($row->code);
            $row->name   = $acc?->name ?? 'Cuenta no configurada';
            $row->class  = $acc?->class ?? 9;
            $row->nature = $acc?->nature ?? 'D';
            return $row;
        })->sortBy('code')->values();

        // Lista de cuentas para el filtro
        $accountList = ChartOfAccount::where('state', true)
            ->where('allows_movement', true)
            ->orderBy('code')
            ->get(['code', 'name', 'class']);

        // Detalle de movimientos de una cuenta específica
        $accountDetail = null;
        if ($accountCode) {
            $accountDetail = DB::table('accounting_documents_details as d')
                ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
                ->where('v.annulled', false)
                ->whereBetween('v.issue_date', [$from, $to])
                ->where('d.accountable_id', $accountCode)
                ->select(
                    'v.id as voucher_id',
                    'v.internal_code',
                    'v.issue_date',
                    'v.type_document_operation_id',
                    'd.document_number',
                    'd.third_party_id',
                    'd.debit',
                    'd.credit'
                )
                ->orderBy('v.issue_date')
                ->get();
        }

        return Inertia::render('Accounting/Ledger', [
            'movements'     => $movements,
            'accountDetail' => $accountDetail,
            'accounts'      => $accountList,
            'filters'       => [
                'date_from'    => $from,
                'date_to'      => $to,
                'account_code' => $accountCode,
            ],
        ]);
    }

    /**
     * Balance de Prueba — saldos de todas las cuentas en un período.
     * Base para construir P&G y Balance General.
     */
    public function trialBalance(Request $request): Response
    {
        $from = $request->input('date_from', now()->startOfYear()->toDateString());
        $to   = $request->input('date_to',   now()->toDateString());

        // Saldos por cuenta en el período
        $rows = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->where('v.annulled', false)
            ->whereBetween('v.issue_date', [$from, $to])
            ->select(
                'd.accountable_id as code',
                DB::raw('SUM(d.debit) as total_debit'),
                DB::raw('SUM(d.credit) as total_credit')
            )
            ->groupBy('d.accountable_id')
            ->orderBy('d.accountable_id')
            ->get();

        // Enriquecer con PUC
        $codes    = $rows->pluck('code')->unique()->filter();
        $accounts = ChartOfAccount::whereIn('code', $codes)->get()->keyBy('code');

        $rows = $rows->map(function ($row) use ($accounts) {
            $acc             = $accounts->get($row->code);
            $row->name       = $acc?->name ?? 'Cuenta no configurada';
            $row->class      = $acc?->class ?? 9;
            $row->class_name = ChartOfAccount::className($acc?->class ?? 9);
            $row->nature     = $acc?->nature ?? 'D';
            // Saldo neto según naturaleza
            if ($row->nature === 'D') {
                $row->balance_debit  = max(0, $row->total_debit - $row->total_credit);
                $row->balance_credit = max(0, $row->total_credit - $row->total_debit);
            } else {
                $row->balance_debit  = max(0, $row->total_debit - $row->total_credit);
                $row->balance_credit = max(0, $row->total_credit - $row->total_debit);
            }
            return $row;
        })->sortBy('code')->values();

        // Totales de comprobación (deben cuadrar)
        $totalDebit  = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');

        // Agrupados por clase para la vista
        $byClass = $rows->groupBy('class')->map(fn ($g) => [
            'class'        => $g->first()->class,
            'class_name'   => $g->first()->class_name,
            'accounts'     => $g->values(),
            'total_debit'  => $g->sum('total_debit'),
            'total_credit' => $g->sum('total_credit'),
        ])->values();

        return Inertia::render('Accounting/TrialBalance', [
            'byClass'      => $byClass,
            'totalDebit'   => $totalDebit,
            'totalCredit'  => $totalCredit,
            'balanced'     => abs($totalDebit - $totalCredit) < 0.01,
            'filters'      => ['date_from' => $from, 'date_to' => $to],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // EXPORTACIONES
    // ──────────────────────────────────────────────────────────────────────

    public function exportTrialBalance(Request $request): mixed
    {
        $from   = $request->input('date_from', now()->startOfYear()->toDateString());
        $to     = $request->input('date_to',   now()->toDateString());
        $format = $request->input('format', 'excel');

        $rows = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->where('v.annulled', false)
            ->whereBetween('v.issue_date', [$from, $to])
            ->select('d.accountable_id as code', DB::raw('SUM(d.debit) as total_debit'), DB::raw('SUM(d.credit) as total_credit'))
            ->groupBy('d.accountable_id')
            ->orderBy('d.accountable_id')
            ->get();

        $codes    = $rows->pluck('code')->unique()->filter();
        $accounts = ChartOfAccount::whereIn('code', $codes)->get()->keyBy('code');
        $rows     = $rows->map(function ($row) use ($accounts) {
            $acc             = $accounts->get($row->code);
            $row->name       = $acc?->name ?? 'Cuenta no configurada';
            $row->class_name = ChartOfAccount::className($acc?->class ?? 9);
            $row->balance_debit  = max(0, $row->total_debit - $row->total_credit);
            $row->balance_credit = max(0, $row->total_credit - $row->total_debit);
            return $row;
        })->sortBy('code')->values();

        $totalDebit  = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');
        $balanced    = abs($totalDebit - $totalCredit) < 0.01;
        $company     = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.trial_balance', compact('rows', 'totalDebit', 'totalCredit', 'balanced', 'from', 'to', 'company'))
                ->setPaper('a4', 'portrait')
                ->download("balance_prueba_{$from}_{$to}.pdf");
        }

        $meta      = [$company?->business_name ?? 'Empresa', "Balance de Prueba — {$from} al {$to}"];
        $headers   = ['Clase', 'Código', 'Cuenta', 'Débito Total', 'Crédito Total', 'Saldo Débito', 'Saldo Crédito'];
        $excelRows = $rows->map(fn ($r) => [
            $r->class_name,
            $r->code,
            $r->name,
            $r->total_debit,
            $r->total_credit,
            $r->balance_debit,
            $r->balance_credit,
        ])->toArray();

        // Fila de totales
        $excelRows[] = ['', 'TOTALES', '', $totalDebit, $totalCredit, '', $balanced ? 'CUADRA ✓' : 'NO CUADRA ✗'];

        return Excel::download(
            new ArrayExport($excelRows, $headers, 'Balance de Prueba', $meta),
            "balance_prueba_{$from}_{$to}.xlsx"
        );
    }

    public function exportJournal(Request $request): mixed
    {
        $from   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to     = $request->input('date_to',   now()->toDateString());
        $format = $request->input('format', 'excel');

        $vouchers = AccountingDocument::with('lines')
            ->where('annulled', false)
            ->whereBetween('issue_date', [$from, $to])
            ->orderBy('issue_date')
            ->get();

        $company = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.journal', compact('vouchers', 'from', 'to', 'company'))
                ->setPaper('a4', 'landscape')
                ->download("libro_diario_{$from}_{$to}.pdf");
        }

        $meta    = [$company?->business_name ?? 'Empresa', "Libro Diario — {$from} al {$to}"];
        $headers = ['Fecha', 'Comprobante', 'Cuenta', 'Tercero', 'Descripción', 'Débito', 'Crédito'];
        $rows    = [];

        foreach ($vouchers as $v) {
            foreach ($v->lines as $line) {
                $rows[] = [
                    $v->issue_date,
                    $v->internal_code,
                    $line->accountable_id,
                    $line->third_party_id ?? '—',
                    $line->description ?? '—',
                    $line->debit ?? 0,
                    $line->credit ?? 0,
                ];
            }
        }

        return Excel::download(
            new ArrayExport($rows, $headers, 'Libro Diario', $meta),
            "libro_diario_{$from}_{$to}.xlsx"
        );
    }
}
