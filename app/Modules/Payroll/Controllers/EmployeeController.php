<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Imports\EmployeeImport;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Shared\Exports\ArrayExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::with('activeContract')
            ->when($request->search, fn ($q, $s) =>
                $q->where(fn ($q2) =>
                    $q2->whereRaw("CONCAT(first_name,' ',last_name) ILIKE ?", ["%{$s}%"])
                       ->orWhere('identification_number', 'like', "%{$s}%")
                )
            )
            ->when($request->state !== null, fn ($q) => $q->where('state', $request->boolean('state')))
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Payroll/Employees/Index', [
            'employees' => $employees,
            'filters'   => $request->only('search', 'state'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Payroll/Employees/Form', [
            'employee'      => null,
            'contract'      => null,
            'typeContracts' => $this->typeContracts(),
            'typePeriods'   => $this->typePeriods(),
            'typeWorkers'   => $this->typeWorkers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employeeData = $request->validate([
            'document_type'          => 'required|string|max:5',
            'identification_number'  => 'required|string|max:20|unique:employees',
            'first_name'             => 'required|string|max:80',
            'middle_name'            => 'nullable|string|max:80',
            'last_name'              => 'required|string|max:80',
            'second_lastname'        => 'nullable|string|max:80',
            'email'                  => 'nullable|email|max:150',
            'phone'                  => 'nullable|string|max:20',
            'address'                => 'nullable|string|max:255',
            'city'                   => 'nullable|string|max:80',
            'department'             => 'nullable|string|max:80',
            'birthdate'              => 'nullable|date',
            'blood_type'             => 'nullable|string|max:5',
            'gender'                 => 'required|integer|in:1,2,3',
            'marital_status_id'      => 'nullable|integer',
            'emergency_contact'      => 'nullable|string|max:100',
            'emergency_phone'        => 'nullable|string|max:20',
        ]);

        $contractData = $request->validate([
            'type_contract_id'           => 'required|integer',
            'type_worker_id'             => 'required|integer',
            'payroll_period_id'          => 'required|integer',
            'job_title'                  => 'required|string|max:100',
            'cost_center'                => 'nullable|string|max:60',
            'arl_risk_class'             => 'required|integer|between:1,5',
            'salary'                     => 'required|numeric|min:' . \App\Modules\Payroll\Services\PayrollCalculationService::SMMLV,
            'is_comprehensive_salary'    => 'boolean',
            'has_transport_allowance'    => 'boolean',
            'voluntary_health_amount'    => 'nullable|numeric|min:0',
            'voluntary_pension_amount'   => 'nullable|numeric|min:0',
            'eps_name'                   => 'nullable|string|max:100',
            'afp_name'                   => 'nullable|string|max:100',
            'arl_name'                   => 'nullable|string|max:100',
            'ccf_name'                   => 'nullable|string|max:100',
            'has_income_tax_withholding' => 'boolean',
            'income_tax_withholding_pct' => 'nullable|numeric|between:0,100',
            'start_date'                 => 'required|date',
            'end_date'                   => 'nullable|date|after:start_date',
            'trial_end_date'             => 'nullable|date',
        ]);

        $employee = Employee::create($employeeData);
        $employee->contracts()->create($contractData + ['created_by' => $request->user()->id]);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', "Empleado {$employee->full_name} creado correctamente.");
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['contracts', 'activeContract', 'novelties' => fn ($q) => $q->latest()->limit(20)]);

        return Inertia::render('Payroll/Employees/Show', [
            'employee'      => $employee,
            'typeContracts' => $this->typeContracts(),
            'typePeriods'   => $this->typePeriods(),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $employee->load('activeContract');

        return Inertia::render('Payroll/Employees/Form', [
            'employee'      => $employee,
            'contract'      => $employee->activeContract,
            'typeContracts' => $this->typeContracts(),
            'typePeriods'   => $this->typePeriods(),
            'typeWorkers'   => $this->typeWorkers(),
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'document_type'         => 'required|string|max:5',
            'identification_number' => "required|string|max:20|unique:employees,identification_number,{$employee->id}",
            'first_name'            => 'required|string|max:80',
            'middle_name'           => 'nullable|string|max:80',
            'last_name'             => 'required|string|max:80',
            'second_lastname'       => 'nullable|string|max:80',
            'email'                 => 'nullable|email|max:150',
            'phone'                 => 'nullable|string|max:20',
            'address'               => 'nullable|string|max:255',
            'city'                  => 'nullable|string|max:80',
            'department'            => 'nullable|string|max:80',
            'birthdate'             => 'nullable|date',
            'blood_type'            => 'nullable|string|max:5',
            'gender'                => 'required|integer|in:1,2,3',
            'marital_status_id'     => 'nullable|integer',
            'emergency_contact'     => 'nullable|string|max:100',
            'emergency_phone'       => 'nullable|string|max:20',
            'state'                 => 'boolean',
        ]);

        $employee->update($data);

        return redirect()->route('payroll.employees.show', $employee)
            ->with('success', 'Empleado actualizado correctamente.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->update(['state' => false]);
        $employee->delete();

        return redirect()->route('payroll.employees.index')
            ->with('success', 'Empleado retirado correctamente.');
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function downloadTemplate()
    {
        $headers = [
            'tipo_documento', 'numero_documento',
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
            'email', 'telefono', 'ciudad', 'departamento',
            'fecha_nacimiento', 'genero',
            'tipo_contrato', 'tipo_trabajador', 'periodo_nomina',
            'cargo', 'salario', 'salario_integral', 'auxilio_transporte',
            'clase_arl', 'fecha_inicio',
            'eps', 'afp', 'arl', 'ccf',
        ];

        $example = [
            'CC', '10234567',
            'Juan', 'Carlos', 'Pérez', 'García',
            'juan.perez@email.com', '3001234567', 'Bogotá', 'Cundinamarca',
            '15/03/1990', 'M',
            'IND', 'DEP', 'MEN',
            'Auxiliar Contable', '1600000', 'NO', 'SI',
            '1', '01/02/2025',
            'Sura EPS', 'Protección AFP', 'Positiva ARL', 'Compensar CCF',
        ];

        $notes = [
            'tipo_documento: CC, CE, PASAPORTE, TI',
            'genero: M=Masculino, F=Femenino',
            'tipo_contrato: IND=Indefinido, FIJ=Fijo, OBR=Obra, APR=Aprendizaje, TEM=Temporal',
            'tipo_trabajador: DEP=Dependiente, IND=Independiente',
            'periodo_nomina: MEN=Mensual, QUI=Quincenal, SEM=Semanal',
            'salario_integral / auxilio_transporte: SI o NO',
            'clase_arl: 1 al 5',
            'fechas: formato DD/MM/YYYY',
            'salario mínimo: $' . number_format(\App\Modules\Payroll\Services\PayrollCalculationService::SMMLV, 0, ',', '.'),
        ];

        return Excel::download(
            new ArrayExport([$example], $headers, 'Plantilla Importación Empleados', $notes),
            'plantilla-empleados.xlsx'
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $import = new EmployeeImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        return back()->with([
            'import_imported' => $import->imported,
            'import_errors'   => $import->errors,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function typeContracts(): array
    {
        return \DB::table('payroll_type_contracts')->where('state', true)->get(['id', 'name'])->toArray();
    }

    private function typePeriods(): array
    {
        return \DB::table('payroll_periods')->where('state', true)->get(['id', 'name'])->toArray();
    }

    private function typeWorkers(): array
    {
        return \DB::table('payroll_type_workers')->where('state', true)->get(['id', 'name'])->toArray();
    }
}
