<?php

namespace App\Shared\Traits;

use App\Modules\Accounting\Models\AccountingConcept;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
use App\Modules\Cash\Models\BankAccountMovement;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashReceipt;
use App\Modules\Cash\Models\PaymentReceipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Motor Contable Genérico.
 *
 * Genera asientos contables automáticos para facturas, compras y NC.
 * Regla de oro: Σ Débito = Σ Crédito en cada comprobante.
 *
 * Uso: incluir el trait en InvoiceService, PurchaseService, CreditNoteService.
 *
 * Conceptos del PUC Colombia (códigos a configurar por empresa):
 *
 *  VENTA (op 1):
 *    1_CXC        → 1305 (Clientes por cobrar — DÉBITO)
 *    1_INGRESO    → 4135 (Ingresos por venta — CRÉDITO)
 *    1_IVA_GEN    → 2408 (IVA generado — CRÉDITO)
 *    1_COSTO      → 6135 (Costo de ventas — DÉBITO)
 *    1_INV_SALIDA → 1435 (Inventario mercancías — CRÉDITO)
 *
 *  COMPRA (op 14):
 *    14_INVENTARIO → 1435 (Inventario mercancías — DÉBITO)
 *    14_IVA_DESC   → 2408 (IVA descontable — DÉBITO)
 *    14_CXP        → 2205 (Cuentas por pagar proveedores — CRÉDITO)
 *
 *  NOTA CRÉDITO (op 91):
 *    91_CXC        → 1305 (CRÉDITO — reduce saldo cliente)
 *    91_INGRESO    → 4135 (DÉBITO — reduce ingresos)
 *    91_IVA_GEN    → 2408 (DÉBITO — reduce IVA)
 *    91_INV_ENTRA  → 1435 (DÉBITO — reingresa inventario)
 *    91_COSTO      → 6135 (CRÉDITO — reduce costo)
 *
 *  NOTA DÉBITO (op 92):
 *    92_CXC        → 1305 (DÉBITO — aumenta saldo cliente)
 *    92_INGRESO    → 4135 (CRÉDITO — mayor ingreso/cargo)
 *    92_IVA_GEN    → 2408 (CRÉDITO — mayor IVA generado)
 */
trait AccountingEngineTrait
{
    /**
     * Punto de entrada principal. Detecta el tipo de documento y genera el asiento.
     * Captura errores de contabilidad sin bloquear el flujo de negocio.
     */
    public function generateAccountingEntry($document): ?AccountingDocument
    {
        try {
            $opId = (int) $document->type_document_operation_id;

            $voucher = AccountingDocument::create([
                'uuid'                       => Str::uuid(),
                'internal_code'              => 'COMP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5)),
                'user_id'                    => $document->user_id,
                'third_party_id'             => $document->third_party_id,
                'document_id'                => $document->id,
                'type_document_operation_id' => $opId,
                'total'                      => $document->total,
                'debit'                      => 0,
                'credit'                     => 0,
                'issue_date'                 => $document->issue_date ?? now()->toDateString(),
                'annulled'                   => false,
            ]);

            match ($opId) {
                1       => $this->entrySale($document, $voucher),
                14      => $this->entryPurchase($document, $voucher),
                91      => $this->entryCreditNote($document, $voucher),
                92      => $this->entryDebitNote($document, $voucher),
                default => Log::info("Motor contable: tipo {$opId} sin asiento definido"),
            };

            // Recargar totales acumulados
            $voucher->refresh();

            // Validar cuadre (warn pero no bloquear)
            if (abs((float) $voucher->debit - (float) $voucher->credit) > 0.01) {
                Log::warning("Asiento descuadrado en documento {$document->id}: " .
                    "D={$voucher->debit} C={$voucher->credit}");
            }

            return $voucher;

        } catch (\Throwable $e) {
            // La contabilidad NO debe bloquear la operación comercial
            Log::error("Error motor contable para documento {$document->id}: " . $e->getMessage());
            return null;
        }
    }

    public function generateCashReceiptAccounting(CashReceipt $receipt): ?AccountingDocument
    {
        try {
            $receipt->loadMissing('details.document');

            $voucher = $this->createReceiptVoucher($receipt, 13);
            if ($voucher->lines()->exists()) {
                return $voucher;
            }

            $this->entryCashOrBankFromMovements($receipt, $voucher, 13, 'debit');

            $allocated = 0.0;
            foreach ($receipt->details as $detail) {
                $amount = (float) $detail->amount;
                if ($amount <= 0) {
                    continue;
                }

                $allocated += $amount;
                $this->addLine($voucher, $receipt, 13, 'CXC', 0, $amount, $detail->document?->internal_code);
            }

            $unallocated = max(0, (float) $receipt->amount_received - $allocated);
            if ($unallocated > 0) {
                $this->addLine($voucher, $receipt, 13, 'ANTICIPO_CLIENTE', 0, $unallocated);
            }

            return $this->refreshAndWarnIfUnbalanced($voucher, "recibo {$receipt->internal_code}");
        } catch (\Throwable $e) {
            Log::error("Error motor contable para recibo {$receipt->id}: " . $e->getMessage());
            return null;
        }
    }

    public function generatePaymentReceiptAccounting(PaymentReceipt $receipt): ?AccountingDocument
    {
        try {
            $receipt->loadMissing('details.document');

            $voucher = $this->createReceiptVoucher($receipt, 14);
            if ($voucher->lines()->exists()) {
                return $voucher;
            }

            $allocated = 0.0;
            foreach ($receipt->details as $detail) {
                $amount = (float) $detail->amount;
                if ($amount <= 0) {
                    continue;
                }

                $allocated += $amount;
                $opId = (int) ($detail->document?->type_document_operation_id ?? 0);
                $slug = $opId === 91 ? 'CXC' : (in_array($opId, [5, 14], true) ? 'CXP' : 'GASTO');
                $this->addLine($voucher, $receipt, 14, $slug, $amount, 0, $detail->document?->internal_code);
            }

            $unallocated = max(0, (float) $receipt->amount_received - $allocated);
            if ($unallocated > 0) {
                $this->addLine($voucher, $receipt, 14, 'GASTO', $unallocated, 0);
            }

            $this->entryCashOrBankFromMovements($receipt, $voucher, 14, 'credit');

            return $this->refreshAndWarnIfUnbalanced($voucher, "egreso {$receipt->internal_code}");
        } catch (\Throwable $e) {
            Log::error("Error motor contable para egreso {$receipt->id}: " . $e->getMessage());
            return null;
        }
    }

    // ─── Asientos por tipo de operación ───────────────────────────────────

    /**
     * Factura de Venta (op 1)
     * Db: CXC | Cr: Ingreso + IVA generado
     * Db: Costo venta | Cr: Inventario (si hay costo)
     */
    private function entrySale($doc, AccountingDocument $voucher): void
    {
        $opId      = 1;
        $subtotal  = (float) ($doc->subtotal ?? 0);
        $totalTax  = (float) ($doc->total_tax ?? 0);
        $total     = (float) $doc->total;
        $costOfSale = $this->calcCostOfSale($doc);

        // Débito: CXC cliente
        $this->addLine($voucher, $doc, $opId, 'CXC', $total, 0);

        // Crédito: Ingreso operacional
        $this->addLine($voucher, $doc, $opId, 'INGRESO', 0, $subtotal);

        // Crédito: IVA generado
        if ($totalTax > 0) {
            $this->addLine($voucher, $doc, $opId, 'IVA_GEN', 0, $totalTax);
        }

        // Costo de ventas (solo si hay ítems con costo)
        if ($costOfSale > 0) {
            $this->addLine($voucher, $doc, $opId, 'COSTO', $costOfSale, 0);
            $this->addLine($voucher, $doc, $opId, 'INV_SALIDA', 0, $costOfSale);
        }
    }

    /**
     * Compra (op 14)
     * Db: Inventario + IVA descontable | Cr: CXP proveedor
     */
    private function entryPurchase($doc, AccountingDocument $voucher): void
    {
        $opId     = 14;
        $subtotal = (float) ($doc->subtotal ?? 0);
        $totalTax = (float) ($doc->total_tax ?? 0);
        $total    = (float) $doc->total;

        // Débito: Inventario
        $this->addLine($voucher, $doc, $opId, 'INVENTARIO', $subtotal, 0);

        // Débito: IVA descontable
        if ($totalTax > 0) {
            $this->addLine($voucher, $doc, $opId, 'IVA_DESC', $totalTax, 0);
        }

        // Crédito: CXP proveedor
        $this->addLine($voucher, $doc, $opId, 'CXP', 0, $total);
    }

    /**
     * Nota Crédito (op 91) — reversa de la venta
     * Cr: CXC | Db: Ingreso + IVA generado
     * Cr: Costo | Db: Inventario (reingresa)
     */
    private function entryCreditNote($doc, AccountingDocument $voucher): void
    {
        $opId      = 91;
        $subtotal  = (float) ($doc->subtotal ?? 0);
        $totalTax  = (float) ($doc->total_tax ?? 0);
        $total     = (float) $doc->total;
        $costOfSale = $this->calcCostOfSale($doc);

        // Crédito: CXC cliente (reduce saldo)
        $this->addLine($voucher, $doc, $opId, 'CXC', 0, $total);

        // Débito: Ingreso (reduce ingresos)
        $this->addLine($voucher, $doc, $opId, 'INGRESO', $subtotal, 0);

        // Débito: IVA generado (reduce IVA)
        if ($totalTax > 0) {
            $this->addLine($voucher, $doc, $opId, 'IVA_GEN', $totalTax, 0);
        }

        // Reversa costo
        if ($costOfSale > 0) {
            $this->addLine($voucher, $doc, $opId, 'INV_ENTRA', $costOfSale, 0);
            $this->addLine($voucher, $doc, $opId, 'COSTO', 0, $costOfSale);
        }
    }

    /**
     * Nota Débito (op 92) — cargo adicional sobre la venta.
     * Db: CXC | Cr: Ingreso + IVA generado.
     */
    private function entryDebitNote($doc, AccountingDocument $voucher): void
    {
        $opId     = 92;
        $subtotal = (float) ($doc->subtotal ?? 0);
        $totalTax = (float) ($doc->total_tax ?? 0);
        $total    = (float) $doc->total;

        $this->addLine($voucher, $doc, $opId, 'CXC', $total, 0);
        $this->addLine($voucher, $doc, $opId, 'INGRESO', 0, $subtotal);

        if ($totalTax > 0) {
            $this->addLine($voucher, $doc, $opId, 'IVA_GEN', 0, $totalTax);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Agrega una línea al comprobante y acumula en el header.
     */
    private function addLine(
        AccountingDocument $voucher,
        $doc,
        int    $opId,
        string $conceptSlug,
        float  $debit,
        float  $credit,
        ?string $documentNumber = null
    ): void {
        if ($debit <= 0 && $credit <= 0) return;

        // Buscar cuenta configurada en accounting_concepts
        $accountCode = AccountingConcept::getAccountCode($opId, $conceptSlug);

        // Si no hay cuenta configurada, usar código por defecto del PUC Colombia
        if (! $accountCode) {
            $accountCode = $this->defaultPucCode($opId, $conceptSlug);
        }

        AccountingDocumentDetail::create([
            'accounting_document_id' => $voucher->id,
            'accountable_id'         => $accountCode,
            'accountable_type'       => 'chart_account',
            'third_party_id'         => $doc->third_party_id ?? null,
            'document_number'        => $documentNumber ?? $doc->internal_code ?? null,
            'taxable_amount'         => max($debit, $credit),
            'debit'                  => round($debit, 4),
            'credit'                 => round($credit, 4),
            'issue_date'             => $doc->issue_date ?? now()->toDateString(),
        ]);

        // Acumular en el comprobante
        $voucher->increment('debit',  round($debit, 4));
        $voucher->increment('credit', round($credit, 4));
    }

    /**
     * Calcula el costo total de venta del documento a partir de sus líneas.
     */
    private function calcCostOfSale($doc): float
    {
        $doc->loadMissing('lines');
        return (float) $doc->lines->sum(fn ($l) => (float) $l->cost_value * (float) $l->amount);
    }

    private function createReceiptVoucher(CashReceipt|PaymentReceipt $receipt, int $opId): AccountingDocument
    {
        return AccountingDocument::firstOrCreate(
            ['internal_code' => 'COMP-' . $receipt->internal_code],
            [
                'uuid' => Str::uuid(),
                'user_id' => $receipt->user_id,
                'third_party_id' => $receipt->third_party_id,
                'document_id' => null,
                'type_document_operation_id' => $opId,
                'prefix' => $receipt->prefix,
                'number' => $receipt->number,
                'debit' => 0,
                'credit' => 0,
                'total' => $receipt->amount_received ?? $receipt->total_amount,
                'issue_date' => $receipt->issue_date ?? now()->toDateString(),
                'annulled' => false,
            ]
        );
    }

    private function entryCashOrBankFromMovements(CashReceipt|PaymentReceipt $receipt, AccountingDocument $voucher, int $opId, string $side): void
    {
        $cashMovements = CashMovement::where('movementable_type', $receipt::class)
            ->where('movementable_id', $receipt->id)
            ->where('state', true)
            ->get();

        foreach ($cashMovements as $movement) {
            $amount = (float) ($side === 'debit' ? $movement->debit : $movement->credit);
            $this->addLine(
                $voucher,
                $receipt,
                $opId,
                'CAJA',
                $side === 'debit' ? $amount : 0,
                $side === 'credit' ? $amount : 0
            );
        }

        $bankMovements = BankAccountMovement::where('movementable_type', $receipt::class)
            ->where('movementable_id', $receipt->id)
            ->where('state', true)
            ->get();

        foreach ($bankMovements as $movement) {
            $amount = (float) ($side === 'debit' ? $movement->debit : $movement->credit);
            $this->addLine(
                $voucher,
                $receipt,
                $opId,
                'BANCO',
                $side === 'debit' ? $amount : 0,
                $side === 'credit' ? $amount : 0
            );
        }
    }

    private function refreshAndWarnIfUnbalanced(AccountingDocument $voucher, string $source): AccountingDocument
    {
        $voucher->refresh();

        if (abs((float) $voucher->debit - (float) $voucher->credit) > 0.01) {
            Log::warning("Asiento descuadrado en {$source}: D={$voucher->debit} C={$voucher->credit}");
        }

        return $voucher;
    }

    /**
     * Códigos PUC Colombia por defecto (si no hay configuración en accounting_concepts).
     * La empresa puede sobreescribir estos valores desde la configuración.
     */
    private function defaultPucCode(int $opId, string $slug): string
    {
        $defaults = [
            // Ventas (op 1)
            '1_CXC'        => '13050501', // Clientes nacionales
            '1_INGRESO'    => '41351001', // Comercio al por mayor
            '1_IVA_GEN'    => '24080101', // IVA por pagar generado
            '1_COSTO'      => '61351001', // Costo de ventas
            '1_INV_SALIDA' => '14350101', // Mercancías no fabricadas

            // Compras (op 14)
            '14_INVENTARIO' => '14350101', // Mercancías no fabricadas
            '14_IVA_DESC'   => '24080501', // IVA descontable
            '14_CXP'        => '22050101', // Proveedores nacionales

            // NC (op 91)
            '91_CXC'       => '13050501',
            '91_INGRESO'   => '41351001',
            '91_IVA_GEN'   => '24080101',
            '91_INV_ENTRA' => '14350101',
            '91_COSTO'     => '61351001',

            // ND (op 92)
            '92_CXC'     => '13050501',
            '92_INGRESO' => '41351001',
            '92_IVA_GEN' => '24080101',

            // Recibos de caja (op 13)
            '13_CAJA'             => '11050501',
            '13_BANCO'            => '11100501',
            '13_CXC'              => '13050501',
            '13_ANTICIPO_CLIENTE' => '28050501',

            // Comprobantes de egreso (op 14 para comprobante de pago)
            '14_CAJA'  => '11050501',
            '14_BANCO' => '11100501',
            '14_CXC'   => '13050501',
            '14_GASTO' => '51959501',
        ];

        return $defaults["{$opId}_{$slug}"] ?? '999999'; // cuenta genérica si no existe
    }
}
