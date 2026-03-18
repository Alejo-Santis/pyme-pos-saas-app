<?php

namespace App\Modules\Purchases\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemWarehouse;
use App\Modules\Purchases\Models\ItemsPurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrder;
use App\Modules\Purchases\Models\PurchaseOrderHistory;
use App\Shared\Traits\AccountingEngineTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de compras.
 * Maneja creación de OC, aprobación, recepción de mercancía (entrada a bodega)
 * y anulación de órdenes de compra.
 */
class PurchaseService
{
    use AccountingEngineTrait;
    /**
     * Crea una orden de compra con sus líneas.
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $userId = Auth::id();

            // Calcular totales
            $lines  = $data['lines'] ?? [];
            $amount = collect($lines)->sum('line_extension_amount');

            $order = PurchaseOrder::create([
                'third_party_id' => $data['third_party_id'] ?? null,
                'user_id'        => $userId,
                'reference'      => $data['reference'] ?? null,
                'amount'         => $amount,
                'issue_date'     => $data['issue_date'],
                'notes'          => $data['notes'] ?? null,
                'status'         => 'draft',
            ]);

            // Líneas de la OC
            foreach ($lines as $line) {
                ItemsPurchaseOrder::create([
                    'purchase_order_id'     => $order->id,
                    'item_id'               => $line['item_id'],
                    'invoice_quantity'      => $line['invoice_quantity'],
                    'average_cost'          => $line['average_cost'],
                    'tax'                   => $line['tax'] ?? null,
                    'line_extension_amount' => $line['line_extension_amount'],
                ]);
            }

            // Historial inicial
            PurchaseOrderHistory::create([
                'purchase_order_id'  => $order->id,
                'user_id'            => $userId,
                'history_issue_date' => now(),
                'history'            => 'Orden de compra creada',
                'notes'              => $data['notes'] ?? null,
            ]);

            return $order;
        });
    }

    /**
     * Actualiza una orden de compra en estado draft.
     */
    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden editar órdenes en estado borrador.',
            ]);
        }

        return DB::transaction(function () use ($order, $data) {
            $lines  = $data['lines'] ?? [];
            $amount = collect($lines)->sum('line_extension_amount');

            $order->update([
                'third_party_id' => $data['third_party_id'] ?? $order->third_party_id,
                'reference'      => $data['reference'] ?? $order->reference,
                'amount'         => $amount,
                'issue_date'     => $data['issue_date'] ?? $order->issue_date,
                'notes'          => $data['notes'] ?? $order->notes,
            ]);

            // Reemplazar líneas
            $order->items()->delete();

            foreach ($lines as $line) {
                ItemsPurchaseOrder::create([
                    'purchase_order_id'     => $order->id,
                    'item_id'               => $line['item_id'],
                    'invoice_quantity'      => $line['invoice_quantity'],
                    'average_cost'          => $line['average_cost'],
                    'tax'                   => $line['tax'] ?? null,
                    'line_extension_amount' => $line['line_extension_amount'],
                ]);
            }

            PurchaseOrderHistory::create([
                'purchase_order_id'  => $order->id,
                'user_id'            => Auth::id(),
                'history_issue_date' => now(),
                'history'            => 'Orden de compra actualizada',
            ]);

            return $order->fresh();
        });
    }

    /**
     * Aprueba la orden para recepción de mercancía.
     */
    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        if (!in_array($order->status, ['draft', 'pending'])) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden aprobar órdenes en estado borrador o pendiente.',
            ]);
        }

        $order->update([
            'approved' => true,
            'status'   => 'approved',
            'approver_user_id' => Auth::id(),
        ]);

        PurchaseOrderHistory::create([
            'purchase_order_id'  => $order->id,
            'user_id'            => Auth::id(),
            'history_issue_date' => now(),
            'history'            => 'Orden de compra aprobada',
        ]);

        return $order;
    }

    /**
     * Recibe mercancía: ingresa stock a bodega y actualiza costo promedio.
     *
     * @param  array  $data  ['warehouse_id' => ..., 'lines' => [['item_id'=>..., 'received_quantity'=>...]]]
     */
    public function receive(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if (!in_array($order->status, ['approved', 'partial'])) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden recibir órdenes aprobadas.',
            ]);
        }

        DB::transaction(function () use ($order, $data) {
            $warehouseId = $data['warehouse_id'];
            $lines       = $data['lines'] ?? [];

            foreach ($lines as $received) {
                $qty  = (float) ($received['received_quantity'] ?? 0);
                $cost = (float) ($received['average_cost'] ?? 0);

                if ($qty <= 0) continue;

                $itemId = $received['item_id'];

                /** @var ItemWarehouse $iw */
                $iw = ItemWarehouse::firstOrCreate(
                    ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
                    ['stock' => 0, 'average_cost' => 0, 'state' => true]
                );

                // Costo promedio ponderado
                $oldStock       = (float) $iw->stock;
                $oldCost        = (float) $iw->average_cost;
                $newStock       = $oldStock + $qty;
                $newAverageCost = $newStock > 0
                    ? (($oldStock * $oldCost) + ($qty * $cost)) / $newStock
                    : $cost;

                $iw->update([
                    'stock'        => $newStock,
                    'average_cost' => round($newAverageCost, 4),
                ]);

                // Actualizar costo en el ítem
                Item::where('id', $itemId)->update(['average_cost' => round($newAverageCost, 4)]);
            }

            // Actualizar estado OC
            $order->update(['status' => 'received']);

            PurchaseOrderHistory::create([
                'purchase_order_id'  => $order->id,
                'user_id'            => Auth::id(),
                'history_issue_date' => now(),
                'history'            => 'Mercancía recibida en bodega',
                'notes'              => "Bodega ID: {$warehouseId}",
            ]);
        });

        // Generar asiento contable de compra (fuera de la transacción de stock)
        $order->load('items');
        $companyId = Company::first()?->id;
        if ($companyId) {
            $subtotal  = (float) $order->items->sum('line_extension_amount');
            $totalTax  = (float) $order->items->sum(function ($line) {
                $tax = is_array($line->tax) ? $line->tax : [];
                return collect($tax)->sum('tax_amount') ?? 0;
            });
            $accountingDoc = (object) [
                'id'                         => $order->id,
                'type_document_operation_id' => 14,
                'company_id'                 => $companyId,
                'user_id'                    => Auth::id(),
                'third_party_id'             => $order->third_party_id,
                'internal_code'              => $order->internal_code,
                'issue_date'                 => now()->toDateString(),
                'total'                      => $subtotal + $totalTax,
                'subtotal'                   => $subtotal,
                'total_tax'                  => $totalTax,
            ];
            $this->generateAccountingEntry($accountingDoc);
        }

        return $order->fresh();
    }

    /**
     * Anula la orden de compra (solo si no se ha recibido mercancía).
     */
    public function annul(PurchaseOrder $order, string $reason = ''): PurchaseOrder
    {
        if ($order->status === 'received') {
            throw ValidationException::withMessages([
                'status' => 'No se puede anular una orden con mercancía ya recibida.',
            ]);
        }

        $order->update([
            'annulled' => true,
            'status'   => 'cancelled',
        ]);

        PurchaseOrderHistory::create([
            'purchase_order_id'  => $order->id,
            'user_id'            => Auth::id(),
            'history_issue_date' => now(),
            'history'            => 'Orden de compra anulada',
            'notes'              => $reason,
        ]);

        return $order;
    }
}
