<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Panel de suscripción del tenant: plan actual, período de prueba, historial.
     */
    public function show(): Response
    {
        $tenant = tenancy()->tenant;

        // La suscripción activa vive en el schema público (landlord)
        $subscription = null;
        $plan         = null;

        if ($tenant) {
            tenancy()->end();

            $subscription = \App\Modules\Tenant\Models\Subscription::with('plan')
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->first();

            $plan = $subscription?->plan
                ?? Plan::where('slug', 'free')->first();

            // Todos los planes disponibles para mostrar opciones de upgrade
            $plans = Plan::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'max_users', 'max_products', 'max_invoices_monthly', 'features', 'trial_days'])
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_monthly' => $plan->price_monthly,
                    'price_yearly' => $plan->price_yearly,
                    'max_users' => $plan->max_users,
                    'max_products' => $plan->max_products,
                    'max_invoices_monthly' => $plan->max_invoices_monthly,
                    'features' => $this->enabledFeatureLabels($plan->features ?? []),
                ]);

            tenancy()->initialize($tenant);
        } else {
            $plans = collect();
        }

        return Inertia::render('Subscription/Show', [
            'subscription' => $subscription ? [
                'id'             => $subscription->id,
                'status'         => $subscription->status,
                'billing_period' => $subscription->billing_period,
                'price'          => $subscription->price,
                'trial_ends_at'  => $subscription->trial_ends_at?->toDateString(),
                'starts_at'      => $subscription->starts_at?->toDateString(),
                'ends_at'        => $subscription->ends_at?->toDateString(),
                'is_active'      => $subscription->isActive(),
                'plan'           => $subscription->plan ? [
                    'id'          => $subscription->plan->id,
                    'name'        => $subscription->plan->name,
                    'slug'        => $subscription->plan->slug,
                    'description' => $subscription->plan->description,
                    'features'    => $this->enabledFeatureLabels($subscription->plan->features ?? []),
                    'max_users'   => $subscription->plan->max_users,
                    'max_products' => $subscription->plan->max_products,
                    'max_invoices_monthly' => $subscription->plan->max_invoices_monthly,
                    'price_monthly' => $subscription->plan->price_monthly,
                    'price_yearly'  => $subscription->plan->price_yearly,
                ] : null,
            ] : null,
            'plans' => $plans,
            'tenant' => $tenant ? [
                'name'          => $tenant->name,
                'status'        => $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'is_on_trial'   => $tenant->isOnTrial(),
                'can_operate'   => $tenant->canOperate(),
            ] : null,
        ]);
    }

    private function enabledFeatureLabels(array $features): array
    {
        $labels = [
            'dian_fe' => 'Facturacion electronica',
            'pos' => 'POS',
            'accounting' => 'Contabilidad',
            'inventory' => 'Inventario',
            'payroll' => 'Nomina',
            'multi_branch' => 'Multi sede',
            'api_access' => 'Acceso API',
        ];

        return collect($features)
            ->filter()
            ->keys()
            ->map(fn (string $key) => $labels[$key] ?? $key)
            ->values()
            ->all();
    }
}
