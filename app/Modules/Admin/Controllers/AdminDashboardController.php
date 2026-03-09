<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $stats = [
            'total_tenants'      => Tenant::count(),
            'active_tenants'     => Tenant::where('status', 'active')->count(),
            'trial_tenants'      => Tenant::where('status', 'trial')->count(),
            'suspended_tenants'  => Tenant::where('status', 'suspended')->count(),
            'total_plans'        => Plan::where('is_active', true)->count(),
            'total_subscriptions'=> Subscription::whereIn('status', ['active', 'trial'])->count(),
        ];

        // Tenants registrados por mes (últimos 6 meses)
        $monthlyGrowth = Tenant::selectRaw("TO_CHAR(created_at, 'Mon') as month, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw("TO_CHAR(created_at, 'Mon'), DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->pluck('total', 'month');

        // Últimos 5 tenants registrados
        $recentTenants = Tenant::with('plan')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id'        => $t->id,
                'name'      => $t->name,
                'email'     => $t->email,
                'status'    => $t->status,
                'plan'      => $t->plan?->name ?? '—',
                'created_at'=> $t->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Dashboard', compact('stats', 'monthlyGrowth', 'recentTenants'));
    }
}
