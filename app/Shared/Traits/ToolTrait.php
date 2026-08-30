<?php

namespace App\Shared\Traits;

use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Models\DocumentHistory;
use App\Modules\Inventory\Models\ItemWarehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helpers compartidos por Controllers, Services y Jobs.
 * Adaptado de xedoc-laravel-svelte/app/Traits/ToolTrait.php
 */
trait ToolTrait
{
    // ── Historial de documentos ───────────────────────────────────────────

    /**
     * Registra un evento en el historial del documento.
     */
    public function createDocumentHistory(Document $document, ?string $notes = null, string $history = 'Creación del documento.'): bool
    {
        try {
            DocumentHistory::create([
                'document_id'        => $document->id,
                'user_id'            => $document->user_id,
                'history_issue_date' => Carbon::now(),
                'notes'              => $notes,
                'history'            => $history,
            ]);

            return true;
        } catch (\Throwable $th) {
            Log::warning('Error al registrar historial de documento', [
                'document_id' => $document->id,
                'error'       => $th->getMessage(),
            ]);

            return false;
        }
    }

    // ── Inventario ────────────────────────────────────────────────────────

    /**
     * Actualiza stock y costo promedio en item_warehouse.
     * Método IN: aumenta stock. Método OUT: disminuye stock.
     */
    public function updateItemInventory(
        string $itemId,
        string $warehouseId,
        float  $quantity,
        float  $unitCost,
        string $movementType = 'OUT'
    ): void {
        // Bloqueo pesimista: sin esto, dos movimientos concurrentes sobre el
        // mismo item+bodega pueden leer el mismo stock, calcular por separado
        // y el segundo UPDATE pisa el resultado del primero (sobreventa).
        $pivot = ItemWarehouse::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $pivot) {
            try {
                $pivot = ItemWarehouse::create([
                    'item_id'       => $itemId,
                    'warehouse_id'  => $warehouseId,
                    'stock'         => 0,
                    'average_cost'  => 0,
                    'state'         => true,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Otra transacción concurrente ya creó la fila — la bloqueamos ahora.
                $pivot = ItemWarehouse::where('item_id', $itemId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $currentStock = (float) $pivot->stock;
        $currentAvg   = (float) $pivot->average_cost;

        if ($movementType === 'IN') {
            // Costo promedio ponderado
            $newStock = $currentStock + $quantity;
            if ($newStock > 0 && $unitCost > 0) {
                $newAvg = (($currentStock * $currentAvg) + ($quantity * $unitCost)) / $newStock;
            } else {
                $newAvg = $currentAvg;
            }
            $pivot->update(['stock' => $newStock, 'average_cost' => round($newAvg, 4)]);
        } else {
            // Salida — solo disminuye stock
            $newStock = max(0, $currentStock - $quantity);
            $pivot->update(['stock' => $newStock]);
        }
    }

    /**
     * Registra un movimiento en item_stocktakings (kardex).
     */
    public function updateItemStocktaking(
        Document $document,
        array    $detail,
        string   $movementType = 'OUT'
    ): void {
        try {
            $warehouseId = $movementType === 'IN'
                ? ($detail['warehouse_in']  ?? null)
                : ($detail['warehouse_out'] ?? null);

            if (! $warehouseId || ! ($detail['item_id'] ?? null)) {
                return;
            }

            $pivot = ItemWarehouse::where('item_id', $detail['item_id'])
                ->where('warehouse_id', $warehouseId)
                ->first();

            DB::table('item_stocktakings')->insert([
                'id'                  => \Illuminate\Support\Str::uuid(),
                'document_id'         => $document->id,
                'item_id'             => $detail['item_id'],
                'warehouse_id'        => $warehouseId,
                'input_quantity'      => $movementType === 'IN'  ? $detail['amount'] : 0,
                'output_quantity'     => $movementType === 'OUT' ? $detail['amount'] : 0,
                'purchase_price'      => $detail['cost_value'] ?? 0,
                'new_average'         => $pivot?->average_cost ?? 0,
                'description'         => $detail['description'] ?? null,
                'type_moviment'       => $movementType,
                'annulled'            => false,
                'state'               => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (\Throwable $th) {
            Log::warning('Error al registrar kardex', [
                'document_id' => $document->id,
                'error'       => $th->getMessage(),
            ]);
        }
    }

    // ── Verificación de dígito NIT ────────────────────────────────────────

    /**
     * Calcula el dígito de verificación de un NIT colombiano.
     */
    public function calculateVerificationDigit(string $identificationNumber): ?int
    {
        if (! is_numeric(trim($identificationNumber))) {
            return null;
        }

        $sequence = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
        $digits   = array_reverse(str_split(trim($identificationNumber)));
        $sum      = 0;

        foreach ($digits as $i => $digit) {
            $sum += (int) $digit * $sequence[$i];
        }

        $remainder = $sum % 11;

        return match (true) {
            $remainder === 0 => 0,
            $remainder === 1 => 1,
            default          => 11 - $remainder,
        };
    }
}
