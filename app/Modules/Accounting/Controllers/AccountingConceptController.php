<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingConcept;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de conceptos contables.
 * Permite a la empresa mapear cada operación (venta, compra, nómina, etc.)
 * con las cuentas del PUC que desea usar.
 *
 * Cada concepto tiene un type_concept del formato "{opId}_{slug}",
 * por ejemplo: "1_CXC", "14_CXP", "20_SUELDOS".
 */
class AccountingConceptController extends Controller
{
    /**
     * Listado de conceptos agrupados por operación.
     */
    public function index(): Response
    {
        $concepts = AccountingConcept::orderBy('type_concept')
            ->get(['id', 'name', 'type_concept', 'accountable_id', 'is_tax_concept']);

        return Inertia::render('Accounting/Concepts/Index', [
            'concepts'   => $concepts,
            'operations' => $this->operations(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Concepts/Form', [
            'concept'    => null,
            'operations' => $this->operations(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'type_concept'   => 'required|string|max:50',
            'accountable_id' => 'required|string|max:20',
            'is_tax_concept' => 'boolean',
        ]);

        AccountingConcept::create($data + ['uuid' => Str::uuid()]);

        return redirect()->route('accounting.concepts.index')
            ->with('success', 'Concepto contable creado.');
    }

    public function edit(AccountingConcept $concept): Response
    {
        return Inertia::render('Accounting/Concepts/Form', [
            'concept'    => $concept,
            'operations' => $this->operations(),
        ]);
    }

    public function update(Request $request, AccountingConcept $concept): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'type_concept'   => 'required|string|max:50',
            'accountable_id' => 'required|string|max:20',
            'is_tax_concept' => 'boolean',
        ]);

        $concept->update($data);

        return redirect()->route('accounting.concepts.index')
            ->with('success', 'Concepto contable actualizado.');
    }

    public function destroy(AccountingConcept $concept): RedirectResponse
    {
        $concept->delete();

        return back()->with('success', 'Concepto eliminado.');
    }

    /**
     * Operaciones predefinidas con sus slugs sugeridos.
     * El formato es {opId}_{slug} → accountable_id (código PUC).
     */
    private function operations(): array
    {
        return [
            [
                'id'    => 1,
                'name'  => 'Factura de Venta',
                'slugs' => [
                    ['slug' => '1_CXC',        'desc' => 'Clientes × Cobrar',        'default' => '13050501'],
                    ['slug' => '1_INGRESO',     'desc' => 'Ingresos por ventas',      'default' => '41351001'],
                    ['slug' => '1_IVA_GEN',     'desc' => 'IVA generado',             'default' => '24080101'],
                    ['slug' => '1_COSTO',       'desc' => 'Costo de ventas',          'default' => '61351001'],
                    ['slug' => '1_INV_SALIDA',  'desc' => 'Inventario (salida)',       'default' => '14350101'],
                ],
            ],
            [
                'id'    => 14,
                'name'  => 'Compra',
                'slugs' => [
                    ['slug' => '14_INVENTARIO', 'desc' => 'Inventario (entrada)',      'default' => '14350101'],
                    ['slug' => '14_IVA_DESC',   'desc' => 'IVA descontable',           'default' => '24080501'],
                    ['slug' => '14_CXP',        'desc' => 'Cuentas × Pagar',          'default' => '22050101'],
                ],
            ],
            [
                'id'    => 91,
                'name'  => 'Nota Crédito',
                'slugs' => [
                    ['slug' => '91_CXC',        'desc' => 'Clientes × Cobrar',        'default' => '13050501'],
                    ['slug' => '91_INGRESO',    'desc' => 'Ingresos (reversa)',        'default' => '41351001'],
                    ['slug' => '91_IVA_GEN',    'desc' => 'IVA generado (reversa)',    'default' => '24080101'],
                    ['slug' => '91_INV_ENTRA',  'desc' => 'Inventario (reingresa)',    'default' => '14350101'],
                    ['slug' => '91_COSTO',      'desc' => 'Costo ventas (reversa)',    'default' => '61351001'],
                ],
            ],
            [
                'id'    => 20,
                'name'  => 'Nómina',
                'slugs' => [
                    ['slug' => '20_SUELDOS',    'desc' => 'Gasto sueldos y salarios', 'default' => '51050501'],
                    ['slug' => '20_NOMINA_XP',  'desc' => 'Nómina × pagar (neta)',    'default' => '25050501'],
                    ['slug' => '20_SS_EMP',     'desc' => 'SS empleado × descontar',  'default' => '25053001'],
                    ['slug' => '20_RTE_FTE',    'desc' => 'Retención en la fuente',   'default' => '23650101'],
                    ['slug' => '20_PATRONAL',   'desc' => 'Aportes patronales gasto', 'default' => '51055401'],
                    ['slug' => '20_PAT_XP',     'desc' => 'Aportes patronales × pagar','default'=> '25054001'],
                ],
            ],
        ];
    }
}
