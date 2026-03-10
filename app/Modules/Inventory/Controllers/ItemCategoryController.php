<?php

namespace App\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController extends Controller
{
    /**
     * Listado de todas las categorías con la categoría padre y conteo de ítems.
     */
    public function index(): Response
    {
        $categories = ItemCategory::with('parent')
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Crea una nueva categoría.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|uuid|exists:item_categories,id',
        ]);

        ItemCategory::create($data);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    /**
     * Actualiza una categoría existente.
     */
    public function update(Request $request, ItemCategory $itemCategory)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id'   => [
                'nullable',
                'uuid',
                'exists:item_categories,id',
                // No puede ser padre de sí misma
                function ($attribute, $value, $fail) use ($itemCategory) {
                    if ($value === $itemCategory->id) {
                        $fail('Una categoría no puede ser su propio padre.');
                    }
                },
            ],
        ]);

        $itemCategory->update($data);

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    /**
     * Elimina una categoría (solo si no tiene ítems asociados directamente).
     */
    public function destroy(ItemCategory $itemCategory)
    {
        if ($itemCategory->items()->count() > 0) {
            return back()->with('error', 'No se puede eliminar una categoría que tiene ítems asociados.');
        }

        // Desasocia hijos antes de eliminar (por si tienen items que bloquearían FK)
        $itemCategory->children()->update(['parent_id' => $itemCategory->parent_id]);

        $itemCategory->delete();

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
