<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
    private array $allFeatures = [
        'dian_fe', 'pos', 'accounting', 'inventory', 'payroll', 'multi_branch', 'api_access',
    ];

    public function index(): Response
    {
        $plans = Plan::withCount('tenants')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => [
                'id'                   => $p->id,
                'name'                 => $p->name,
                'slug'                 => $p->slug,
                'description'          => $p->description,
                'price_monthly'        => $p->price_monthly,
                'price_yearly'         => $p->price_yearly,
                'max_users'            => $p->max_users,
                'max_products'         => $p->max_products,
                'max_invoices_monthly' => $p->max_invoices_monthly,
                'features'             => $p->features ?? [],
                'trial_days'           => $p->trial_days,
                'is_active'            => $p->is_active,
                'sort_order'           => $p->sort_order,
                'tenants_count'        => $p->tenants_count,
            ]);

        return Inertia::render('Admin/Plans/Index', [
            'plans'       => $plans,
            'allFeatures' => $this->allFeatures,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePlan($request);
        $data['slug'] = Str::slug($data['name']);

        // Garantizar slug único
        $base = $data['slug'];
        $i    = 1;
        while (Plan::where('slug', $data['slug'])->exists()) {
            $data['slug'] = $base . '-' . $i++;
        }

        $data['sort_order'] = Plan::max('sort_order') + 1;
        $data['features']   = $this->parseFeatures($request);

        Plan::create($data);

        return back()->with('success', "Plan '{$data['name']}' creado correctamente.");
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);
        $data = $this->validatePlan($request);
        $data['features'] = $this->parseFeatures($request);

        $plan->update($data);

        return back()->with('success', "Plan '{$plan->name}' actualizado correctamente.");
    }

    public function destroy(string $id): RedirectResponse
    {
        $plan = Plan::withCount('tenants')->findOrFail($id);

        if ($plan->tenants_count > 0) {
            return back()->withErrors([
                'plan' => "No se puede eliminar: {$plan->tenants_count} empresa(s) usan este plan.",
            ]);
        }

        $plan->delete();

        return back()->with('success', 'Plan eliminado correctamente.');
    }

    public function toggleActive(string $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', 'Estado del plan actualizado.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function validatePlan(Request $request): array
    {
        return $request->validate([
            'name'                 => 'required|string|max:100',
            'description'          => 'nullable|string|max:500',
            'price_monthly'        => 'required|numeric|min:0',
            'price_yearly'         => 'required|numeric|min:0',
            'max_users'            => 'nullable|integer|min:1',
            'max_products'         => 'nullable|integer|min:1',
            'max_invoices_monthly' => 'nullable|integer|min:1',
            'trial_days'           => 'required|integer|min:0|max:365',
            'is_active'            => 'boolean',
        ]);
    }

    private function parseFeatures(Request $request): array
    {
        $features = [];
        foreach ($this->allFeatures as $key) {
            $features[$key] = (bool) $request->input("features.{$key}", false);
        }
        return $features;
    }
}
