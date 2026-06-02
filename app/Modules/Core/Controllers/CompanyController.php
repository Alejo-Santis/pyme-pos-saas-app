<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Company;
use App\Modules\Invoice\Services\ApiNextpymeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración de la empresa del tenant.
 * Cubre datos tributarios, contacto y credenciales del proveedor DIAN.
 */
class CompanyController extends Controller
{
    public function show(): Response
    {
        $company = $this->currentCompany();

        return Inertia::render('Config/Company', [
            'company'        => $company,
            'environments'   => [
                ['id' => 1, 'name' => 'Producción'],
                ['id' => 2, 'name' => 'Habilitación (pruebas)'],
            ],
            'typeOrganizations'           => \DB::table('type_organizations')->get(['id', 'name']),
            'typeDocumentIdentifications' => \DB::table('type_document_identifications')->get(['id', 'name']),
            'typeRegimes'                 => \DB::table('type_regimes')->get(['id', 'name']),
            'typeLiabilities'             => \DB::table('type_liabilities')->get(['id', 'name']),
            'countries'                   => \DB::table('countries')->get(['id', 'name']),
            'municipalities'              => \DB::table('municipalities')->select('id', 'name', 'department_id')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->currentCompany();

        $data = $request->validate([
            'identification_number'           => [
                'required',
                'string',
                'max:20',
                Rule::unique('companies', 'identification_number')->ignore($company?->id),
            ],
            'dv'                              => 'required|string|max:1',
            'business_name'                   => 'required|string|max:255',
            'trade_name'                      => 'nullable|string|max:255',
            'email'                           => 'required|email|max:255',
            'phone'                           => 'nullable|string|max:20',
            'address'                         => 'required|string|max:255',
            'type_organization_id'            => 'required|integer',
            'type_document_identification_id' => 'required|integer',
            'type_regime_id'                  => 'required|integer',
            'type_liability_id'               => 'required|integer',
            'country_id'                      => 'required|integer',
            'municipality_id'                 => 'required|integer',
            // DIAN
            'type_environment_id'             => 'required|integer|in:1,2',
            'dian_software_id'                => 'nullable|string|max:255',
            'dian_software_security_code'     => 'nullable|string|max:255',
            'dian_test_set_id'                => 'nullable|string|max:255',
            'dian_provider'                   => 'nullable|string|max:100',
            'api_path_fe'                     => 'nullable|url|max:255',
            'api_token_fe'                    => 'nullable|string|max:500',
            // Flags
            'electronic_documents'            => 'boolean',
            'use_price_list'                  => 'boolean',
            'prices_with_taxes_included'      => 'boolean',
        ]);

        if ($company) {
            $company->update($data);
        } else {
            Company::create($data);
        }

        return back()->with('success', 'Configuración de empresa guardada correctamente.');
    }

    public function testNextpyme(ApiNextpymeService $api): RedirectResponse
    {
        $result = $api->testConnection();
        $status = $result['statusCode'] ?? 'sin status';
        $url = $result['url'] ?? 'sin URL';
        $message = "{$result['message']} URL: {$url}";

        if (($result['reachable'] ?? false) && ($result['authorized'] ?? false)) {
            return back()->with('success', $message);
        }

        return back()->with('error', "Prueba Nextpyme fallida HTTP {$status}. {$message}");
    }

    private function currentCompany(): ?Company
    {
        return Company::query()
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->orderBy('created_at')
            ->first();
    }
}
