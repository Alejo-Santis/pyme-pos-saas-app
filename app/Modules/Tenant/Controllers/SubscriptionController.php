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
                ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'features', 'trial_days']);

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
                    'features'    => $subscription->plan->features,
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
}
