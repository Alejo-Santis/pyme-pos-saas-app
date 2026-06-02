<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FiscalPeriod;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de períodos fiscales contables.
 *
 * Solo rol 'admin' o 'contador' puede cerrar/reabrir períodos.
 * Una vez cerrado un período, el motor contable rechaza nuevos asientos
 * con fecha dentro de ese período.
 */
class FiscalPeriodController extends Controller
{
    /**
     * Lista todos los períodos fiscales del año en curso (y opcionalmente años anteriores).
     */
    public function index(Request $request): Response
    {
        $year = (int) ($request->input('year', now()->year));

        // Asegurar que existan los 12 períodos del año solicitado
        $this->ensurePeriodsExist($year);

        $periods = FiscalPeriod::where('year', $year)
            ->orderBy('month')
            ->get()
            ->map(fn (FiscalPeriod $p) => [
                'id'        => $p->id,
                'year'      => $p->year,
                'month'     => $p->month,
                'name'      => $p->name,
                'status'    => $p->status,
                'closed_by' => $p->closed_by,
                'closed_at' => $p->closed_at?->format('Y-m-d H:i'),
                'notes'     => $p->notes,
                'is_open'   => $p->isOpen(),
            ]);

        $availableYears = FiscalPeriod::distinct('year')
            ->orderByDesc('year')
            ->pluck('year');

        return Inertia::render('Accounting/FiscalPeriods', [
            'periods'        => $periods,
            'selectedYear'   => $year,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Cierra un período fiscal.
     *
     * Desde este momento el motor contable rechaza asientos en ese período.
     */
    public function close(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        if ($fiscalPeriod->isClosed()) {
            return back()->withErrors(['period' => "El período {$fiscalPeriod->name} ya está cerrado."]);
        }

        $fiscalPeriod->update([
            'status'    => FiscalPeriod::STATUS_CLOSED,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
            'notes'     => $request->input('notes'),
        ]);

        FiscalPeriod::clearCache($fiscalPeriod->year, $fiscalPeriod->month);

        return back()->with('success', "Período {$fiscalPeriod->name} cerrado. No se podrán registrar asientos en este período.");
    }

    /**
     * Reabre un período fiscal cerrado.
     *
     * Requiere justificación y queda registrado en el historial.
     * Útil para correcciones de períodos recientes.
     */
    public function reopen(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $request->validate([
            'notes' => 'required|string|min:10|max:500',
        ], [
            'notes.required' => 'Debe justificar la reapertura del período.',
            'notes.min'      => 'La justificación debe tener al menos 10 caracteres.',
        ]);

        if ($fiscalPeriod->isOpen()) {
            return back()->withErrors(['period' => "El período {$fiscalPeriod->name} ya está abierto."]);
        }

        $fiscalPeriod->update([
            'status'    => FiscalPeriod::STATUS_OPEN,
            'closed_by' => null,
            'closed_at' => null,
            'notes'     => '[REABIERTO ' . now()->format('d/m/Y H:i') . '] ' . $request->input('notes'),
        ]);

        FiscalPeriod::clearCache($fiscalPeriod->year, $fiscalPeriod->month);

        return back()->with('success', "Período {$fiscalPeriod->name} reabierto correctamente.");
    }

    /**
     * Cierra todos los períodos abiertos de años anteriores al actual.
     * Operación de cierre anual masivo.
     */
    public function closeYear(Request $request): RedirectResponse
    {
        $request->validate([
            'year'  => 'required|integer|min:2020|max:' . now()->year,
            'notes' => 'nullable|string|max:500',
        ]);

        $year = (int) $request->input('year');

        if ($year >= now()->year) {
            return back()->withErrors(['year' => 'Solo se puede hacer cierre anual de años anteriores al actual.']);
        }

        $this->ensurePeriodsExist($year);

        $count = FiscalPeriod::where('year', $year)
            ->where('status', FiscalPeriod::STATUS_OPEN)
            ->update([
                'status'    => FiscalPeriod::STATUS_CLOSED,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'notes'     => $request->input('notes', "Cierre anual {$year}"),
            ]);

        // Invalidar caché para todos los meses del año
        for ($m = 1; $m <= 12; $m++) {
            FiscalPeriod::clearCache($year, $m);
        }

        return back()->with('success', "Cierre anual {$year} completado. {$count} períodos cerrados.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Garantiza que existan los 12 registros de período para un año dado.
     * Se llama de forma lazy al acceder al listado.
     */
    private function ensurePeriodsExist(int $year): void
    {
        for ($month = 1; $month <= 12; $month++) {
            FiscalPeriod::firstOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'name'   => FiscalPeriod::monthName($month) . ' ' . $year,
                    'status' => FiscalPeriod::STATUS_OPEN,
                ]
            );
        }
    }
}
