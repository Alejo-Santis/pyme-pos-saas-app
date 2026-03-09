<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlanController extends Controller
{
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

        return Inertia::render('Admin/Plans/Index', compact('plans'));
    }

    public function toggleActive(string $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('success', 'Estado del plan actualizado.');
    }
}
