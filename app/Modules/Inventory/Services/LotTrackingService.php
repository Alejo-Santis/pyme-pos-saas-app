<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ItemLot;
use App\Modules\Inventory\Models\ItemLotMovement;
use Illuminate\Support\Facades\Log;

/**
 * Gestión de lotes y números de serie de inventario.
 *
 * Registra movimientos de trazabilidad cuando se crean facturas, compras
 * o traslados que involucran productos con tracking_type != 'none'.
 */
class LotTrackingService
{
    /**
     * Registra una entrada de lote (compra, ajuste, traslado IN).
     *
     * @param array $lotData ['lot_number', 'quantity', 'expiry_date'?, 'manufacture_date'?]
     */
    public function registerIn(
        string  $itemId,
        string  $warehouseId,
        string  $trackingType,
        array   $lotData,
        ?string $documentId = null
    ): ?ItemLot {
        try {
            $lot = ItemLot::firstOrCreate(
                [
                    'item_id'       => $itemId,
                    'warehouse_id'  => $warehouseId,
                    'lot_number'    => $lotData['lot_number'],
                ],
                [
                    'tracking_type'    => $trackingType,
                    'quantity'         => 0,
                    'expiry_date'      => $lotData['expiry_date'] ?? null,
                    'manufacture_date' => $lotData['manufacture_date'] ?? null,
                    'status'           => ItemLot::STATUS_ACTIVE,
                ]
            );

            $qty = (float) ($lotData['quantity'] ?? 1);
            $lot->increment('quantity', $qty);

            ItemLotMovement::create([
                'item_lot_id'   => $lot->id,
                'document_id'   => $documentId,
                'movement_type' => 'IN',
                'quantity'      => $qty,
            ]);

            return $lot;
        } catch (\Throwable $e) {
            Log::warning("LotTrackingService: error en entrada de lote [{$itemId}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra una salida de lote (venta, nota crédito, traslado OUT).
     * Si la cantidad del lote llega a 0, lo marca como consumido.
     */
    public function registerOut(
        string  $itemId,
        string  $warehouseId,
        string  $lotNumber,
        float   $quantity,
        ?string $documentId = null
    ): bool {
        try {
            $lot = ItemLot::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('lot_number', $lotNumber)
                ->where('status', ItemLot::STATUS_ACTIVE)
                ->first();

            if (! $lot) {
                Log::warning("LotTrackingService: lote no encontrado [{$lotNumber}] para item [{$itemId}]");
                return false;
            }

            $newQty = max(0, (float) $lot->quantity - $quantity);
            $lot->update([
                'quantity' => $newQty,
                'status'   => $newQty <= 0 ? ItemLot::STATUS_CONSUMED : ItemLot::STATUS_ACTIVE,
            ]);

            ItemLotMovement::create([
                'item_lot_id'   => $lot->id,
                'document_id'   => $documentId,
                'movement_type' => 'OUT',
                'quantity'      => $quantity,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning("LotTrackingService: error en salida de lote [{$lotNumber}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca lotes vencidos como 'expired'.
     * Llamar desde un comando diario programado.
     */
    public static function markExpiredLots(): int
    {
        return ItemLot::where('status', ItemLot::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->update(['status' => ItemLot::STATUS_EXPIRED]);
    }

    /**
     * Lotes próximos a vencer (para notificaciones).
     */
    public static function expiringSoon(int $days = 30): \Illuminate\Support\Collection
    {
        return ItemLot::with(['item', 'warehouse'])
            ->expiringSoon($days)
            ->get();
    }
}
