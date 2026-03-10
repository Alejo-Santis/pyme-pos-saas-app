<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Catálogos globales (schema public) reutilizados en varias acciones.
     */
    private function catalogs(): array
    {
        return [
            'unitMeasures'       => DB::table('unit_measures')->orderBy('name')->get(['id', 'name', 'code']),
            'taxes'              => DB::table('taxes')->orderBy('name')->get(['id', 'name', 'code']),
            'itemClassifications'=> DB::table('item_clasification')->orderBy('id')->get(['id', 'name']),
            'categories'         => ItemCategory::with('parent')
                                        ->orderBy('name')
                                        ->get(['id', 'name', 'parent_id']),
        ];
    }

    /**
     * Listado paginado de ítems con filtros.
     */
    public function index(Request $request): Response
    {
        $items = Item::with('itemCategory')
            ->when($request->search, function ($q, $s) {
                $q->where(function ($inner) use ($s) {
                    $inner->where('name', 'ilike', "%{$s}%")
                          ->orWhere('internal_code', 'ilike', "%{$s}%")
                          ->orWhere('short_name', 'ilike', "%{$s}%");
                });
            })
            ->when($request->category_id, fn ($q, $c) => $q->where('item_category_id', $c))
            ->when($request->type,        fn ($q, $t) => $q->where('type', $t))
            ->when($request->status !== null && $request->status !== '', function ($q) use ($request) {
                $q->where('is_active', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Items', [
            'items'      => $items,
            'categories' => ItemCategory::orderBy('name')->get(['id', 'name', 'parent_id']),
            'filters'    => $request->only(['search', 'category_id', 'type', 'status']),
            'unitMeasures' => DB::table('unit_measures')->orderBy('name')->get(['id', 'name', 'code']),
            'taxes'      => DB::table('taxes')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Formulario de creación de ítem.
     */
    public function create(): Response
    {
        return Inertia::render('Inventory/ItemForm', array_merge(
            ['item' => null],
            $this->catalogs()
        ));
    }

    /**
     * Guarda un nuevo ítem.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'internal_code'      => 'nullable|string|max:100|unique:items',
            'name'               => 'required|string|max:255',
            'short_name'         => 'nullable|string|max:100',
            'reference'          => 'nullable|string|max:100',
            'note'               => 'nullable|string',
            'type'               => 'required|in:product,service,combo',
            'item_category_id'   => 'nullable|uuid|exists:item_categories,id',
            'clasification_id'   => 'nullable|integer',
            'tax_category_id'    => 'nullable|integer',
            'unit_measure_id'    => 'nullable|integer',
            'group_id'           => 'nullable|uuid|exists:item_groups,id',
            'line_id'            => 'nullable|uuid|exists:item_lines,id',
            'last_purchase_price'=> 'numeric|min:0',
            'average_cost'       => 'numeric|min:0',
            'default_sale_price' => 'numeric|min:0',
            'consumption_tax'    => 'numeric|min:0',
            'icui'               => 'numeric|min:0',
            'ibua'               => 'numeric|min:0',
            'double_taxes'       => 'boolean',
            'barcode_one'        => 'nullable|string|max:100',
            'barcode_two'        => 'nullable|string|max:100',
            'minimum_existence'  => 'numeric|min:0',
            'maximum_existence'  => 'numeric|min:0',
            'minor_account_id'   => 'nullable|string|max:50',
            'is_active'          => 'boolean',
            'manages_stock'      => 'boolean',
            'is_service'         => 'boolean',
        ]);

        Item::create($data);

        return redirect()->route('inventory.index')
            ->with('success', 'Ítem creado correctamente.');
    }

    /**
     * Formulario de edición de ítem.
     */
    public function edit(Item $item): Response
    {
        return Inertia::render('Inventory/ItemForm', array_merge(
            ['item' => $item->load('itemCategory')],
            $this->catalogs()
        ));
    }

    /**
     * Actualiza un ítem existente.
     */
    public function update(Request $request, Item $item)
    {
        $data = $request->validate([
            'internal_code'      => "nullable|string|max:100|unique:items,internal_code,{$item->id},id",
            'name'               => 'required|string|max:255',
            'short_name'         => 'nullable|string|max:100',
            'reference'          => 'nullable|string|max:100',
            'note'               => 'nullable|string',
            'type'               => 'required|in:product,service,combo',
            'item_category_id'   => 'nullable|uuid|exists:item_categories,id',
            'clasification_id'   => 'nullable|integer',
            'tax_category_id'    => 'nullable|integer',
            'unit_measure_id'    => 'nullable|integer',
            'group_id'           => 'nullable|uuid|exists:item_groups,id',
            'line_id'            => 'nullable|uuid|exists:item_lines,id',
            'last_purchase_price'=> 'numeric|min:0',
            'average_cost'       => 'numeric|min:0',
            'default_sale_price' => 'numeric|min:0',
            'consumption_tax'    => 'numeric|min:0',
            'icui'               => 'numeric|min:0',
            'ibua'               => 'numeric|min:0',
            'double_taxes'       => 'boolean',
            'barcode_one'        => 'nullable|string|max:100',
            'barcode_two'        => 'nullable|string|max:100',
            'minimum_existence'  => 'numeric|min:0',
            'maximum_existence'  => 'numeric|min:0',
            'minor_account_id'   => 'nullable|string|max:50',
            'is_active'          => 'boolean',
            'manages_stock'      => 'boolean',
            'is_service'         => 'boolean',
        ]);

        $item->update($data);

        return redirect()->route('inventory.index')
            ->with('success', 'Ítem actualizado correctamente.');
    }

    /**
     * Elimina (soft delete) un ítem.
     */
    public function destroy(Item $item)
    {
        $item->delete();

        return back()->with('success', 'Ítem eliminado correctamente.');
    }

    /**
     * Cambia el estado activo/inactivo del ítem.
     */
    public function toggleStatus(Item $item)
    {
        $item->update(['is_active' => ! $item->is_active]);

        $estado = $item->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Ítem {$estado} correctamente.");
    }
}
