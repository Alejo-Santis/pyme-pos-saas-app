<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Services\DefaultsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly DefaultsService $defaults
    ) {}

    /**
     * Muestra el wizard de onboarding.
     */
    public function show(): Response
    {
        $organizations  = DB::table('type_organizations')->orderBy('id')->get(['id', 'name', 'code']);
        $regimes        = DB::table('type_regimes')->orderBy('id')->get(['id', 'name', 'code']);
        $liabilities    = DB::table('type_liabilities')->orderBy('name')->get(['id', 'name', 'code']);
        $docTypes       = DB::table('type_document_identifications')->orderBy('id')->get(['id', 'name', 'code']);
        $departments    = DB::table('departments')->orderBy('name')->get(['id', 'name', 'code']);
        $municipalities = DB::table('municipalities')->orderBy('name')->get(['id', 'name', 'code', 'department_id']);
        $countries      = DB::table('countries')->orderBy('name')->get(['id', 'name', 'code']);
        $company        = Company::first();

        return Inertia::render('Onboarding', compact(
            'organizations', 'regimes', 'liabilities', 'docTypes',
            'departments', 'municipalities', 'countries', 'company',
        ));
    }

    /**
     * Guarda los datos de la empresa (Paso 1).
     */
    public function saveCompany(Request $request)
    {
        $data = $request->validate([
            'identification_number'           => 'required|string|max:20',
            'dv'                              => 'required|string|size:1',
            'business_name'                   => 'required|string|max:255',
            'trade_name'                      => 'nullable|string|max:255',
            'type_document_identification_id' => 'required|integer',
            'type_organization_id'            => 'required|integer',
            'type_regime_id'                  => 'required|integer',
            'type_liability_id'               => 'required|integer',
            'country_id'                      => 'required|integer',
            'municipality_id'                 => 'required|integer',
            'email'                           => 'required|email|max:255',
            'phone'                           => 'nullable|string|max:20',
            'address'                         => 'required|string|max:255',
        ]);

        Company::updateOrCreate(
            ['identification_number' => $data['identification_number']],
            $data
        );

        return back()->with('success', 'Datos de la empresa guardados correctamente.');
    }

    /**
     * Guarda la resolución DIAN real (Paso 2) y completa el onboarding.
     * Después de guardar la resolución del usuario, siembra el resto de defaults.
     */
    public function saveResolution(Request $request)
    {
        $request->validate([
            'resolution'      => 'required|string|max:50',
            'resolution_date' => 'required|date',
            'prefix'          => 'nullable|string|max:10',
            'from'            => 'required|integer|min:1',
            'to'              => 'required|integer|gt:from',
            'date_from'       => 'required|date',
            'date_to'         => 'required|date|after:date_from',
        ]);

        $company = Company::firstOrFail();

        // Guardar la resolución real FEV que el usuario configuró
        // Nota: NO usar $company->resolutions() — la tabla no tiene company_id (multi-tenant implícito)
        Resolution::updateOrCreate(
            ['resolution' => $request->resolution, 'type_document_operation_id' => 1],
            [
                'uuid'                       => \Illuminate\Support\Str::uuid(),
                'type_document_id'           => 1,
                'type_document_operation_id' => 1,
                'resolution'                 => $request->resolution,
                'resolution_date'            => $request->resolution_date,
                'prefix'                     => $request->prefix,
                'from'                       => $request->from,
                'to'                         => $request->to,
                'current_number'             => $request->from - 1,  // primer emitido = from
                'date_from'                  => $request->date_from,
                'date_to'                    => $request->date_to,
                'is_active'                  => true,
            ]
        );

        // Sembrar el resto de resoluciones internas y terceros por defecto
        $this->defaults->seedResolutions($company);
        $this->defaults->seedThirds($company);

        auth()->user()->update(['onboarding_completed' => true]);

        return redirect()->route('dashboard')
            ->with('success', '¡Configuración completada! Bienvenido a PyME POS SaaS.');
    }

    /**
     * Completa el onboarding saltando la configuración DIAN (puede hacerse después).
     * Siembra igual los defaults internos.
     */
    public function complete(Request $request)
    {
        $company = Company::first();

        if ($company) {
            $this->defaults->seedResolutions($company);
            $this->defaults->seedThirds($company);
        }

        auth()->user()->update(['onboarding_completed' => true]);

        return redirect()->route('dashboard')
            ->with('success', '¡Bienvenido a PyME POS SaaS! Puedes configurar la facturación DIAN desde Configuración.');
    }

    /**
     * Municipios de un departamento (para el select en cascada del wizard).
     */
    public function municipalities(int $departmentId)
    {
        $municipalities = DB::table('municipalities')
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($municipalities);
    }
}
