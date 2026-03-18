<?php

namespace App\Shared\Traits;

use App\Modules\Accounting\Models\AccountingConcept;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
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
                'company_id'                 => $document->company_id,
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
        float  $credit
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
            'document_number'        => $doc->internal_code ?? null,
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
        ];

        return $defaults["{$opId}_{$slug}"] ?? '999999'; // cuenta genérica si no existe
    }
}
