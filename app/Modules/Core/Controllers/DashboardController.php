<?php

namespace App\Modules\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = tenancy()->tenant;
        $year   = $request->integer('year', now()->year);

        // ── Métricas de conteo ───────────────────────────────────────────────
        $stats = [
            'items'                => DB::table('items')->count(),
            'third_parties'        => DB::table('third_parties')->count(),
            'users'                => DB::table('users')->where('is_active', true)->count(),
            'accounting_receipts'  => DB::table('documents')->count(),
            'plan_name'  => $tenant?->plan?->name ?? '—',
            'trial_ends' => $tenant?->trial_ends_at
                ? \Carbon\Carbon::parse($tenant->trial_ends_at)->format('d/m/Y')
                : '—',
        ];

        // ── Ventas por mes (documentos de venta del año) ─────────────────────
        $salesRaw = DB::table('documents')
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int AS month, COUNT(*) AS total')
            ->whereYear('created_at', $year)
            ->groupByRaw('EXTRACT(MONTH FROM created_at)::int')
            ->get()
            ->keyBy('month');

        $sales = collect(range(1, 12))
            ->map(fn ($m) => (int) ($salesRaw[$m]->total ?? 0))
            ->values()
            ->toArray();

        // ── Compras por mes (órdenes del año) ────────────────────────────────
        $purchasesRaw = DB::table('purchase_orders')
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int AS month, COUNT(*) AS total')
            ->whereYear('created_at', $year)
            ->groupByRaw('EXTRACT(MONTH FROM created_at)::int')
            ->get()
            ->keyBy('month');

        $purchases = collect(range(1, 12))
            ->map(fn ($m) => (int) ($purchasesRaw[$m]->total ?? 0))
            ->values()
            ->toArray();

        // ── Tendencia mes actual vs anterior (solo tiene sentido en el año en curso) ──
        $trend = function (array $monthly) use ($year) {
            if ($year !== now()->year || now()->month < 2) {
                return null;
            }

            $current  = $monthly[now()->month - 1];
            $previous = $monthly[now()->month - 2];

            if ($previous === 0) {
                return null;
            }

            return round((($current - $previous) / $previous) * 100);
        };

        // ── Actividad reciente (últimos documentos y terceros creados) ───────
        $recentActivity = DB::table('documents')
            ->selectRaw("'document' AS type, internal_code AS label, created_at")
            ->orderByDesc('created_at')
            ->limit(3)
            ->unionAll(
                DB::table('third_parties')
                    ->selectRaw("'third_party' AS type, name AS label, created_at")
                    ->orderByDesc('created_at')
                    ->limit(2)
            )
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'stats'          => $stats,
            'sales'          => $sales,
            'purchases'      => $purchases,
            'salesTrend'     => $trend($sales),
            'purchasesTrend' => $trend($purchases),
            'recentActivity' => $recentActivity,
            'year'           => $year,
        ]);
    }
}
