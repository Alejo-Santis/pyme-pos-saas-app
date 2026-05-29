<?php

namespace App\Modules\Cash\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\CashReceipt;
use App\Modules\Cash\Models\CashBox;
use App\Modules\Cash\Models\PaymentReceipt;
use App\Modules\Cash\Services\PaymentSettlementService;
use App\Modules\Core\Models\ThirdParty;
use App\Modules\Invoice\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly PaymentSettlementService $settlementService
    ) {}

    public function index(Request $request): Response
    {
        $start = $request->start_date;
        $end = $request->end_date;

        $cashReceipts = CashReceipt::with(['thirdParty:id,name,identification_number', 'user:id,name'])
            ->withCount('details')
            ->when($start, fn ($q) => $q->where('issue_date', '>=', $start))
            ->when($end, fn ($q) => $q->where('issue_date', '<=', $end))
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'cash_page')
            ->withQueryString();

        $paymentReceipts = PaymentReceipt::with(['thirdParty:id,name,identification_number', 'user:id,name'])
            ->withCount('details')
            ->when($start, fn ($q) => $q->where('issue_date', '>=', $start))
            ->when($end, fn ($q) => $q->where('issue_date', '<=', $end))
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'payment_page')
            ->withQueryString();

        return Inertia::render('Cash/Receipts', [
            'cashReceipts' => $cashReceipts,
            'paymentReceipts' => $paymentReceipts,
            'filters' => $request->only(['start_date', 'end_date']),
            'thirdParties' => ThirdParty::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'identification_number']),
            'cashBoxes' => CashBox::where('state', true)
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get(['id', 'name', 'internal_code']),
            'bankAccounts' => BankAccount::with('bank:id,name')
                ->where('state', true)
                ->orderBy('name')
                ->get(['id', 'bank_id', 'name', 'type', 'account_bank_number']),
            'receivableDocuments' => Document::with('thirdParty:id,name,identification_number')
                ->where('annulled', false)
                ->where('paid', false)
                ->where('balance', '>', 0)
                ->whereIn('type_document_operation_id', [1, 92])
                ->orderByDesc('issue_date')
                ->limit(80)
                ->get(['id', 'internal_code', 'prefix', 'number', 'third_party_id', 'issue_date', 'total', 'balance']),
            'payableDocuments' => Document::with('thirdParty:id,name,identification_number')
                ->where('annulled', false)
                ->where('paid', false)
                ->where('balance', '>', 0)
                ->whereIn('type_document_operation_id', [5, 14])
                ->orderByDesc('issue_date')
                ->limit(80)
                ->get(['id', 'internal_code', 'prefix', 'number', 'third_party_id', 'issue_date', 'total', 'balance']),
            'summary' => [
                'cash_total' => CashReceipt::when($start, fn ($q) => $q->where('issue_date', '>=', $start))
                    ->when($end, fn ($q) => $q->where('issue_date', '<=', $end))
                    ->where('annulled', false)
                    ->sum('amount_received'),
                'payment_total' => PaymentReceipt::when($start, fn ($q) => $q->where('issue_date', '>=', $start))
                    ->when($end, fn ($q) => $q->where('issue_date', '<=', $end))
                    ->sum('amount_received'),
            ],
        ]);
    }

    public function storeCashReceipt(Request $request): RedirectResponse
    {
        $data = $this->validateReceiptPayload($request);

        $receipt = $this->settlementService->createManualCashReceipt($data, Auth::id());

        return back()->with('success', "Recibo {$receipt->internal_code} registrado correctamente.");
    }

    public function storePaymentReceipt(Request $request): RedirectResponse
    {
        $data = $this->validateReceiptPayload($request);

        $receipt = $this->settlementService->createManualPaymentReceipt($data, Auth::id());

        return back()->with('success', "Egreso {$receipt->internal_code} registrado correctamente.");
    }

    private function validateReceiptPayload(Request $request): array
    {
        return $request->validate([
            'third_party_id' => 'nullable|uuid|exists:third_parties,id',
            'issue_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
            'payment_forms' => 'required|array|min:1',
            'payment_forms.*.payment_form_id' => 'required|integer',
            'payment_forms.*.payment_method_id' => 'required|integer',
            'payment_forms.*.value' => 'required|numeric|min:0.01',
            'payment_forms.*.cash_box_id' => 'nullable|uuid|exists:cash_boxes,id',
            'payment_forms.*.bank_account_id' => 'nullable|uuid|exists:bank_accounts,id',
            'payment_forms.*.transaction_reference' => 'nullable|string|max:100',
            'allocations' => 'nullable|array',
            'allocations.*.document_id' => 'required_with:allocations|uuid|exists:documents,id',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0.01',
            'allocations.*.withholdings_tax' => 'nullable|numeric|min:0',
            'allocations.*.transaction_reference' => 'nullable|string|max:100',
        ]);
    }
}
