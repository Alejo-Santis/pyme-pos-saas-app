<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de resoluciones de numeración DIAN.
 * Cada resolución autoriza un prefijo y rango de numeración para un tipo de documento.
 */
class ResolutionController extends Controller
{
    public function index(): Response
    {
        $resolutions = Resolution::orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Config/Resolutions', [
            'resolutions'    => $resolutions,
            'company'        => Company::first(),
            'documentTypes'  => [
                ['id' => 1, 'operation_id' => 1,  'name' => 'Factura de Venta Electrónica'],
                ['id' => 2, 'operation_id' => 2,  'name' => 'Nota Crédito Electrónica'],
                ['id' => 3, 'operation_id' => 3,  'name' => 'Nota Débito Electrónica'],
                ['id' => 4, 'operation_id' => 4,  'name' => 'Factura POS'],
                ['id' => 5, 'operation_id' => 5,  'name' => 'Documento Soporte de Compra'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateResolution($request);
        $data['current_number'] = $data['from'] - 1;

        Resolution::create($data);

        return back()->with('success', 'Resolución creada correctamente.');
    }

    public function update(Request $request, Resolution $resolution): RedirectResponse
    {
        $data = $this->validateResolution($request, $resolution);
        $resolution->update($data);

        return back()->with('success', 'Resolución actualizada correctamente.');
    }

    public function destroy(Resolution $resolution): RedirectResponse
    {
        $resolution->delete();

        return back()->with('success', 'Resolución eliminada.');
    }

    public function toggle(Resolution $resolution): RedirectResponse
    {
        $resolution->update(['is_active' => ! $resolution->is_active]);

        return back()->with('success', $resolution->is_active ? 'Resolución activada.' : 'Resolución desactivada.');
    }

    // ── Privados ──────────────────────────────────────────────────────────

    private function validateResolution(Request $request, ?Resolution $resolution = null): array
    {
        return $request->validate([
            'type_document_id'           => 'required|integer',
            'type_document_operation_id' => 'required|integer',
            'resolution'                 => 'required|string|max:50',
            'resolution_date'            => 'required|date',
            'prefix'                     => 'nullable|string|max:10',
            'from'                       => 'required|integer|min:1',
            'to'                         => 'required|integer|gte:from',
            'date_from'                  => 'required|date',
            'date_to'                    => 'required|date|after:date_from',
            'is_active'                  => 'boolean',
        ]);
    }
}
