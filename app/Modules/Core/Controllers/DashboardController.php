<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = tenancy()->tenant;

        // Métricas básicas del tenant (conteos en el schema del tenant)
        $stats = [
            'items'           => DB::table('items')->count(),
            'third_parties'   => DB::table('third_parties')->count(),
            'users'           => DB::table('users')->where('is_active', true)->count(),
            'documents_month' => DB::table('documents')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'plan_name'  => $tenant?->plan?->name ?? '—',
            'trial_ends' => $tenant?->trial_ends_at?->format('d/m/Y') ?? '—',
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
