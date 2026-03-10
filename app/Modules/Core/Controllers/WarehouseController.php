<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Establishment;
use App\Modules\Core\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        $warehouses = Warehouse::with('establishment')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $establishments = Establishment::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Config/Warehouses', [
            'warehouses'     => $warehouses,
            'establishments' => $establishments,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'establishment_id' => 'required|uuid|exists:establishments,id',
            'name'             => 'required|string|max:255',
            'internal_code'    => 'nullable|string|max:50',
            'description'      => 'nullable|string|max:500',
            'is_main'          => 'boolean',
        ]);

        if (!empty($data['is_main'])) {
            Warehouse::where('establishment_id', $data['establishment_id'])
                ->where('is_main', true)
                ->update(['is_main' => false]);
        }

        Warehouse::create($data);

        return back()->with('success', 'Bodega creada correctamente.');
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'establishment_id' => 'required|uuid|exists:establishments,id',
            'name'             => 'required|string|max:255',
            'internal_code'    => 'nullable|string|max:50',
            'description'      => 'nullable|string|max:500',
            'is_main'          => 'boolean',
        ]);

        if (!empty($data['is_main'])) {
            Warehouse::where('establishment_id', $data['establishment_id'])
                ->where('id', '!=', $warehouse->id)
                ->where('is_main', true)
                ->update(['is_main' => false]);
        }

        $warehouse->update($data);

        return back()->with('success', 'Bodega actualizada correctamente.');
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_main) {
            return back()->withErrors(['error' => 'No se puede eliminar la bodega principal.']);
        }

        $warehouse->delete();

        return back()->with('success', 'Bodega eliminada correctamente.');
    }
}
