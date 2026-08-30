<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Cash\Models\CashReceipt;
use App\Modules\Cash\Models\PaymentReceipt;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Invoice\Models\Document;
use App\Shared\Exports\ArrayExport;
use App\Shared\Traits\AccountingEngineTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    use AccountingEngineTrait;

    /**
     * Auditoría de documentos comerciales y comprobantes contables.
     */
    public function audit(Request $request): Response
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());

        $unbalanced = AccountingDocument::query()
            ->where('annulled', false)
            ->whereBetween('issue_date', [$from, $to])
            ->whereRaw('ABS(debit - credit) > 0.01')
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get(['id', 'internal_code', 'issue_date', 'type_document_operation_id', 'document_id', 'debit', 'credit', 'total'])
            ->map(fn (AccountingDocument $voucher) => [
                'id' => $voucher->id,
                'internal_code' => $voucher->internal_code,
                'issue_date' => (string) $voucher->issue_date,
                'type_document_operation_id' => $voucher->type_document_operation_id,
                'source_type' => $voucher->document_id ? 'document' : 'voucher',
                'source_id' => $voucher->document_id,
                'debit' => $voucher->debit,
                'credit' => $voucher->credit,
                'difference' => round(abs((float) $voucher->debit - (float) $voucher->credit), 4),
                'total' => $voucher->total,
            ]);

        $documents = Document::with('thirdParty')
            ->active()
            ->whereBetween('issue_date', [$from, $to])
            ->whereIn('type_document_operation_id', [1, 14, 91, 92])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('accounting_documents')
                    ->whereColumn('accounting_documents.document_id', 'documents.id');
            })
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get()
            ->map(fn (Document $document) => $this->mapAuditSource($document, 'document'));

        $cashReceipts = $this->missingReceiptVouchers(
            CashReceipt::with('thirdParty')->where('annulled', false)->whereBetween('issue_date', [$from, $to]),
            'cash_receipt'
        );

        $paymentReceipts = $this->missingReceiptVouchers(
            PaymentReceipt::with('thirdParty')->whereBetween('issue_date', [$from, $to]),
            'payment_receipt'
        );

        return Inertia::render('Accounting/Audit', [
            'filters' => ['date_from' => $from, 'date_to' => $to],
            'summary' => [
                'unbalanced' => $unbalanced->count(),
                'documents' => $documents->count(),
                'cash_receipts' => $cashReceipts->count(),
                'payment_receipts' => $paymentReceipts->count(),
            ],
            'unbalanced' => $unbalanced,
            'documents' => $documents,
            'cashReceipts' => $cashReceipts,
            'paymentReceipts' => $paymentReceipts,
        ]);
    }

    /**
     * Regenera el comprobante contable de un origen puntual.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_type' => ['required', 'in:document,cash_receipt,payment_receipt'],
            'source_id' => ['required', 'uuid'],
        ]);

        DB::transaction(function () use ($data) {
            if ($data['source_type'] === 'document') {
                $document = Document::with(['lines', 'thirdParty'])->findOrFail($data['source_id']);
                AccountingDocument::where('document_id', $document->id)->delete();
                $this->generateAccountingEntry($document);
                return;
            }

            if ($data['source_type'] === 'cash_receipt') {
                $receipt = CashReceipt::with(['details.document', 'thirdParty'])->findOrFail($data['source_id']);
                AccountingDocument::where('internal_code', 'COMP-' . $receipt->internal_code)->delete();
                $this->generateCashReceiptAccounting($receipt);
                return;
            }

            $receipt = PaymentReceipt::with(['details.document', 'thirdParty'])->findOrFail($data['source_id']);
            AccountingDocument::where('internal_code', 'COMP-' . $receipt->internal_code)->delete();
            $this->generatePaymentReceiptAccounting($receipt);
        });

        return back()->with('success', 'Comprobante contable regenerado.');
    }

    private function missingReceiptVouchers($query, string $sourceType)
    {
        $table = $query->getModel()->getTable();

        return $query
            ->whereNotExists(function ($subQuery) use ($table) {
                $subQuery->selectRaw('1')
                    ->from('accounting_documents')
                    ->whereRaw("accounting_documents.internal_code = 'COMP-' || {$table}.internal_code");
            })
            ->orderByDesc('issue_date')
            ->limit(50)
            ->get()
            ->map(fn ($receipt) => $this->mapAuditSource($receipt, $sourceType));
    }

    private function mapAuditSource($source, string $sourceType): array
    {
        return [
            'id' => $source->id,
            'source_type' => $sourceType,
            'internal_code' => $source->internal_code,
            'issue_date' => $source->issue_date?->toDateString() ?? (string) $source->issue_date,
            'third_party' => $source->thirdParty?->name ?? 'Sin tercero',
            'type_document_operation_id' => $source->type_document_operation_id,
            'total' => $source->total ?? $source->amount_received ?? $source->total_amount ?? 0,
            'balance' => $source->balance ?? null,
        ];
    }

    /**
     * Auxiliar contable por tercero, cuenta y documento.
     */
    public function auxiliary(Request $request): Response
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());
        $thirdPartyId = $request->input('third_party_id');
        $accountCode = trim((string) $request->input('account_code', ''));
        $documentNumber = trim((string) $request->input('document_number', ''));

        $baseQuery = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->leftJoin('third_parties as tp', 'tp.id', '=', 'd.third_party_id')
            ->leftJoin('chart_of_accounts as coa', 'coa.code', '=', 'd.accountable_id')
            ->where('v.annulled', false)
            ->whereBetween('d.issue_date', [$from, $to])
            ->when($thirdPartyId, fn ($q) => $q->where('d.third_party_id', $thirdPartyId))
            ->when($accountCode !== '', fn ($q) => $q->where('d.accountable_id', 'like', "{$accountCode}%"))
            ->when($documentNumber !== '', fn ($q) => $q->where('d.document_number', 'ilike', "%{$documentNumber}%"));

        $totals = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(d.debit), 0) as debit, COALESCE(SUM(d.credit), 0) as credit, COUNT(*) as lines')
            ->first();

        $byDocument = (clone $baseQuery)
            ->select(
                'd.document_number',
                'd.third_party_id',
                DB::raw("COALESCE(tp.name, 'Sin tercero') as third_party"),
                DB::raw('COALESCE(SUM(d.debit), 0) as debit'),
                DB::raw('COALESCE(SUM(d.credit), 0) as credit'),
                DB::raw('COALESCE(SUM(d.debit) - SUM(d.credit), 0) as balance'),
                DB::raw('COUNT(*) as lines')
            )
            ->groupBy('d.document_number', 'd.third_party_id', 'tp.name')
            ->orderByDesc(DB::raw('ABS(COALESCE(SUM(d.debit) - SUM(d.credit), 0))'))
            ->limit(30)
            ->get();

        $movements = (clone $baseQuery)
            ->select(
                'd.id',
                'd.issue_date',
                'v.internal_code as voucher_code',
                'v.type_document_operation_id',
                'd.accountable_id as account_code',
                DB::raw("COALESCE(coa.name, 'Cuenta no configurada') as account_name"),
                'd.third_party_id',
                DB::raw("COALESCE(tp.name, 'Sin tercero') as third_party"),
                'tp.identification_number',
                'd.document_number',
                'd.debit',
                'd.credit'
            )
            ->orderBy('d.issue_date')
            ->orderBy('v.created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Accounting/Auxiliary', [
            'movements' => $movements,
            'summary' => [
                'debit' => (float) ($totals->debit ?? 0),
                'credit' => (float) ($totals->credit ?? 0),
                'balance' => (float) (($totals->debit ?? 0) - ($totals->credit ?? 0)),
                'lines' => (int) ($totals->lines ?? 0),
            ],
            'byDocument' => $byDocument,
            'commercialReconciliation' => $this->commercialReconciliation($thirdPartyId),
            'thirdParties' => ThirdParty::active()
                ->orderBy('name')
                ->get(['id', 'name', 'identification_number']),
            'accounts' => ChartOfAccount::where('state', true)
                ->where('allows_movement', true)
                ->orderBy('code')
                ->get(['code', 'name', 'class']),
            'filters' => [
                'date_from' => $from,
                'date_to' => $to,
                'third_party_id' => $thirdPartyId,
                'account_code' => $accountCode,
                'document_number' => $documentNumber,
            ],
        ]);
    }

    /**
     * Diferencias entre saldos comerciales y saldos contables.
     */
    public function differences(Request $request): Response
    {
        $thirdPartyId = $request->input('third_party_id');
        $side = $request->input('side', 'all');
        $onlyDifferences = $request->boolean('only_differences', true);

        $rows = $this->commercialReconciliation($thirdPartyId)
            ->when($side !== 'all', fn ($collection) => $collection->where('side', $side))
            ->when($onlyDifferences, fn ($collection) => $collection->filter(fn ($row) => abs((float) $row['difference']) >= 1))
            ->values();

        return Inertia::render('Accounting/Differences', [
            'rows' => $rows,
            'summary' => [
                'documents' => $rows->count(),
                'commercial_balance' => round($rows->sum('commercial_balance'), 2),
                'accounting_balance' => round($rows->sum('accounting_balance'), 2),
                'absolute_difference' => round($rows->sum(fn ($row) => abs((float) $row['difference'])), 2),
            ],
            'thirdParties' => ThirdParty::active()
                ->orderBy('name')
                ->get(['id', 'name', 'identification_number']),
            'accounts' => ChartOfAccount::where('state', true)
                ->where('allows_movement', true)
                ->orderBy('code')
                ->get(['code', 'name', 'class']),
            'filters' => [
                'third_party_id' => $thirdPartyId,
                'side' => $side,
                'only_differences' => $onlyDifferences,
            ],
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'document_id' => ['required', 'uuid', 'exists:documents,id'],
            'side' => ['required', 'in:receivable,payable'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'target_account_code' => ['required', 'string', 'exists:chart_of_accounts,code'],
            'counterpart_account_code' => ['required', 'string', 'different:target_account_code', 'exists:chart_of_accounts,code'],
            'direction' => ['required', 'in:increase,decrease'],
            'issue_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $document = Document::with('thirdParty')->findOrFail($data['document_id']);
            $amount = round((float) $data['amount'], 4);
            $targetIsDebit = $this->targetAdjustmentIsDebit($data['side'], $data['direction']);

            $voucher = AccountingDocument::create([
                'uuid' => Str::uuid(),
                'internal_code' => 'COMP-AJUSTE-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id' => $request->user()?->id,
                'third_party_id' => $document->third_party_id,
                'document_id' => $document->id,
                'type_document_operation_id' => 98,
                'prefix' => 'AJ',
                'debit' => 0,
                'credit' => 0,
                'total' => $amount,
                'notes' => $data['reason'],
                'issue_date' => $data['issue_date'],
                'annulled' => false,
            ]);

            $this->createAdjustmentLine(
                voucher: $voucher,
                document: $document,
                accountCode: $data['target_account_code'],
                debit: $targetIsDebit ? $amount : 0,
                credit: $targetIsDebit ? 0 : $amount
            );

            $this->createAdjustmentLine(
                voucher: $voucher,
                document: $document,
                accountCode: $data['counterpart_account_code'],
                debit: $targetIsDebit ? 0 : $amount,
                credit: $targetIsDebit ? $amount : 0
            );

            $voucher->forceFill([
                'debit' => $amount,
                'credit' => $amount,
            ])->save();
        });

        return back()->with('success', 'Comprobante de ajuste contable creado.');
    }

    public function adjustments(Request $request): Response
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());
        $status = $request->input('status', 'active');

        $query = AccountingDocument::with(['document:id,internal_code,type_document_operation_id', 'lines', 'reversal:id,internal_code,issue_date'])
            ->whereIn('type_document_operation_id', [98, 99])
            ->whereBetween('issue_date', [$from, $to])
            ->when($status === 'active', fn ($q) => $q->whereNull('reversed_at')->where('annulled', false))
            ->when($status === 'reversed', fn ($q) => $q->whereNotNull('reversed_at'))
            ->when($status === 'reversal', fn ($q) => $q->where('type_document_operation_id', 99))
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');

        $totals = (clone $query)
            ->reorder()
            ->selectRaw('COUNT(*) as total_vouchers, COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        return Inertia::render('Accounting/Adjustments', [
            'vouchers' => $query->paginate(30)->withQueryString(),
            'summary' => [
                'total_vouchers' => (int) ($totals->total_vouchers ?? 0),
                'total_debit' => (float) ($totals->total_debit ?? 0),
                'total_credit' => (float) ($totals->total_credit ?? 0),
            ],
            'filters' => [
                'date_from' => $from,
                'date_to' => $to,
                'status' => $status,
            ],
        ]);
    }

    public function reverseAdjustment(Request $request, AccountingDocument $voucher): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'issue_date' => ['required', 'date'],
        ]);

        if ((int) $voucher->type_document_operation_id !== 98) {
            return back()->with('error', 'Sólo se pueden reversar ajustes contables manuales.');
        }

        if ($voucher->reversed_at || $voucher->reversed_by_accounting_document_id) {
            return back()->with('error', 'Este ajuste ya fue reversado.');
        }

        DB::transaction(function () use ($voucher, $data, $request) {
            $voucher->load('lines');

            $reversal = AccountingDocument::create([
                'uuid' => Str::uuid(),
                'internal_code' => 'COMP-REV-AJ-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id' => $request->user()?->id,
                'third_party_id' => $voucher->third_party_id,
                'document_id' => $voucher->document_id,
                'type_document_operation_id' => 99,
                'prefix' => 'RAJ',
                'debit' => $voucher->debit,
                'credit' => $voucher->credit,
                'total' => $voucher->total,
                'notes' => $data['reason'],
                'issue_date' => $data['issue_date'],
                'annulled' => false,
            ]);

            foreach ($voucher->lines as $line) {
                AccountingDocumentDetail::create([
                    'accounting_document_id' => $reversal->id,
                    'accountable_id' => $line->accountable_id,
                    'accountable_type' => $line->accountable_type,
                    'third_party_id' => $line->third_party_id,
                    'cost_center_id' => $line->cost_center_id,
                    'document_number' => $line->document_number,
                    'taxable_amount' => $line->taxable_amount,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'issue_date' => $data['issue_date'],
                ]);
            }

            $voucher->forceFill([
                'reversed_at' => now(),
                'reversed_by_accounting_document_id' => $reversal->id,
                'notes' => trim(($voucher->notes ? $voucher->notes . "\n" : '') . 'Reversado: ' . $data['reason']),
            ])->save();
        });

        return back()->with('success', 'Ajuste reversado con comprobante inverso.');
    }

    private function targetAdjustmentIsDebit(string $side, string $direction): bool
    {
        if ($side === 'receivable') {
            return $direction === 'increase';
        }

        return $direction === 'decrease';
    }

    private function createAdjustmentLine(
        AccountingDocument $voucher,
        Document $document,
        string $accountCode,
        float $debit,
        float $credit
    ): void {
        AccountingDocumentDetail::create([
            'accounting_document_id' => $voucher->id,
            'accountable_id' => $accountCode,
            'accountable_type' => 'chart_account',
            'third_party_id' => $document->third_party_id,
            'document_number' => $document->internal_code,
            'taxable_amount' => max($debit, $credit),
            'debit' => round($debit, 4),
            'credit' => round($credit, 4),
            'issue_date' => $voucher->issue_date,
        ]);
    }

    private function operationLabel(int $operationId): string
    {
        return match ($operationId) {
            1 => 'Factura Venta',
            5 => 'Documento soporte',
            14 => 'Compra',
            91 => 'Nota Crédito',
            92 => 'Nota Débito',
            97 => 'Cierre POS',
            98 => 'Ajuste manual',
            99 => 'Reverso de ajuste',
            default => "Operación {$operationId}",
        };
    }

    private function commercialReconciliation(?string $thirdPartyId)
    {
        $documents = Document::with('thirdParty:id,name,identification_number')
            ->where('annulled', false)
            ->where('balance', '>', 0)
            ->whereIn('type_document_operation_id', [1, 5, 14, 92])
            ->when($thirdPartyId, fn ($q) => $q->where('third_party_id', $thirdPartyId))
            ->orderByDesc('issue_date')
            ->limit(80)
            ->get(['id', 'internal_code', 'third_party_id', 'type_document_operation_id', 'issue_date', 'total', 'balance']);

        $documentNumbers = $documents->pluck('internal_code')->filter()->values();

        $accountingBalances = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->where('v.annulled', false)
            ->whereIn('d.document_number', $documentNumbers)
            ->where(function ($q) {
                $q->where('d.accountable_id', 'like', '13%')
                    ->orWhere('d.accountable_id', 'like', '22%');
            })
            ->select(
                'd.document_number',
                DB::raw("SUM(CASE WHEN d.accountable_id LIKE '13%' THEN d.debit - d.credit ELSE 0 END) as receivable_balance"),
                DB::raw("SUM(CASE WHEN d.accountable_id LIKE '22%' THEN d.credit - d.debit ELSE 0 END) as payable_balance")
            )
            ->groupBy('d.document_number')
            ->get()
            ->keyBy('document_number');

        $cashReceipts = DB::table('cash_receipt_details as d')
            ->join('cash_receipts as r', 'r.id', '=', 'd.cash_receipt_id')
            ->whereIn('d.document_id', $documents->pluck('id'))
            ->select('d.document_id', 'r.id', 'r.internal_code', 'r.issue_date', 'd.amount')
            ->orderByDesc('r.issue_date')
            ->get()
            ->groupBy('document_id');

        $paymentReceipts = DB::table('payment_receipts_details as d')
            ->join('payment_receipts as r', 'r.id', '=', 'd.payment_receipt_id')
            ->whereIn('d.document_id', $documents->pluck('id'))
            ->select('d.document_id', 'r.id', 'r.internal_code', 'r.issue_date', 'd.amount')
            ->orderByDesc('r.issue_date')
            ->get()
            ->groupBy('document_id');

        return $documents->map(function (Document $document) use ($accountingBalances, $cashReceipts, $paymentReceipts) {
            $isReceivable = in_array((int) $document->type_document_operation_id, [1, 92], true);
            $balanceRow = $accountingBalances->get($document->internal_code);
            $accountingBalance = $isReceivable
                ? (float) ($balanceRow->receivable_balance ?? 0)
                : (float) ($balanceRow->payable_balance ?? 0);
            $commercialBalance = (float) $document->balance;

            return [
                'id' => $document->id,
                'internal_code' => $document->internal_code,
                'third_party' => $document->thirdParty?->name ?? 'Sin tercero',
                'identification_number' => $document->thirdParty?->identification_number,
                'type_document_operation_id' => $document->type_document_operation_id,
                'side' => $isReceivable ? 'receivable' : 'payable',
                'issue_date' => $document->issue_date?->toDateString() ?? (string) $document->issue_date,
                'commercial_balance' => round($commercialBalance, 2),
                'accounting_balance' => round($accountingBalance, 2),
                'difference' => round($commercialBalance - $accountingBalance, 2),
                'receipts' => ($isReceivable ? $cashReceipts : $paymentReceipts)
                    ->get($document->id, collect())
                    ->take(5)
                    ->map(fn ($receipt) => [
                        'id' => $receipt->id,
                        'internal_code' => $receipt->internal_code,
                        'issue_date' => (string) $receipt->issue_date,
                        'amount' => round((float) $receipt->amount, 2),
                    ])
                    ->values(),
            ];
        })
            ->sortByDesc(fn ($row) => abs($row['difference']))
            ->values();
    }

    /**
     * Libro Diario — listado de comprobantes contables con sus líneas.
     */
    public function journal(Request $request): Response
    {
        $type = $request->input('type');

        $query = AccountingDocument::with(['document', 'lines', 'reversal:id,internal_code,issue_date'])
            ->orderBy('issue_date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($from = $request->input('date_from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('issue_date', '<=', $to);
        }
        if ($type) {
            if ($type === 'adjustments') {
                $query->whereIn('type_document_operation_id', [98, 99]);
            } else {
                $query->where('type_document_operation_id', $type);
            }
        }
        if ($request->boolean('annulled') === false) {
            $query->where('annulled', false);
        }

        $vouchers = $query->paginate(30)->withQueryString();

        // Totales del período filtrado
        $totals = AccountingDocument::where('annulled', false)
            ->when($request->input('date_from'), fn ($q, $v) => $q->whereDate('issue_date', '>=', $v))
            ->when($request->input('date_to'),   fn ($q, $v) => $q->whereDate('issue_date', '<=', $v))
            ->when($type === 'adjustments', fn ($q) => $q->whereIn('type_document_operation_id', [98, 99]))
            ->when($type && $type !== 'adjustments', fn ($q) => $q->where('type_document_operation_id', $type))
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
        $type = $request->input('type');

        $vouchers = AccountingDocument::with(['document:id,internal_code', 'lines', 'reversal:id,internal_code,issue_date'])
            ->where('annulled', false)
            ->whereBetween('issue_date', [$from, $to])
            ->when($type === 'adjustments', fn ($q) => $q->whereIn('type_document_operation_id', [98, 99]))
            ->when($type && $type !== 'adjustments', fn ($q) => $q->where('type_document_operation_id', $type))
            ->orderBy('issue_date')
            ->get();

        $company = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.journal', compact('vouchers', 'from', 'to', 'company'))
                ->setPaper('a4', 'landscape')
                ->download("libro_diario_{$from}_{$to}.pdf");
        }

        $meta    = [$company?->name ?? 'Empresa', "Libro Diario — {$from} al {$to}"];
        $headers = ['Fecha', 'Comprobante', 'Tipo', 'Estado', 'Documento origen', 'Reversado por', 'Cuenta', 'Tercero', 'Documento línea', 'Descripción', 'Débito', 'Crédito'];
        $rows    = [];

        foreach ($vouchers as $v) {
            foreach ($v->lines as $line) {
                $rows[] = [
                    $v->issue_date,
                    $v->internal_code,
                    $this->operationLabel((int) $v->type_document_operation_id),
                    $v->reversed_at ? 'Reversado' : ($v->annulled ? 'Anulado' : 'Activo'),
                    $v->document?->internal_code ?? '',
                    $v->reversal?->internal_code ?? '',
                    $line->accountable_id,
                    $line->third_party_id ?? '—',
                    $line->document_number ?? '',
                    $v->notes ?? '—',
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

    public function exportAdjustments(Request $request): mixed
    {
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->toDateString());
        $status = $request->input('status', 'active');
        $format = $request->input('format', 'excel');

        $query = AccountingDocument::with(['document:id,internal_code', 'lines', 'reversal:id,internal_code,issue_date'])
            ->whereIn('type_document_operation_id', [98, 99])
            ->whereBetween('issue_date', [$from, $to])
            ->when($status === 'active', fn ($q) => $q->whereNull('reversed_at')->where('annulled', false))
            ->when($status === 'reversed', fn ($q) => $q->whereNotNull('reversed_at'))
            ->when($status === 'reversal', fn ($q) => $q->where('type_document_operation_id', 99))
            ->orderBy('issue_date')
            ->orderBy('created_at');

        $vouchers = $query->get();
        $company = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.accounting_adjustments', compact('vouchers', 'from', 'to', 'status', 'company'))
                ->setPaper('a4', 'landscape')
                ->download("ajustes_contables_{$from}_{$to}.pdf");
        }

        $statusLabels = [
            'active' => 'Activos',
            'reversed' => 'Reversados',
            'reversal' => 'Sólo reversos',
            'all' => 'Todos',
        ];

        $meta = [
            $company?->name ?? 'Empresa',
            'Ajustes contables — ' . ($statusLabels[$status] ?? 'Todos') . " — {$from} al {$to}",
        ];
        $headers = [
            'Fecha',
            'Comprobante',
            'Tipo',
            'Documento origen',
            'Estado',
            'Reversado por',
            'Cuenta',
            'Documento línea',
            'Débito',
            'Crédito',
            'Observación',
        ];
        $rows = [];

        foreach ($vouchers as $voucher) {
            foreach ($voucher->lines as $line) {
                $rows[] = [
                    $voucher->issue_date,
                    $voucher->internal_code,
                    (int) $voucher->type_document_operation_id === 99 ? 'Reverso de ajuste' : 'Ajuste manual',
                    $voucher->document?->internal_code ?? '',
                    $voucher->reversed_at ? 'Reversado' : ($voucher->annulled ? 'Anulado' : 'Activo'),
                    $voucher->reversal?->internal_code ?? '',
                    $line->accountable_id,
                    $line->document_number,
                    $line->debit ?? 0,
                    $line->credit ?? 0,
                    $voucher->notes,
                ];
            }
        }

        return Excel::download(
            new ArrayExport($rows, $headers, 'Ajustes Contables', $meta),
            "ajustes_contables_{$from}_{$to}.xlsx"
        );
    }
}
