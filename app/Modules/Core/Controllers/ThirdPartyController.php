<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\PartyLinkage;
use App\Modules\Core\Models\ThirdParty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ThirdPartyController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ThirdParty::with('linkage')
            ->when($request->search, fn($q, $s) =>
                $q->where('identification_number', 'like', "%{$s}%")
                  ->orWhere('name', 'ilike', "%{$s}%")
                  ->orWhere('surname', 'ilike', "%{$s}%")
            )
            ->when($request->type, function($q, $t) {
                $q->whereHas('linkage', fn($l) => $l->where($t, true));
            })
            ->when($request->status !== null, fn($q, $s) =>
                $q->where('is_active', $s)
            )
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('ThirdParties/Index', [
            'thirds'  => $query,
            'filters' => $request->only(['search', 'type', 'status']),
            'types'    => DB::table('type_thirds')->orderBy('id')->get(['id', 'name']),
            'docTypes' => DB::table('type_document_identifications')->orderBy('id')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ThirdParties/Form', [
            'third'         => null,
            'organizations' => DB::table('type_organizations')->orderBy('id')->get(['id', 'name']),
            'regimes'       => DB::table('type_regimes')->orderBy('id')->get(['id', 'name']),
            'liabilities'   => DB::table('type_liabilities')->orderBy('name')->get(['id', 'name']),
            'docTypes'      => DB::table('type_document_identifications')->orderBy('id')->get(['id', 'name']),
            'thirds'        => DB::table('type_thirds')->orderBy('id')->get(['id', 'name']),
            'departments'   => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'municipalities' => DB::table('municipalities')->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'identification_number'           => 'required|string|max:20|unique:third_parties',
            'dv'                              => 'nullable|string|max:1',
            'type_document_identification_id' => 'required|integer',
            'type_organization_id'            => 'required|integer',
            'type_regime_id'                  => 'required|integer',
            'type_liability_id'               => 'required|integer',
            'type_third_id'                   => 'required|integer',
            'name'                            => 'required|string|max:255',
            'surname'                         => 'nullable|string|max:255',
            'email'                           => 'nullable|email|max:255',
            'phone'                           => 'nullable|string|max:20',
            'address'                         => 'nullable|string|max:255',
            'municipality_id'                 => 'nullable|integer',
            'country_id'                      => 'nullable|integer',
            'excluded_from_taxes'             => 'boolean',
            'great_contributor'               => 'boolean',
            'self_retaining'                  => 'boolean',
            'seller'                          => 'boolean',
            'credit_limit'                    => 'numeric|min:0',
            'payment_days'                    => 'integer|min:0',
            'customer'                        => 'boolean',
            'provider'                        => 'boolean',
        ]);

        DB::transaction(function () use ($data) {
            $third = ThirdParty::create(collect($data)->except(['customer', 'provider'])->toArray());

            PartyLinkage::create([
                'third_party_id' => $third->id,
                'customer'       => $data['customer'] ?? false,
                'provider'       => $data['provider'] ?? false,
                'other'          => !($data['customer'] ?? false) && !($data['provider'] ?? false),
            ]);
        });

        return redirect()->route('third-parties.index')
            ->with('success', 'Tercero creado correctamente.');
    }

    public function edit(ThirdParty $thirdParty): Response
    {
        return Inertia::render('ThirdParties/Form', [
            'third'          => $thirdParty->load('linkage'),
            'organizations'  => DB::table('type_organizations')->orderBy('id')->get(['id', 'name']),
            'regimes'        => DB::table('type_regimes')->orderBy('id')->get(['id', 'name']),
            'liabilities'    => DB::table('type_liabilities')->orderBy('name')->get(['id', 'name']),
            'docTypes'       => DB::table('type_document_identifications')->orderBy('id')->get(['id', 'name']),
            'thirds'         => DB::table('type_thirds')->orderBy('id')->get(['id', 'name']),
            'departments'    => DB::table('departments')->orderBy('name')->get(['id', 'name']),
            'municipalities' => DB::table('municipalities')->orderBy('name')->get(['id', 'name', 'department_id']),
        ]);
    }

    public function update(Request $request, ThirdParty $thirdParty)
    {
        $data = $request->validate([
            'identification_number'           => "required|string|max:20|unique:third_parties,identification_number,{$thirdParty->id},id",
            'dv'                              => 'nullable|string|max:1',
            'type_document_identification_id' => 'required|integer',
            'type_organization_id'            => 'required|integer',
            'type_regime_id'                  => 'required|integer',
            'type_liability_id'               => 'required|integer',
            'type_third_id'                   => 'required|integer',
            'name'                            => 'required|string|max:255',
            'surname'                         => 'nullable|string|max:255',
            'email'                           => 'nullable|email|max:255',
            'phone'                           => 'nullable|string|max:20',
            'address'                         => 'nullable|string|max:255',
            'municipality_id'                 => 'nullable|integer',
            'country_id'                      => 'nullable|integer',
            'excluded_from_taxes'             => 'boolean',
            'great_contributor'               => 'boolean',
            'self_retaining'                  => 'boolean',
            'seller'                          => 'boolean',
            'credit_limit'                    => 'numeric|min:0',
            'payment_days'                    => 'integer|min:0',
            'is_active'                       => 'boolean',
            'customer'                        => 'boolean',
            'provider'                        => 'boolean',
        ]);

        DB::transaction(function () use ($data, $thirdParty) {
            $thirdParty->update(collect($data)->except(['customer', 'provider'])->toArray());

            $thirdParty->linkage()->updateOrCreate(
                ['third_party_id' => $thirdParty->id],
                [
                    'customer' => $data['customer'] ?? false,
                    'provider' => $data['provider'] ?? false,
                    'other'    => !($data['customer'] ?? false) && !($data['provider'] ?? false),
                ]
            );
        });

        return redirect()->route('third-parties.index')
            ->with('success', 'Tercero actualizado correctamente.');
    }

    public function destroy(ThirdParty $thirdParty)
    {
        $thirdParty->delete();

        return back()->with('success', 'Tercero eliminado correctamente.');
    }

    public function toggleStatus(ThirdParty $thirdParty)
    {
        $thirdParty->update(['is_active' => !$thirdParty->is_active]);

        return back()->with('success', 'Estado actualizado.');
    }
}
