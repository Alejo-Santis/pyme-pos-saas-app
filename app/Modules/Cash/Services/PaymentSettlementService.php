<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Models\BankAccount;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Cash\Models\CashBox;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashReceipt;
use App\Modules\Cash\Models\CashReceiptDetail;
use App\Modules\Cash\Models\DocumentPaymentReceipt;
use App\Modules\Cash\Models\IncomeAndExpense;
use App\Modules\Cash\Models\PaymentReceipt;
use App\Modules\Cash\Models\PaymentReceiptDetail;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentPaymentMethod;
use App\Modules\POS\Models\PosTerminal;
use App\Modules\POS\Models\PosTerminalUser;
use App\Shared\Traits\AccountingEngineTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettlementService
{
    use AccountingEngineTrait;

    private const CASH_METHOD_ID = 10;

    public function createManualCashReceipt(array $data, string $userId): CashReceipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $forms = $this->normalizedForms($data['payment_forms'] ?? []);
            $total = collect($forms)->sum('value');

            if ($total <= 0) {
                throw ValidationException::withMessages(['payment_forms' => 'El recibo debe tener al menos un pago.']);
            }

            $receipt = CashReceipt::create([
                'type_document_operation_id' => 13,
                'user_id' => $userId,
                'third_party_id' => $data['third_party_id'] ?? null,
                'income_and_expense_id' => $this->incomeExpenseConcept('ING-CARTERA', 'Recaudo de cartera y anticipos', 13)->id,
                'prefix' => 'RC',
                'total_amount' => $total,
                'amount_received' => $total,
                'notes' => $data['notes'] ?? null,
                'annulled' => false,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            ]);

            $this->createCashReceiptDetailsAndApplyBalance($receipt, $data['allocations'] ?? [], $forms);
            $this->registerLooseMovements($receipt, $forms, 'in', $receipt->notes ?: "Recibo de caja {$receipt->internal_code}", $receipt->internal_code);
            $this->generateCashReceiptAccounting($receipt);

            return $receipt->load(['details.document', 'thirdParty']);
        });
    }

    public function createManualPaymentReceipt(array $data, string $userId): PaymentReceipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $forms = $this->normalizedForms($data['payment_forms'] ?? []);
            $total = collect($forms)->sum('value');

            if ($total <= 0) {
                throw ValidationException::withMessages(['payment_forms' => 'El egreso debe tener al menos un pago.']);
            }

            $receipt = PaymentReceipt::create([
                'type_document_operation_id' => 14,
                'user_id' => $userId,
                'third_party_id' => $data['third_party_id'] ?? null,
                'income_and_expense_id' => $this->incomeExpenseConcept('EGR-GENERAL', 'Egresos y pagos a terceros', 14)->id,
                'prefix' => 'PG',
                'total_amount' => $total,
                'amount_received' => $total,
                'is_paid_credit' => ! empty($data['allocations']),
                'payment_forms' => $forms,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
            ]);

            $this->createPaymentReceiptDetailsAndApplyBalance($receipt, $data['allocations'] ?? [], $forms);
            $this->registerLooseMovements($receipt, $forms, 'out', $data['notes'] ?? "Comprobante de egreso {$receipt->internal_code}", $receipt->internal_code);
            $this->generatePaymentReceiptAccounting($receipt);

            return $receipt->load(['details.document', 'thirdParty']);
        });
    }

    public function registerSalePayments(
        PosTerminal $terminal,
        PosTerminalUser $shift,
        Document $document,
        array $paymentForms
    ): array {
        $paymentForms = $this->capPaymentsToDocumentTotal($paymentForms, (float) $document->total);

        $totals = $this->registerMovements(
            document: $document,
            paymentForms: $paymentForms,
            direction: 'in',
            description: "Venta POS {$document->internal_code}",
            reference: $shift->cashier_session_key,
            fallbackCashBoxId: $terminal->cash_box_id
        );

        $shift->increment('total_sales', $document->total);
        $shift->increment('total_cash', $totals['cash']);
        $shift->increment('total_card', $totals['card']);
        $shift->increment('total_transfer', $totals['transfer']);
        $shift->increment('current_balance', $totals['cash']);

        return $totals;
    }

    public function registerCreditNoteRefund(
        Document $original,
        Document $creditNote,
        ?array $refundForms = null
    ): array {
        $forms = $refundForms ?: $this->buildRefundFormsFromOriginal($original, (float) $creditNote->total);
        $terminal = $original->pos_terminal_id ? PosTerminal::find($original->pos_terminal_id) : null;

        return $this->registerMovements(
            document: $creditNote,
            paymentForms: $forms,
            direction: 'out',
            description: "Devolución NC {$creditNote->internal_code} (ref. {$original->internal_code})",
            reference: $original->cashier_shift ?: $original->internal_code,
            fallbackCashBoxId: $terminal?->cash_box_id
        );
    }

    public function syncDocumentPaymentMethods(Document $document, array $paymentForms): void
    {
        $forms = $this->normalizedForms($paymentForms);

        $document->forceFill(['payment_forms' => $forms])->saveQuietly();

        DocumentPaymentMethod::where('document_id', $document->id)->delete();

        foreach ($forms as $form) {
            DocumentPaymentMethod::create([
                'document_id'        => $document->id,
                'payment_method_id'  => $form['payment_method_id'],
                'amount'             => $form['value'],
            ]);
        }
    }

    private function registerMovements(
        Document $document,
        array $paymentForms,
        string $direction,
        string $description,
        ?string $reference = null,
        ?string $fallbackCashBoxId = null
    ): array {
        $this->syncDocumentPaymentMethods($document, $paymentForms);

        $forms = $this->normalizedForms($paymentForms);
        $totals = ['cash' => 0.0, 'card' => 0.0, 'transfer' => 0.0];
        $today = now()->toDateString();
        $receipt = $direction === 'in'
            ? $this->createCashReceipt($document, $forms, $description)
            : $this->createPaymentReceipt($document, $forms);

        foreach ($forms as $form) {
            $amount = $form['value'];
            if ($amount <= 0) {
                continue;
            }

            $methodId = $form['payment_method_id'];
            $detail = trim((string) ($form['transaction_reference'] ?? ''));
            $movementDescription = $detail !== '' ? "{$description} ({$detail})" : $description;

            if ($methodId === self::CASH_METHOD_ID) {
                $totals['cash'] += $amount;
                $cashBoxId = $form['cash_box_id'] ?? $fallbackCashBoxId ?? CashBox::getMain()?->id;

                if ($cashBoxId) {
                    CashMovement::create([
                        'cash_box_id'    => $cashBoxId,
                        'user_id'        => $document->user_id,
                        'third_party_id' => $document->third_party_id,
                        'document_id'    => $document->id,
                        'movementable_id' => $receipt->id,
                        'movementable_type' => $receipt::class,
                        'debit'          => $direction === 'in' ? $amount : 0,
                        'credit'         => $direction === 'out' ? $amount : 0,
                        'issue_date'     => $today,
                        'description'    => $movementDescription,
                        'reference'      => $reference,
                        'state'          => true,
                    ]);
                }

                $this->createReceiptDetail($receipt, $document, $form, $direction, $amount);

                continue;
            }

            if (in_array($methodId, [48, 49], true)) {
                $totals['card'] += $amount;
            } else {
                $totals['transfer'] += $amount;
            }

            $bankAccountId = $form['bank_account_id'] ?? BankAccount::where('state', true)->value('id');

            if ($bankAccountId) {
                BankAccountMovement::create([
                    'bank_account_id' => $bankAccountId,
                    'user_id'         => $document->user_id,
                    'third_party_id'  => $document->third_party_id,
                    'document_id'     => $document->id,
                    'movementable_id' => $receipt->id,
                    'movementable_type' => $receipt::class,
                    'debit'           => $direction === 'in' ? $amount : 0,
                    'credit'          => $direction === 'out' ? $amount : 0,
                    'issue_date'      => $today,
                    'description'     => $movementDescription,
                    'reference'       => $reference,
                    'state'           => true,
                ]);
            }

            $this->createReceiptDetail($receipt, $document, $form, $direction, $amount);
        }

        if ($direction === 'in' && $receipt instanceof CashReceipt) {
            $this->generateCashReceiptAccounting($receipt);
        }

        if ($direction === 'out' && $receipt instanceof PaymentReceipt) {
            $this->generatePaymentReceiptAccounting($receipt);
        }

        return $totals;
    }

    private function buildRefundFormsFromOriginal(Document $original, float $refundAmount): array
    {
        $forms = [];
        $remaining = $refundAmount;

        foreach ($this->normalizedForms($original->payment_forms ?? []) as $form) {
            if ($remaining <= 0) {
                break;
            }

            $amount = min($form['value'], $remaining);
            $forms[] = array_merge($form, ['value' => $amount]);
            $remaining -= $amount;
        }

        if ($remaining > 0) {
            $forms[] = [
                'payment_form_id' => 1,
                'payment_method_id' => self::CASH_METHOD_ID,
                'value' => $remaining,
            ];
        }

        return $forms;
    }

    private function capPaymentsToDocumentTotal(array $paymentForms, float $documentTotal): array
    {
        $remaining = $documentTotal;

        return collect($paymentForms)
            ->map(function (array $form) use (&$remaining) {
                $value = (float) ($form['value'] ?? $form['amount'] ?? 0);
                $applied = min($value, max(0, $remaining));
                $remaining -= $applied;

                $form['value'] = $applied;

                return $form;
            })
            ->filter(fn (array $form) => (float) ($form['value'] ?? 0) > 0)
            ->values()
            ->all();
    }

    private function normalizedForms(array $paymentForms): array
    {
        return collect($paymentForms)
            ->map(function (array $form) {
                $value = $form['value'] ?? $form['amount'] ?? 0;

                return [
                    'payment_form_id'       => (int) ($form['payment_form_id'] ?? 1),
                    'payment_method_id'     => (int) ($form['payment_method_id'] ?? self::CASH_METHOD_ID),
                    'value'                 => (float) $value,
                    'cash_box_id'           => $form['cash_box_id'] ?? null,
                    'bank_account_id'       => $form['bank_account_id'] ?? null,
                    'transaction_reference' => $form['transaction_reference'] ?? null,
                ];
            })
            ->filter(fn (array $form) => $form['value'] > 0)
            ->values()
            ->all();
    }

    private function createCashReceiptDetailsAndApplyBalance(CashReceipt $receipt, array $allocations, array $forms): void
    {
        $firstForm = $forms[0] ?? ['payment_form_id' => 1, 'transaction_reference' => null];

        if (empty($allocations)) {
            CashReceiptDetail::create([
                'cash_receipt_id' => $receipt->id,
                'document_id' => null,
                'transaction_reference' => $firstForm['transaction_reference'] ?? null,
                'withholdings_tax' => 0,
                'quantity' => 1,
                'payment_form_id' => $firstForm['payment_form_id'] ?? 1,
                'currency_id' => 35,
                'amount' => $receipt->amount_received,
            ]);

            return;
        }

        foreach ($allocations as $allocation) {
            $amount = (float) ($allocation['amount'] ?? 0);
            if ($amount <= 0 || empty($allocation['document_id'])) {
                continue;
            }

            $document = Document::lockForUpdate()->findOrFail($allocation['document_id']);
            $amount = min($amount, (float) $document->balance);

            if ($amount <= 0) {
                continue;
            }

            CashReceiptDetail::create([
                'cash_receipt_id' => $receipt->id,
                'document_id' => $document->id,
                'transaction_reference' => $allocation['transaction_reference'] ?? $firstForm['transaction_reference'] ?? null,
                'withholdings_tax' => (float) ($allocation['withholdings_tax'] ?? 0),
                'quantity' => 1,
                'payment_form_id' => $firstForm['payment_form_id'] ?? 1,
                'currency_id' => $document->currency_id ?? 35,
                'amount' => $amount,
            ]);

            $newBalance = max(0, (float) $document->balance - $amount);
            $document->update([
                'balance' => $newBalance,
                'paid' => $newBalance <= 0,
            ]);
        }
    }

    private function createPaymentReceiptDetailsAndApplyBalance(PaymentReceipt $receipt, array $allocations, array $forms): void
    {
        $firstForm = $forms[0] ?? ['payment_form_id' => 1, 'transaction_reference' => null];

        if (empty($allocations)) {
            PaymentReceiptDetail::create([
                'payment_receipt_id' => $receipt->id,
                'document_id' => null,
                'transaction_reference' => $firstForm['transaction_reference'] ?? null,
                'withholdings_tax' => 0,
                'quantity' => 1,
                'payment_form_id' => $firstForm['payment_form_id'] ?? 1,
                'currency_id' => 35,
                'amount' => $receipt->amount_received,
                'refunded_amount' => 0,
            ]);

            return;
        }

        foreach ($allocations as $allocation) {
            $amount = (float) ($allocation['amount'] ?? 0);
            if ($amount <= 0 || empty($allocation['document_id'])) {
                continue;
            }

            $document = Document::lockForUpdate()->findOrFail($allocation['document_id']);
            $amount = min($amount, (float) $document->balance);

            if ($amount <= 0) {
                continue;
            }

            $detail = PaymentReceiptDetail::create([
                'payment_receipt_id' => $receipt->id,
                'document_id' => $document->id,
                'transaction_reference' => $allocation['transaction_reference'] ?? $firstForm['transaction_reference'] ?? null,
                'withholdings_tax' => (float) ($allocation['withholdings_tax'] ?? 0),
                'quantity' => 1,
                'payment_form_id' => $firstForm['payment_form_id'] ?? 1,
                'currency_id' => $document->currency_id ?? 35,
                'amount' => $amount,
                'refunded_amount' => 0,
            ]);

            DocumentPaymentReceipt::create([
                'payment_receipt_id' => $receipt->id,
                'document_id' => $document->id,
                'payment_receipt_detail_id' => $detail->id,
                'amount' => $amount,
            ]);

            $newBalance = max(0, (float) $document->balance - $amount);
            $document->update([
                'balance' => $newBalance,
                'paid' => $newBalance <= 0,
            ]);
        }
    }

    private function registerLooseMovements(
        CashReceipt|PaymentReceipt $receipt,
        array $forms,
        string $direction,
        string $description,
        string $reference
    ): void {
        foreach ($forms as $form) {
            $amount = (float) ($form['value'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $detail = trim((string) ($form['transaction_reference'] ?? ''));
            $movementDescription = $detail !== '' ? "{$description} ({$detail})" : $description;

            if ((int) $form['payment_method_id'] === self::CASH_METHOD_ID) {
                $cashBoxId = $form['cash_box_id'] ?? CashBox::getMain()?->id;

                if ($cashBoxId) {
                    CashMovement::create([
                        'cash_box_id' => $cashBoxId,
                        'user_id' => $receipt->user_id,
                        'third_party_id' => $receipt->third_party_id,
                        'movementable_id' => $receipt->id,
                        'movementable_type' => $receipt::class,
                        'debit' => $direction === 'in' ? $amount : 0,
                        'credit' => $direction === 'out' ? $amount : 0,
                        'issue_date' => $receipt->issue_date,
                        'description' => $movementDescription,
                        'reference' => $reference,
                        'state' => true,
                    ]);
                }

                continue;
            }

            $bankAccountId = $form['bank_account_id'] ?? BankAccount::where('state', true)->value('id');
            if ($bankAccountId) {
                BankAccountMovement::create([
                    'bank_account_id' => $bankAccountId,
                    'user_id' => $receipt->user_id,
                    'third_party_id' => $receipt->third_party_id,
                    'movementable_id' => $receipt->id,
                    'movementable_type' => $receipt::class,
                    'debit' => $direction === 'in' ? $amount : 0,
                    'credit' => $direction === 'out' ? $amount : 0,
                    'issue_date' => $receipt->issue_date,
                    'description' => $movementDescription,
                    'reference' => $reference,
                    'state' => true,
                ]);
            }
        }
    }

    private function createCashReceipt(Document $document, array $forms, string $description): CashReceipt
    {
        $total = collect($forms)->sum('value');
        $concept = $this->incomeExpenseConcept('ING-VENTAS', 'Recaudo de ventas y cartera', 13);

        return CashReceipt::create([
            'type_document_operation_id' => 13,
            'user_id' => $document->user_id,
            'third_party_id' => $document->third_party_id,
            'income_and_expense_id' => $concept->id,
            'prefix' => 'RC',
            'total_amount' => $total,
            'amount_received' => $total,
            'notes' => $description,
            'annulled' => false,
            'issue_date' => now()->toDateString(),
        ]);
    }

    private function createPaymentReceipt(Document $document, array $forms): PaymentReceipt
    {
        $total = collect($forms)->sum('value');
        $concept = $this->incomeExpenseConcept('EGR-DEVOLUCIONES', 'Devoluciones a clientes', 14);

        return PaymentReceipt::create([
            'type_document_operation_id' => 14,
            'user_id' => $document->user_id,
            'third_party_id' => $document->third_party_id,
            'income_and_expense_id' => $concept->id,
            'prefix' => 'PG',
            'total_amount' => $total,
            'amount_received' => $total,
            'is_paid_credit' => false,
            'payment_forms' => $forms,
            'issue_date' => now()->toDateString(),
        ]);
    }

    private function createReceiptDetail(
        CashReceipt|PaymentReceipt $receipt,
        Document $document,
        array $form,
        string $direction,
        float $amount
    ): void {
        $detailData = [
            'document_id' => $document->id,
            'transaction_reference' => $form['transaction_reference'] ?? null,
            'withholdings_tax' => 0,
            'quantity' => 1,
            'payment_form_id' => $form['payment_form_id'] ?? 1,
            'currency_id' => $document->currency_id ?? 35,
            'amount' => $amount,
        ];

        if ($direction === 'in' && $receipt instanceof CashReceipt) {
            CashReceiptDetail::create([
                ...$detailData,
                'cash_receipt_id' => $receipt->id,
            ]);

            return;
        }

        if ($direction === 'out' && $receipt instanceof PaymentReceipt) {
            $detail = PaymentReceiptDetail::create([
                ...$detailData,
                'payment_receipt_id' => $receipt->id,
                'refunded_amount' => $amount,
            ]);

            DocumentPaymentReceipt::create([
                'payment_receipt_id' => $receipt->id,
                'document_id' => $document->id,
                'payment_receipt_detail_id' => $detail->id,
                'amount' => $amount,
            ]);
        }
    }

    private function incomeExpenseConcept(string $code, string $name, int $typeOperationId): IncomeAndExpense
    {
        return IncomeAndExpense::firstOrCreate(
            ['internal_code' => $code],
            [
                'name' => $name,
                'description' => $name,
                'type_document_operation_id' => $typeOperationId,
            ]
        );
    }
}
