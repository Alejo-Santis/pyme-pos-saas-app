<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\LandlordAuditLog;
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
            'past_due_subscriptions' => Subscription::where('status', 'past_due')->count(),
            'cancelled_subscriptions' => Subscription::whereIn('status', ['cancelled', 'expired'])->count(),
            'trials_ending_soon' => Tenant::where('status', 'trial')
                ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
                ->count(),
            'estimated_mrr' => Subscription::where('status', 'active')
                ->selectRaw("COALESCE(SUM(CASE WHEN billing_period = 'yearly' THEN price / 12 ELSE price END), 0) as total")
                ->value('total'),
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

        $trialsEndingSoon = Tenant::with('plan')
            ->where('status', 'trial')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->orderBy('trial_ends_at')
            ->take(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'plan' => $t->plan?->name ?? '—',
                'trial_ends_at' => $t->trial_ends_at?->format('d/m/Y'),
            ]);

        $recentAudit = LandlordAuditLog::latest()
            ->take(6)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'admin_name' => $log->admin_name ?? 'Sistema',
                'event' => $log->event,
                'module' => $log->module,
                'created_at' => $log->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Dashboard', compact('stats', 'monthlyGrowth', 'recentTenants', 'trialsEndingSoon', 'recentAudit'));
    }
}
