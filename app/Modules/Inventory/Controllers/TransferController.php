<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemWarehouse;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Models\TransferHistory;
use App\Modules\Inventory\Models\TransferItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    /**
     * Lista de traslados con filtros básicos.
     */
    public function index(Request $request): Response
    {
        $query = Transfer::with(['warehouseOrigin', 'warehouseDestination', 'user'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('transfer_date', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('transfer_date', '<=', $to);
        }

        if ($wh = $request->input('warehouse_id')) {
            $query->where(function ($q) use ($wh) {
                $q->where('warehouse_origin_id', $wh)
                  ->orWhere('warehouse_destination_id', $wh);
            });
        }

        return Inertia::render('Inventory/Transfers/Index', [
            'transfers'  => $query->paginate(25)->withQueryString(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'internal_code']),
            'filters'    => $request->only(['status', 'date_from', 'date_to', 'warehouse_id']),
        ]);
    }

    /**
     * Formulario de nuevo traslado.
     */
    public function create(): Response
    {
        return Inertia::render('Inventory/Transfers/Form', [
            'transfer'   => null,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'name', 'internal_code']),
            'items'      => Item::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'internal_code', 'name', 'average_cost']),
        ]);
    }

    /**
     * Guarda un traslado en estado borrador.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_origin_id'      => 'required|uuid|exists:warehouses,id|different:warehouse_destination_id',
            'warehouse_destination_id' => 'required|uuid|exists:warehouses,id',
            'transfer_date'            => 'required|date',
            'notes'                    => 'nullable|string|max:500',
            'items'                    => 'required|array|min:1',
            'items.*.item_id'          => 'required|uuid|exists:items,id',
            'items.*.quantity'         => 'required|numeric|min:0.001',
            'items.*.cost'             => 'nullable|numeric|min:0',
        ]);

        // Validar stock disponible en bodega origen
        foreach ($data['items'] as $line) {
            $stock = ItemWarehouse::where('item_id', $line['item_id'])
                ->where('warehouse_id', $data['warehouse_origin_id'])
                ->value('stock') ?? 0;

            if ((float) $line['quantity'] > (float) $stock) {
                $item = Item::find($line['item_id']);
                return back()->withErrors([
                    'items' => "Stock insuficiente para '{$item->name}'. Disponible: {$stock}",
                ]);
            }
        }

        DB::transaction(function () use ($data, $request) {
            $subtotal = collect($data['items'])->sum(fn ($l) => $l['quantity'] * ($l['cost'] ?? 0));

            $transfer = Transfer::create([
                'uuid'                     => Str::uuid(),
                'warehouse_origin_id'      => $data['warehouse_origin_id'],
                'warehouse_destination_id' => $data['warehouse_destination_id'],
                'user_id'                  => $request->user()->id,
                'status'                   => Transfer::STATUS_DRAFT,
                'transfer_date'            => $data['transfer_date'],
                'notes'                    => $data['notes'] ?? null,
                'subtotal'                 => $subtotal,
                'total'                    => $subtotal,
            ]);

            foreach ($data['items'] as $line) {
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'item_id'     => $line['item_id'],
                    'quantity'    => $line['quantity'],
                    'cost'        => $line['cost'] ?? 0,
                    'line_total'  => $line['quantity'] * ($line['cost'] ?? 0),
                ]);
            }

            TransferHistory::create([
                'transfer_id' => $transfer->id,
                'user_id'     => $request->user()->id,
                'action'      => 'Traslado creado en borrador',
                'action_date' => now(),
            ]);

            return $transfer;
        });

        return redirect()->route('inventory.transfers.index')
            ->with('success', 'Traslado creado correctamente.');
    }

    /**
     * Detalle de un traslado.
     */
    public function show(Transfer $transfer): Response
    {
        $transfer->load(['warehouseOrigin', 'warehouseDestination', 'user', 'items.item', 'histories.user']);

        // Stock actual en bodega origen por ítem (para mostrar disponibilidad)
        $stockOrigin = ItemWarehouse::where('warehouse_id', $transfer->warehouse_origin_id)
            ->whereIn('item_id', $transfer->items->pluck('item_id'))
            ->get(['item_id', 'stock'])
            ->keyBy('item_id');

        return Inertia::render('Inventory/Transfers/Show', [
            'transfer'    => $transfer,
            'stockOrigin' => $stockOrigin,
        ]);
    }

    /**
     * Despacha el traslado: descuenta stock en origen.
     * Estado: draft → in_transit
     */
    public function dispatch(Request $request, Transfer $transfer): RedirectResponse
    {
        if ($transfer->status !== Transfer::STATUS_DRAFT) {
            return back()->withErrors(['transfer' => 'Solo se pueden despachar traslados en estado borrador.']);
        }

        DB::transaction(function () use ($transfer, $request) {
            foreach ($transfer->items as $line) {
                // Descontar stock en bodega origen
                $this->adjustStock($line->item_id, $transfer->warehouse_origin_id, -(float) $line->quantity, (float) $line->cost);
            }

            $transfer->update(['status' => Transfer::STATUS_TRANSIT]);

            TransferHistory::create([
                'transfer_id' => $transfer->id,
                'user_id'     => $request->user()->id,
                'action'      => 'Traslado despachado — stock descontado en bodega origen',
                'action_date' => now(),
            ]);
        });

        return back()->with('success', 'Traslado despachado. En tránsito hacia bodega destino.');
    }

    /**
     * Recibe el traslado: agrega stock en destino.
     * Estado: in_transit → received
     */
    public function receive(Request $request, Transfer $transfer): RedirectResponse
    {
        if ($transfer->status !== Transfer::STATUS_TRANSIT) {
            return back()->withErrors(['transfer' => 'Solo se pueden recibir traslados en tránsito.']);
        }

        DB::transaction(function () use ($transfer, $request) {
            foreach ($transfer->items as $line) {
                // Agregar stock en bodega destino
                $this->adjustStock($line->item_id, $transfer->warehouse_destination_id, (float) $line->quantity, (float) $line->cost);
            }

            $transfer->update(['status' => Transfer::STATUS_RECEIVED]);

            TransferHistory::create([
                'transfer_id' => $transfer->id,
                'user_id'     => $request->user()->id,
                'action'      => 'Traslado recibido — stock ingresado en bodega destino',
                'action_date' => now(),
            ]);
        });

        return back()->with('success', 'Traslado recibido. Stock actualizado en bodega destino.');
    }

    /**
     * Cancela un traslado borrador o en tránsito.
     * Si estaba en tránsito, revierte el stock en origen.
     */
    public function cancel(Request $request, Transfer $transfer): RedirectResponse
    {
        if ($transfer->status === Transfer::STATUS_RECEIVED) {
            return back()->withErrors(['transfer' => 'No se puede cancelar un traslado ya recibido.']);
        }

        if ($transfer->status === Transfer::STATUS_CANCELLED) {
            return back()->withErrors(['transfer' => 'El traslado ya está cancelado.']);
        }

        DB::transaction(function () use ($transfer, $request) {
            // Si estaba en tránsito, devolver stock a origen
            if ($transfer->status === Transfer::STATUS_TRANSIT) {
                foreach ($transfer->items as $line) {
                    $this->adjustStock($line->item_id, $transfer->warehouse_origin_id, (float) $line->quantity, (float) $line->cost);
                }
            }

            $transfer->update(['status' => Transfer::STATUS_CANCELLED]);

            TransferHistory::create([
                'transfer_id' => $transfer->id,
                'user_id'     => $request->user()->id,
                'action'      => 'Traslado cancelado' . ($transfer->status === Transfer::STATUS_TRANSIT ? ' — stock revertido en bodega origen' : ''),
                'notes'       => $request->input('notes'),
                'action_date' => now(),
            ]);
        });

        return back()->with('success', 'Traslado cancelado.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Ajusta stock en item_warehouse (crea el registro si no existe).
     * quantity positivo = entrada, negativo = salida.
     */
    private function adjustStock(string $itemId, string $warehouseId, float $qty, float $cost): void
    {
        $iw = ItemWarehouse::firstOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            ['stock' => 0, 'average_cost' => $cost, 'state' => true]
        );

        $newStock = max(0, (float) $iw->stock + $qty);

        // Actualizar costo promedio ponderado en entrada
        if ($qty > 0 && $cost > 0) {
            $totalValue = ((float) $iw->stock * (float) $iw->average_cost) + ($qty * $cost);
            $iw->average_cost = $newStock > 0 ? $totalValue / $newStock : $cost;
        }

        $iw->stock = $newStock;
        $iw->save();
    }
}
