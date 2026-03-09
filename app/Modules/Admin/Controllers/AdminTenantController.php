<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTenantController extends Controller
{
    // Lista de tenants con filtros y paginación
    public function index(Request $request): Response
    {
        $tenants = Tenant::with(['plan', 'activeSubscription'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'ilike', "%{$s}%")
                  ->orWhere('email', 'ilike', "%{$s}%")
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn ($q, $p) => $q->where('plan_id', $p))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($t) => [
                'id'            => $t->id,
                'name'          => $t->name,
                'email'         => $t->email,
                'status'        => $t->status,
                'plan'          => $t->plan?->name ?? '—',
                'plan_id'       => $t->plan_id,
                'domain'        => $t->domains->first()?->domain ?? '—',
                'trial_ends_at' => $t->trial_ends_at
                    ? \Carbon\Carbon::parse($t->trial_ends_at)->format('d/m/Y')
                    : null,
                'created_at'    => $t->created_at?->format('d/m/Y'),
            ]);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants'  => $tenants,
            'plans'    => $plans,
            'filters'  => $request->only(['search', 'status', 'plan_id']),
        ]);
    }

    // Detalle de un tenant
    public function show(string $id): Response
    {
        $tenant = Tenant::with(['plan', 'subscriptions.plan', 'domains'])->findOrFail($id);

        $subscriptions = $tenant->subscriptions()
            ->with('plan')
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id'         => $s->id,
                'plan'       => $s->plan?->name ?? '—',
                'status'     => $s->status,
                'billing'    => $s->billing_period,
                'price'      => $s->price,
                'starts_at'  => $s->starts_at?->format('d/m/Y'),
                'ends_at'    => $s->ends_at?->format('d/m/Y'),
                'trial_ends' => $s->trial_ends_at
                    ? \Carbon\Carbon::parse($s->trial_ends_at)->format('d/m/Y')
                    : null,
            ]);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price_monthly']);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'email'         => $tenant->email,
                'status'        => $tenant->status,
                'plan'          => $tenant->plan?->name ?? '—',
                'plan_id'       => $tenant->plan_id,
                'domain'        => $tenant->domains->first()?->domain ?? '—',
                'trial_ends_at' => $tenant->trial_ends_at
                    ? \Carbon\Carbon::parse($tenant->trial_ends_at)->format('d/m/Y')
                    : null,
                'created_at'    => $tenant->created_at?->format('d/m/Y H:i'),
            ],
            'subscriptions' => $subscriptions,
            'plans'         => $plans,
        ]);
    }

    // Cambiar estado del tenant
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:active,trial,suspended,cancelled',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => $request->status]);

        return back()->with('success', "Estado del tenant actualizado a '{$request->status}'.");
    }

    // Cambiar plan del tenant
    public function updatePlan(Request $request, string $id)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['plan_id' => $request->plan_id]);

        return back()->with('success', 'Plan del tenant actualizado correctamente.');
    }
}
