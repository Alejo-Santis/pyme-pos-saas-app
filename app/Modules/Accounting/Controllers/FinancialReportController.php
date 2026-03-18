<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Core\Models\Company;
use App\Shared\Exports\ArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reportes financieros formales en Colombia (NIIF PYMES / Decreto 2649).
 *
 * Estado de Resultados (P&G):
 *   Ingresos (clase 4) - Costos (clase 6) - Gastos (clase 5) = Utilidad / Pérdida
 *
 * Balance General:
 *   Activos (clase 1) = Pasivos (clase 2) + Patrimonio (clase 3)
 */
class FinancialReportController extends Controller
{
    /**
     * Estado de Resultados (P&G) — período de tiempo configurable.
     */
    public function incomeStatement(Request $request): Response
    {
        $from = $request->input('date_from', now()->startOfYear()->toDateString());
        $to   = $request->input('date_to',   now()->toDateString());

        // Saldos de cuentas de resultado (clases 4, 5, 6)
        $balances = $this->getBalancesByClass([4, 5, 6], $from, $to);

        // Ingresos operacionales (grupo 41)
        $ingresos = $this->sumGroup($balances, '41');
        // Ingresos no operacionales (grupo 42)
        $ingresosNoOp = $this->sumGroup($balances, '42');
        // Costo de ventas (clase 6)
        $costos = $this->sumClass($balances, 6);
        // Gastos operacionales (grupos 51, 52)
        $gastosOp = $this->sumGroup($balances, '51') + $this->sumGroup($balances, '52');
        // Gastos no operacionales (grupo 53)
        $gastosNoOp = $this->sumGroup($balances, '53');

        $utilidadBruta     = $ingresos - $costos;
        $utilidadOperativa = $utilidadBruta - $gastosOp;
        $utilidadNeta      = $utilidadOperativa + $ingresosNoOp - $gastosNoOp;

        // Detalle por grupos para la vista
        $detail = $this->buildStatementDetail($balances, [4, 5, 6]);

        return Inertia::render('Accounting/IncomeStatement', [
            'summary' => [
                'ingresos_operacionales'    => round($ingresos, 2),
                'ingresos_no_operacionales' => round($ingresosNoOp, 2),
                'costo_ventas'              => round($costos, 2),
                'gastos_operacionales'      => round($gastosOp, 2),
                'gastos_no_operacionales'   => round($gastosNoOp, 2),
                'utilidad_bruta'            => round($utilidadBruta, 2),
                'utilidad_operativa'        => round($utilidadOperativa, 2),
                'utilidad_neta'             => round($utilidadNeta, 2),
            ],
            'detail'  => $detail,
            'filters' => ['date_from' => $from, 'date_to' => $to],
        ]);
    }

    /**
     * Balance General — fotografía de la posición financiera al cierre.
     */
    public function balanceSheet(Request $request): Response
    {
        $to = $request->input('date_to', now()->toDateString());
        // El balance acumula desde el inicio de la empresa (o del período contable)
        $from = $request->input('date_from', now()->startOfYear()->toDateString());

        // Saldos de cuentas de posición (clases 1, 2, 3)
        $balances = $this->getBalancesByClass([1, 2, 3], $from, $to);

        $totalActivos   = $this->sumClass($balances, 1);
        $totalPasivos   = $this->sumClass($balances, 2);
        $totalPatrimonio = $this->sumClass($balances, 3);

        // La utilidad del período se incorpora al patrimonio
        $balancesRes = $this->getBalancesByClass([4, 5, 6], $from, $to);
        $utilidadPeriodo = $this->sumClass($balancesRes, 4)
                         - $this->sumClass($balancesRes, 5)
                         - $this->sumClass($balancesRes, 6);

        $totalPatrimonioConResultado = $totalPatrimonio + $utilidadPeriodo;

        $detail = $this->buildStatementDetail($balances, [1, 2, 3]);

        return Inertia::render('Accounting/BalanceSheet', [
            'summary' => [
                'total_activos'            => round($totalActivos, 2),
                'total_pasivos'            => round($totalPasivos, 2),
                'total_patrimonio'         => round($totalPatrimonioConResultado, 2),
                'utilidad_periodo'         => round($utilidadPeriodo, 2),
                'cuadre'                   => abs($totalActivos - ($totalPasivos + $totalPatrimonioConResultado)) < 1,
                'diferencia'               => round($totalActivos - ($totalPasivos + $totalPatrimonioConResultado), 2),
            ],
            'detail'  => $detail,
            'filters' => ['date_from' => $from, 'date_to' => $to],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // EXPORTACIONES
    // ──────────────────────────────────────────────────────────────────────

    public function exportIncomeStatement(Request $request): mixed
    {
        $from = $request->input('date_from', now()->startOfYear()->toDateString());
        $to   = $request->input('date_to',   now()->toDateString());
        $format = $request->input('format', 'excel');

        $balances = $this->getBalancesByClass([4, 5, 6], $from, $to);
        $summary  = [
            'ingresos_operacionales'    => round($this->sumGroup($balances, '41'), 2),
            'ingresos_no_operacionales' => round($this->sumGroup($balances, '42'), 2),
            'costo_ventas'              => round($this->sumClass($balances, 6), 2),
            'gastos_operacionales'      => round($this->sumGroup($balances, '51') + $this->sumGroup($balances, '52'), 2),
            'gastos_no_operacionales'   => round($this->sumGroup($balances, '53'), 2),
        ];
        $summary['utilidad_bruta']     = round($summary['ingresos_operacionales'] - $summary['costo_ventas'], 2);
        $summary['utilidad_operativa'] = round($summary['utilidad_bruta'] - $summary['gastos_operacionales'], 2);
        $summary['utilidad_neta']      = round($summary['utilidad_operativa'] + $summary['ingresos_no_operacionales'] - $summary['gastos_no_operacionales'], 2);
        $detail  = $this->buildStatementDetail($balances, [4, 5, 6]);
        $company = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.income_statement', compact('summary', 'detail', 'from', 'to', 'company'))
                ->setPaper('a4', 'portrait')
                ->download("estado_resultados_{$from}_{$to}.pdf");
        }

        $meta = [$company?->name ?? 'Empresa', "Estado de Resultados — {$from} al {$to}"];
        $rows = [
            ['Ingresos Operacionales', '', number_format($summary['ingresos_operacionales'], 2, ',', '.')],
            ['(–) Costo de Ventas', '', '(' . number_format($summary['costo_ventas'], 2, ',', '.') . ')'],
            ['= Utilidad Bruta', '', number_format($summary['utilidad_bruta'], 2, ',', '.')],
            ['(–) Gastos Operacionales', '', '(' . number_format($summary['gastos_operacionales'], 2, ',', '.') . ')'],
            ['= Utilidad Operativa', '', number_format($summary['utilidad_operativa'], 2, ',', '.')],
            ['(+/–) Otros ingresos/gastos', '', number_format($summary['ingresos_no_operacionales'] - $summary['gastos_no_operacionales'], 2, ',', '.')],
            ['= UTILIDAD NETA', '', number_format($summary['utilidad_neta'], 2, ',', '.')],
        ];
        // Agregar detalle de cuentas
        foreach ($detail as $class) {
            $rows[] = ['', '', ''];
            $rows[] = [$class['class_name'], '', number_format($class['total'], 2, ',', '.')];
            foreach ($class['groups'] as $group) {
                $rows[] = ['  ' . $group['name'], '', number_format($group['total'], 2, ',', '.')];
                foreach ($group['accounts'] as $acc) {
                    $rows[] = ['    ' . $acc['code'] . ' ' . $acc['name'], '', number_format($acc['balance'], 2, ',', '.')];
                }
            }
        }
        return Excel::download(new ArrayExport($rows, ['Concepto', '', 'Valor ($)'], 'Estado de Resultados', $meta), "estado_resultados_{$from}_{$to}.xlsx");
    }

    public function exportBalanceSheet(Request $request): mixed
    {
        $from   = $request->input('date_from', now()->startOfYear()->toDateString());
        $to     = $request->input('date_to',   now()->toDateString());
        $format = $request->input('format', 'excel');

        $balances   = $this->getBalancesByClass([1, 2, 3], $from, $to);
        $balancesRes = $this->getBalancesByClass([4, 5, 6], $from, $to);
        $summary = [
            'total_activos'    => round($this->sumClass($balances, 1), 2),
            'total_pasivos'    => round($this->sumClass($balances, 2), 2),
            'total_patrimonio' => round($this->sumClass($balances, 3), 2),
            'utilidad_periodo' => round($this->sumClass($balancesRes, 4) - $this->sumClass($balancesRes, 5) - $this->sumClass($balancesRes, 6), 2),
        ];
        $summary['patrimonio_total'] = round($summary['total_patrimonio'] + $summary['utilidad_periodo'], 2);
        $summary['cuadre'] = abs($summary['total_activos'] - ($summary['total_pasivos'] + $summary['patrimonio_total'])) < 1;
        $detail  = $this->buildStatementDetail($balances, [1, 2, 3]);
        $company = Company::first();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.balance_sheet', compact('summary', 'detail', 'from', 'to', 'company'))
                ->setPaper('a4', 'portrait')
                ->download("balance_general_{$to}.pdf");
        }

        $meta = [$company?->name ?? 'Empresa', "Balance General al {$to}"];
        $rows = [
            ['ACTIVOS', number_format($summary['total_activos'], 2, ',', '.'), '', 'PASIVOS + PATRIMONIO', number_format($summary['total_pasivos'] + $summary['patrimonio_total'], 2, ',', '.')],
            ['Total Activos', number_format($summary['total_activos'], 2, ',', '.'), '', 'Total Pasivos', number_format($summary['total_pasivos'], 2, ',', '.')],
            ['', '', '', 'Total Patrimonio', number_format($summary['patrimonio_total'], 2, ',', '.')],
            ['', '', '', 'Utilidad Período', number_format($summary['utilidad_periodo'], 2, ',', '.')],
            ['', '', '', 'CUADRA:', $summary['cuadre'] ? 'SÍ ✓' : 'NO ✗'],
        ];
        return Excel::download(new ArrayExport($rows, ['Activos', 'Valor', '', 'Pasivos/Patrimonio', 'Valor'], 'Balance General', $meta), "balance_general_{$to}.xlsx");
    }

    // ─── Helpers privados ─────────────────────────────────────────────────

    /**
     * Obtiene saldos netos (débito - crédito) por cuenta en el período,
     * filtrados por clases del PUC.
     */
    private function getBalancesByClass(array $classes, string $from, string $to): \Illuminate\Support\Collection
    {
        $rows = DB::table('accounting_documents_details as d')
            ->join('accounting_documents as v', 'v.id', '=', 'd.accounting_document_id')
            ->where('v.annulled', false)
            ->whereBetween('v.issue_date', [$from, $to])
            ->select(
                'd.accountable_id as code',
                DB::raw('SUM(d.debit) as debit'),
                DB::raw('SUM(d.credit) as credit')
            )
            ->groupBy('d.accountable_id')
            ->get();

        $codes    = $rows->pluck('code')->unique()->filter();
        $accounts = ChartOfAccount::whereIn('code', $codes)
            ->whereIn('class', $classes)
            ->get()
            ->keyBy('code');

        return $rows
            ->filter(fn ($r) => $accounts->has($r->code))
            ->map(function ($r) use ($accounts) {
                $acc           = $accounts->get($r->code);
                $r->name       = $acc->name;
                $r->class      = $acc->class;
                $r->level      = $acc->level;
                $r->parent_code = $acc->parent_code;
                $r->nature     = $acc->nature;
                // Saldo neto según naturaleza de la cuenta
                $r->balance = $acc->nature === 'D'
                    ? (float) $r->debit - (float) $r->credit
                    : (float) $r->credit - (float) $r->debit;
                return $r;
            });
    }

    /** Suma saldos de una clase completa */
    private function sumClass(\Illuminate\Support\Collection $balances, int $class): float
    {
        return (float) $balances->where('class', $class)->sum('balance');
    }

    /** Suma saldos de un grupo (cuentas cuyo código empieza con el prefijo) */
    private function sumGroup(\Illuminate\Support\Collection $balances, string $prefix): float
    {
        return (float) $balances->filter(fn ($r) => str_starts_with($r->code, $prefix))->sum('balance');
    }

    /**
     * Construye estructura jerárquica para visualizar en la vista:
     * Clase → Grupos → Cuentas
     */
    private function buildStatementDetail(\Illuminate\Support\Collection $balances, array $classes): array
    {
        $result = [];
        foreach ($classes as $class) {
            $classRows = $balances->where('class', $class)->sortBy('code');
            if ($classRows->isEmpty()) continue;

            // Agrupar por los 2 primeros dígitos (grupo)
            $groups = $classRows->groupBy(fn ($r) => substr($r->code, 0, 2));

            $classData = [
                'class'      => $class,
                'class_name' => ChartOfAccount::className($class),
                'total'      => round($classRows->sum('balance'), 2),
                'groups'     => [],
            ];

            foreach ($groups as $groupCode => $groupRows) {
                $groupAccount = ChartOfAccount::where('code', $groupCode)->first();
                $classData['groups'][] = [
                    'code'     => $groupCode,
                    'name'     => $groupAccount?->name ?? "Grupo {$groupCode}",
                    'total'    => round($groupRows->sum('balance'), 2),
                    'accounts' => $groupRows->map(fn ($r) => [
                        'code'    => $r->code,
                        'name'    => $r->name,
                        'balance' => round($r->balance, 2),
                    ])->values()->all(),
                ];
            }

            $result[] = $classData;
        }
        return $result;
    }
}
