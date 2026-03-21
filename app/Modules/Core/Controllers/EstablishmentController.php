<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Establishment;
use App\Modules\Core\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstablishmentController extends Controller
{
    public function index(): Response
    {
        $establishments = Establishment::withCount('warehouses')
            ->with(['warehouses' => fn($q) => $q->orderBy('name')])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        $company = Company::first();

        return Inertia::render('Config/Establishments', [
            'establishments' => $establishments,
            'company'        => $company,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'business_name'   => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'municipality_id' => 'nullable|integer',
            'is_main'         => 'boolean',
            'sync_items_full' => 'boolean',
        ]);

        // Si se marca como principal, quitar marca de los demás
        if (!empty($data['is_main'])) {
            Establishment::where('is_main', true)->update(['is_main' => false]);
        }

        Establishment::create($data);

        return back()->with('success', 'Establecimiento creado correctamente.');
    }

    public function update(Request $request, Establishment $establishment)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'business_name'   => 'nullable|string|max:255',
            'address'         => 'nullable|string|max:255',
            'municipality_id' => 'nullable|integer',
            'is_main'         => 'boolean',
            'sync_items_full' => 'boolean',
        ]);

        if (!empty($data['is_main'])) {
            Establishment::where('id', '!=', $establishment->id)
                ->where('is_main', true)
                ->update(['is_main' => false]);
        }

        $establishment->update($data);

        return back()->with('success', 'Establecimiento actualizado correctamente.');
    }

    public function destroy(Establishment $establishment)
    {
        if ($establishment->is_main) {
            return back()->withErrors(['error' => 'No se puede eliminar el establecimiento principal.']);
        }

        if ($establishment->warehouses()->count() > 0) {
            return back()->withErrors(['error' => 'El establecimiento tiene bodegas asociadas. Elimínalas primero.']);
        }

        $establishment->delete();

        return back()->with('success', 'Establecimiento eliminado correctamente.');
    }

    public function toggle(Establishment $establishment)
    {
        // No se puede desactivar el principal
        if ($establishment->is_main) {
            return back()->withErrors(['error' => 'No se puede desactivar el establecimiento principal.']);
        }

        // establishments no tienen is_active en la migración, usamos SoftDeletes como toggle
        // Realmente solo delete/restore — skip this for now
        return back();
    }
}
