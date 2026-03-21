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
            'items'               => DB::table('items')->count(),
            'third_parties'       => DB::table('third_parties')->count(),
            'users'               => DB::table('users')->where('is_active', true)->count(),
            'accounting_receipts' => DB::table('documents')->where('annulled', false)->count(),
            'plan_name'  => $tenant?->plan?->name ?? '—',
            'trial_ends' => $tenant?->trial_ends_at
                ? \Carbon\Carbon::parse($tenant->trial_ends_at)->format('d/m/Y')
                : null,

            // Ventas del mes actual (total en pesos)
            'revenue_this_month' => (float) DB::table('documents')
                ->where('annulled', false)
                ->whereIn('type_document_operation_id', [1, 14]) // FEV + POS
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->sum('total'),

            // Ventas del año actual
            'revenue_this_year' => (float) DB::table('documents')
                ->where('annulled', false)
                ->whereIn('type_document_operation_id', [1, 14])
                ->whereYear('created_at', now()->year)
                ->sum('total'),

            // Documentos pendientes de pago
            'pending_payment' => DB::table('documents')
                ->where('annulled', false)
                ->where('paid', false)
                ->whereIn('type_document_operation_id', [1, 14])
                ->count(),
        ];

        // ── Ventas: cantidad de documentos por mes ───────────────────────────
        $salesRaw = DB::table('documents')
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int AS month, COUNT(*) AS total')
            ->where('annulled', false)
            ->whereYear('created_at', $year)
            ->groupByRaw('EXTRACT(MONTH FROM created_at)::int')
            ->get()
            ->keyBy('month');

        $sales = collect(range(1, 12))
            ->map(fn ($m) => (int) ($salesRaw[$m]->total ?? 0))
            ->values()
            ->toArray();

        // ── Ingresos: suma de totales por mes (ventas + POS) ─────────────────
        $revenueRaw = DB::table('documents')
            ->selectRaw('EXTRACT(MONTH FROM created_at)::int AS month, COALESCE(SUM(total), 0) AS total')
            ->where('annulled', false)
            ->whereIn('type_document_operation_id', [1, 14])
            ->whereYear('created_at', $year)
            ->groupByRaw('EXTRACT(MONTH FROM created_at)::int')
            ->get()
            ->keyBy('month');

        $revenue = collect(range(1, 12))
            ->map(fn ($m) => (float) ($revenueRaw[$m]->total ?? 0))
            ->values()
            ->toArray();

        // ── Compras: cantidad de órdenes por mes ─────────────────────────────
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

        // ── Documentos recientes (últimos 8) ─────────────────────────────────
        $recentDocuments = DB::table('documents as d')
            ->leftJoin('third_parties as t', 't.id', '=', 'd.third_party_id')
            ->select([
                'd.id',
                'd.prefix',
                'd.number',
                'd.total',
                'd.paid',
                'd.annulled',
                'd.electronic',
                'd.type_document_operation_id',
                DB::raw("COALESCE(d.issue_date, d.created_at::date) AS date"),
                't.name AS third_party_name',
            ])
            ->orderByDesc('d.created_at')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id'                         => $row->id,
                'label'                      => trim(($row->prefix ?? '') . '-' . ($row->number ?? '')),
                'third_party'                => $row->third_party_name ?? '—',
                'total'                      => (float) $row->total,
                'paid'                       => (bool) $row->paid,
                'annulled'                   => (bool) $row->annulled,
                'electronic'                 => (bool) $row->electronic,
                'type_document_operation_id' => (int) $row->type_document_operation_id,
                'date'                       => $row->date,
            ])
            ->toArray();

        return Inertia::render('Dashboard', [
            'stats'           => $stats,
            'sales'           => $sales,
            'revenue'         => $revenue,
            'purchases'       => $purchases,
            'year'            => $year,
            'recentDocuments' => $recentDocuments,
        ]);
    }
}
