<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayrollSocialBenefit;
use App\Modules\Payroll\Services\PayrollCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prestaciones Sociales: Prima, Cesantías, Intereses de Cesantías, Vacaciones.
 *
 * Colombia — Ley 21/1982, Ley 50/1990, Código Sustantivo del Trabajo:
 *  Prima de servicios : Salario * días / 360   (se paga 2 veces al año: jun y dic)
 *  Cesantías          : Salario * días / 360   (1 mes por año trabajado)
 *  Intereses cesantías: Cesantías * 12% anual  (se liquidan en enero)
 *  Vacaciones         : Salario * días / 720   (15 días hábiles por año)
 */
class SocialBenefitController extends Controller
{
    public function index(Request $request): Response
    {
        $benefits = PayrollSocialBenefit::with(['employee', 'contract'])
            ->when($request->employee_id, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->year, fn ($q, $y) => $q->where('year', $y))
            ->when($request->paid !== null && $request->paid !== '', fn ($q) =>
                $q->where('is_paid', (bool) $request->paid)
            )
            ->orderByDesc('year')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $employees = Employee::active()->orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Payroll/SocialBenefits/Index', [
            'benefits'  => $benefits,
            'employees' => $employees,
            'filters'   => $request->only('employee_id', 'type', 'year', 'paid'),
            'types'     => [
                PayrollSocialBenefit::TYPE_PRIMA               => 'Prima de Servicios',
                PayrollSocialBenefit::TYPE_CESANTIAS           => 'Cesantías',
                PayrollSocialBenefit::TYPE_INTERESES_CESANTIAS => 'Intereses sobre Cesantías',
                PayrollSocialBenefit::TYPE_VACACIONES          => 'Vacaciones',
            ],
        ]);
    }

    /**
     * Vista de cálculo / liquidación de una prestación para un empleado.
     */
    public function calculate(Request $request): Response
    {
        $employees = Employee::active()
            ->with(['activeContract'])
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $preview = null;

        if ($request->filled(['employee_id', 'type', 'date_from', 'date_to'])) {
            $preview = $this->computeBenefit(
                $request->employee_id,
                $request->type,
                $request->date_from,
                $request->date_to,
            );
        }

        return Inertia::render('Payroll/SocialBenefits/Calculate', [
            'employees' => $employees,
            'types'     => [
                PayrollSocialBenefit::TYPE_PRIMA               => 'Prima de Servicios',
                PayrollSocialBenefit::TYPE_CESANTIAS           => 'Cesantías',
                PayrollSocialBenefit::TYPE_INTERESES_CESANTIAS => 'Intereses sobre Cesantías',
                PayrollSocialBenefit::TYPE_VACACIONES          => 'Vacaciones',
            ],
            'preview'   => $preview,
            'filters'   => $request->only('employee_id', 'type', 'date_from', 'date_to'),
            'smmlv'     => PayrollCalculationService::smmlv((int) now()->year),
        ]);
    }

    /**
     * Guarda la liquidación calculada.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id'  => 'required|uuid|exists:employees,id',
            'contract_id'  => 'required|uuid|exists:employee_contracts,id',
            'type'         => 'required|string|in:prima,cesantias,intereses_cesantias,vacaciones',
            'year'         => 'required|integer|min:2000|max:2100',
            'semester'     => 'nullable|integer|in:1,2',
            'base_salary'  => 'required|numeric|min:0',
            'days_worked'  => 'required|integer|min:1',
            'amount'       => 'required|numeric|min:0',
        ]);

        PayrollSocialBenefit::create($data + ['is_paid' => false]);

        return redirect()->route('payroll.benefits.index')
            ->with('success', PayrollSocialBenefit::typeLabel($data['type']) . ' liquidada correctamente.');
    }

    /**
     * Marcar como pagada una prestación social.
     */
    public function pay(Request $request, PayrollSocialBenefit $benefit): RedirectResponse
    {
        if ($benefit->is_paid) {
            return back()->withErrors(['error' => 'Esta prestación ya fue pagada.']);
        }

        $data = $request->validate([
            'pay_date'    => 'required|date',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        $benefit->update([
            'is_paid'     => true,
            'pay_date'    => $data['pay_date'],
            'paid_amount' => $data['paid_amount'],
        ]);

        return back()->with('success', 'Prestación registrada como pagada.');
    }

    /**
     * Eliminar liquidación no pagada.
     */
    public function destroy(PayrollSocialBenefit $benefit): RedirectResponse
    {
        if ($benefit->is_paid) {
            return back()->withErrors(['error' => 'No se puede eliminar una prestación ya pagada.']);
        }

        $benefit->delete();

        return back()->with('success', 'Liquidación eliminada.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function computeBenefit(string $employeeId, string $type, string $dateFrom, string $dateTo): ?array
    {
        $contract = EmployeeContract::where('employee_id', $employeeId)
            ->where('state', true)
            ->latest('start_date')
            ->first();

        if (! $contract) return null;

        $from      = \Carbon\Carbon::parse($dateFrom);
        $to        = \Carbon\Carbon::parse($dateTo);
        $days      = (int) $from->diffInDays($to) + 1;
        $salary    = (float) $contract->salary;
        $transport = $contract->has_transport_allowance ? PayrollCalculationService::transportAllowance((int) $to->year) : 0;
        $base      = $salary + ($type !== PayrollSocialBenefit::TYPE_VACACIONES ? $transport : 0);

        $amount = match ($type) {
            PayrollSocialBenefit::TYPE_PRIMA               => round($base * $days / 360, 2),
            PayrollSocialBenefit::TYPE_CESANTIAS           => round($base * $days / 360, 2),
            // Intereses = 12% anual sobre las cesantías YA prorrateadas del período
            // (($base*$days/360) ya es el monto de cesantías del período — no se
            // vuelve a multiplicar por días/360, eso subestimaba el interés a la
            // mitad o más en cualquier período distinto de un año completo).
            PayrollSocialBenefit::TYPE_INTERESES_CESANTIAS => round(($base * $days / 360) * 0.12, 2),
            PayrollSocialBenefit::TYPE_VACACIONES          => round($salary * $days / 720, 2),
            default                                        => 0,
        };

        $year     = $to->year;
        $semester = $to->month <= 6 ? 1 : 2;

        return [
            'employee_id'  => $employeeId,
            'contract_id'  => $contract->id,
            'type'         => $type,
            'year'         => $year,
            'semester'     => $semester,
            'base_salary'  => $salary,
            'days_worked'  => $days,
            'amount'       => $amount,
            'date_from'    => $dateFrom,
            'date_to'      => $dateTo,
            'label'        => PayrollSocialBenefit::typeLabel($type),
            'transport'    => $transport,
        ];
    }
}
