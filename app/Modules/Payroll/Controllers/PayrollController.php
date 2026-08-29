<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\AccountingDocument;
use App\Modules\Accounting\Models\AccountingDocumentDetail;
use App\Modules\Core\Models\Company;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayrollNovelty;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use App\Modules\Payroll\Services\PayrollCalculationService;
use App\Shared\Exports\ArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    public function __construct(private PayrollCalculationService $calculator) {}

    // ── Liquidaciones ─────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $runs = PayrollRun::with('createdBy')
            ->when($request->year, fn ($q, $y) => $q->whereYear('period_start', $y))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('period_start')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Payroll/Runs/Index', [
            'runs'    => $runs,
            'filters' => $request->only('year', 'status'),
            'stats'   => [
                'total_employees' => Employee::active()->count(),
                'pending_runs'    => PayrollRun::where('status', PayrollRun::STATUS_DRAFT)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        // Calcular período sugerido (mes actual)
        $now          = now();
        $periodStart  = $request->period_start ?? $now->startOfMonth()->toDateString();
        $periodEnd    = $request->period_end   ?? $now->endOfMonth()->toDateString();

        // Preview de la liquidación (sin guardar)
        $preview = $this->calculator->previewRun($periodStart, $periodEnd);

        return Inertia::render('Payroll/Runs/Create', [
            'periodStart'   => $periodStart,
            'periodEnd'     => $periodEnd,
            'preview'       => $preview,
            'smmlv'         => PayrollCalculationService::SMMLV,
            'transportAllowance' => PayrollCalculationService::TRANSPORT_ALLOWANCE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after:period_start',
            'notes'        => 'nullable|string|max:500',
        ]);

        // Verificar que no exista otra liquidación solapada
        $overlap = PayrollRun::whereNotIn('status', [PayrollRun::STATUS_CANCELLED])
            ->where(fn ($q) =>
                $q->whereBetween('period_start', [$data['period_start'], $data['period_end']])
                  ->orWhereBetween('period_end', [$data['period_start'], $data['period_end']])
            )
            ->first();

        if ($overlap) {
            return back()->withErrors(['period_start' => "Ya existe una liquidación que cubre ese período: {$overlap->name}"]);
        }

        $run = PayrollRun::create($data + [
            'created_by'        => $request->user()->id,
            'payroll_period_id' => 1,
            'status'            => PayrollRun::STATUS_DRAFT,
        ]);

        // Calcular y guardar todos los empleados
        $this->calculator->processRun($run);

        return redirect()->route('payroll.runs.show', $run)
            ->with('success', "Liquidación \"{$run->name}\" procesada correctamente.");
    }

    public function show(PayrollRun $run): Response
    {
        $run->load(['details.employee', 'details.contract', 'createdBy', 'approvedBy']);

        return Inertia::render('Payroll/Runs/Show', [
            'run'    => $run,
            'labels' => [
                'statuses' => [
                    'draft'     => 'Borrador',
                    'approved'  => 'Aprobada',
                    'paid'      => 'Pagada',
                    'cancelled' => 'Anulada',
                ],
            ],
        ]);
    }

    public function approve(Request $request, PayrollRun $run): RedirectResponse
    {
        if (!$run->isDraft()) {
            return back()->withErrors(['status' => 'Solo se pueden aprobar liquidaciones en estado Borrador.']);
        }

        $run->update(['status' => PayrollRun::STATUS_APPROVED, 'approved_by' => $request->user()->id]);

        // Generar asiento contable de nómina
        $this->generatePayrollEntry($run, $request->user()->id);

        return back()->with('success', "Liquidación {$run->name} aprobada.");
    }

    /**
     * Genera el asiento contable de provisión de nómina (op 20).
     *
     * Debe:
     *   Db 510506 Sueldos y salarios        = total_earned
     *   Cr 250505 Nómina por pagar          = total_net
     *   Cr 250530 SS empleados por descontar = retenciones seguridad social empleado
     *   Db 510554 Aportes patronales salud+pensión = (salud + pensión empleador)
     *   Cr 250530 SS patronal por pagar           = (salud + pensión empleador)
     */
    private function generatePayrollEntry(PayrollRun $run, string $userId): void
    {
        try {
            $companyId = Company::first()?->id;
            if (! $companyId) return;

            $run->loadMissing('details');

            $totalEarned      = (float) $run->total_earned;
            $totalNet         = (float) $run->total_net;
            $totalDeductions  = (float) $run->total_deductions;

            // Aportes patronales: suma de salud + pensión + ARL + parafiscales (CCF, SENA, ICBF)
            $healthEmployer   = (float) $run->details->sum('health_employer');
            $pensionEmployer  = (float) $run->details->sum('pension_employer');
            $arlEmployer      = (float) $run->details->sum('arl_employer');
            $parafiscales     = (float) $run->details->sum('ccf_employer')
                              + (float) $run->details->sum('sena_employer')
                              + (float) $run->details->sum('icbf_employer');
            $totalPatronal    = $healthEmployer + $pensionEmployer + $arlEmployer + $parafiscales;

            $voucher = AccountingDocument::create([
                'uuid'                       => Str::uuid(),
                'internal_code'              => 'NOM-' . $run->period_start->format('Ym') . '-' . strtoupper(Str::random(4)),
                'company_id'                 => $companyId,
                'user_id'                    => $userId,
                'third_party_id'             => null,
                'document_id'                => null,
                'type_document_operation_id' => 20, // Nómina
                'total'                      => $totalEarned,
                'debit'                      => 0,
                'credit'                     => 0,
                'issue_date'                 => $run->period_end->toDateString(),
                'annulled'                   => false,
            ]);

            $lines = [
                // Gasto sueldos (Db)
                ['account' => '51050501', 'debit' => $totalEarned, 'credit' => 0,           'desc' => 'Gasto sueldos y salarios'],
                // Nómina neta por pagar (Cr)
                ['account' => '25050501', 'debit' => 0,            'credit' => $totalNet,    'desc' => 'Nómina por pagar'],
                // Deducciones seguridad social empleado (Cr)
                ['account' => '25053001', 'debit' => 0,            'credit' => $totalDeductions - ($run->details->sum('income_tax_withholding') ?? 0), 'desc' => 'SS empleados por descontar'],
                // Retención en la fuente (Cr) si aplica
                ['account' => '23650101', 'debit' => 0,            'credit' => (float) $run->details->sum('income_tax_withholding'), 'desc' => 'Retención en la fuente nómina'],
                // Aportes patronales gasto (Db)
                ['account' => '51055401', 'debit' => $totalPatronal, 'credit' => 0,          'desc' => 'Aportes patronales (salud, pensión, ARL, parafiscales)'],
                // Aportes patronales por pagar (Cr)
                ['account' => '25054001', 'debit' => 0,            'credit' => $totalPatronal, 'desc' => 'Aportes patronales por pagar'],
            ];

            $totalDebit  = 0;
            $totalCredit = 0;

            foreach ($lines as $line) {
                if ($line['debit'] <= 0 && $line['credit'] <= 0) continue;
                AccountingDocumentDetail::create([
                    'accounting_document_id' => $voucher->id,
                    'accountable_id'         => $line['account'],
                    'accountable_type'       => 'chart_account',
                    'third_party_id'         => null,
                    'document_number'        => $voucher->internal_code,
                    'taxable_amount'         => max($line['debit'], $line['credit']),
                    'debit'                  => round($line['debit'], 4),
                    'credit'                 => round($line['credit'], 4),
                    'issue_date'             => $run->period_end->toDateString(),
                ]);
                $totalDebit  += $line['debit'];
                $totalCredit += $line['credit'];
            }

            $voucher->update(['debit' => round($totalDebit, 4), 'credit' => round($totalCredit, 4)]);

            if (abs($totalDebit - $totalCredit) > 0.01) {
                Log::warning("Asiento nómina descuadrado {$voucher->internal_code}: D={$totalDebit} C={$totalCredit}");
            }

        } catch (\Throwable $e) {
            Log::error("Error asiento nómina run {$run->id}: " . $e->getMessage());
        }
    }

    public function markPaid(PayrollRun $run): RedirectResponse
    {
        if (!$run->isApproved()) {
            return back()->withErrors(['status' => 'Solo se pueden marcar como pagadas las liquidaciones aprobadas.']);
        }

        $run->update(['status' => PayrollRun::STATUS_PAID]);

        return back()->with('success', "Liquidación {$run->name} marcada como pagada.");
    }

    public function cancel(Request $request, PayrollRun $run): RedirectResponse
    {
        if ($run->status === PayrollRun::STATUS_PAID) {
            return back()->withErrors(['status' => 'No se puede anular una liquidación ya pagada.']);
        }

        $run->update(['status' => PayrollRun::STATUS_CANCELLED, 'notes' => $request->reason ?? $run->notes]);

        // Desmarcar novedades para que puedan incluirse en otra liquidación
        PayrollNovelty::where('payroll_run_employee_id', '!=', null)
            ->whereIn('payroll_run_employee_id', $run->details()->pluck('id'))
            ->update(['is_processed' => false, 'payroll_run_employee_id' => null]);

        return back()->with('success', "Liquidación {$run->name} anulada.");
    }

    // ── Novedades ─────────────────────────────────────────────────

    public function noveltyIndex(Request $request): Response
    {
        $novelties = PayrollNovelty::with(['employee', 'contract'])
            ->when($request->employee_id, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->pending, fn ($q) => $q->pending())
            ->orderByDesc('date_from')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::active()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'identification_number']);
        $overtimeTypes = \DB::table('payroll_type_overtime_surcharges')->where('state', true)->get(['id', 'name', 'code', 'factor']);
        $disabilityTypes = \DB::table('payroll_type_disabilities')->get(['id', 'name', 'code']);

        return Inertia::render('Payroll/Novelties/Index', [
            'novelties'       => $novelties,
            'employees'       => $employees,
            'overtimeTypes'   => $overtimeTypes,
            'disabilityTypes' => $disabilityTypes,
            'filters'         => $request->only('employee_id', 'type', 'pending'),
        ]);
    }

    public function noveltyStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id'       => 'required|uuid|exists:employees,id',
            'type'              => 'required|string|in:overtime,disability,unpaid_leave,commission,bonus,loan,vacation,other',
            'overtime_type_id'  => 'nullable|integer|required_if:type,overtime',
            'overtime_hours'    => 'nullable|numeric|required_if:type,overtime|min:0.5',
            'disability_type_id'=> 'nullable|integer|required_if:type,disability',
            'disability_days'   => 'nullable|integer|required_if:type,disability|min:1',
            'vacation_days'     => 'nullable|integer|required_if:type,vacation|min:1',
            'unpaid_leave_days' => 'nullable|integer|required_if:type,unpaid_leave|min:1',
            'amount'            => 'nullable|numeric|min:0',
            'date_from'         => 'required|date',
            'date_to'           => 'nullable|date|after_or_equal:date_from',
            'description'       => 'nullable|string|max:500',
        ]);

        $contract = EmployeeContract::where('employee_id', $data['employee_id'])
            ->where('state', true)
            ->latest('start_date')
            ->firstOrFail();

        PayrollNovelty::create($data + [
            'contract_id' => $contract->id,
            'created_by'  => $request->user()->id,
        ]);

        return back()->with('success', 'Novedad registrada correctamente.');
    }

    public function export(Request $request, PayrollRun $run): mixed
    {
        $run->load(['details.employee', 'details.contract', 'createdBy']);
        $company = Company::first();
        $format  = $request->input('format', 'excel');

        if ($format === 'pdf') {
            return Pdf::loadView('exports.payroll_run', compact('run', 'company'))
                ->setPaper('a4', 'landscape')
                ->download("nomina_{$run->period_start}_{$run->period_end}.pdf");
        }

        $meta    = [$company?->business_name ?? 'Empresa', "Liquidación: {$run->name}", "Período: {$run->period_start} al {$run->period_end}"];
        $headers = ['Empleado', 'Cargo', 'Días', 'Sal. Básico', 'Transp.', 'Extras', 'Total Dev.', 'Salud Emp.', 'Pensión Emp.', 'Ret. Fte.', 'Total Ded.', 'NETO', 'Costo Emp.'];
        $rows    = $run->details->map(fn ($d) => [
            ($d->employee?->first_name ?? '') . ' ' . ($d->employee?->last_name ?? ''),
            $d->contract?->job_title ?? '—',
            $d->worked_days,
            $d->basic_salary,
            $d->transport_allowance,
            $d->overtime_amount + $d->commissions + $d->bonuses,
            $d->total_earned,
            $d->health_employee,
            $d->pension_employee,
            $d->income_tax_withholding,
            $d->total_deductions,
            $d->net_pay,
            $d->total_employer_cost,
        ])->toArray();

        // Fila de totales
        $rows[] = ['TOTALES', '', '', '', '', '', $run->total_earned, '', '', '', $run->total_deductions, $run->total_net, $run->total_employer_cost];

        return Excel::download(
            new ArrayExport($rows, $headers, 'Nómina', $meta),
            "nomina_{$run->period_start}_{$run->period_end}.xlsx"
        );
    }

    public function noveltyDestroy(PayrollNovelty $novelty): RedirectResponse
    {
        if ($novelty->is_processed) {
            return back()->withErrors(['error' => 'No se puede eliminar una novedad ya liquidada.']);
        }

        $novelty->delete();

        return back()->with('success', 'Novedad eliminada.');
    }
}
