<?php

namespace App\Modules\Payroll\Imports;

use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\EmployeeContract;
use App\Modules\Payroll\Services\PayrollCalculationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeImport implements ToCollection, WithHeadingRow
{
    public int   $imported = 0;
    public array $errors   = [];

    private float $smmlv;

    // Catálogos en memoria
    private array $contractTypes  = [];
    private array $workerTypes    = [];
    private array $periods        = [];
    private array $maritalStatuses = [];

    public function __construct()
    {
        $this->contractTypes  = DB::table('payroll_type_contracts')->pluck('id', 'code')->toArray();
        $this->workerTypes    = DB::table('payroll_type_workers')->pluck('id', 'code')->toArray();
        $this->periods        = DB::table('payroll_periods')->pluck('id', 'code')->toArray();
        $this->maritalStatuses = DB::table('payroll_marital_statuses')->pluck('id', 'code')->toArray();
        $this->smmlv = PayrollCalculationService::smmlv((int) now()->year);
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            try {
                $this->processRow($row->toArray(), $rowNum);
            } catch (\Throwable $e) {
                $this->errors[] = "Fila {$rowNum}: " . $e->getMessage();
            }
        }
    }

    private function processRow(array $row, int $rowNum): void
    {
        $row = array_map('trim', $row);

        // ── Datos personales ──────────────────────────────────────────────
        $docType      = strtoupper($row['tipo_documento']    ?? 'CC');
        $idNumber     = $row['numero_documento']             ?? '';
        $firstName    = $row['primer_nombre']                ?? '';
        $middleName   = $row['segundo_nombre']               ?? '';
        $lastName     = $row['primer_apellido']              ?? '';
        $secondLast   = $row['segundo_apellido']             ?? '';
        $email        = $row['email']                        ?? '';
        $phone        = $row['telefono']                     ?? '';
        $address      = $row['direccion']                    ?? '';
        $city         = $row['ciudad']                       ?? '';
        $department   = $row['departamento']                 ?? '';
        $birthdate    = $this->parseDate($row['fecha_nacimiento'] ?? '');
        $gender       = $this->parseGender($row['genero']   ?? 'M');
        $maritalCode  = strtoupper($row['estado_civil']     ?? 'S');

        // ── Datos del contrato ────────────────────────────────────────────
        $contractCode = strtoupper($row['tipo_contrato']    ?? 'IND');
        $workerCode   = strtoupper($row['tipo_trabajador']  ?? 'DEP');
        $periodCode   = strtoupper($row['periodo_nomina']   ?? 'MEN');
        $jobTitle     = $row['cargo']                       ?? '';
        $salary       = $this->parseNum($row['salario']     ?? 0);
        $isIntegral   = $this->parseBool($row['salario_integral']    ?? '');
        $hasTransport = $this->parseBool($row['auxilio_transporte']  ?? 'SI');
        $arlClass     = (int) ($row['clase_arl']            ?? 1);
        $startDate    = $this->parseDate($row['fecha_inicio']        ?? '');
        $epsName      = $row['eps']                         ?? '';
        $afpName      = $row['afp']                         ?? '';
        $arlName      = $row['arl']                         ?? '';
        $ccfName      = $row['ccf']                         ?? '';

        // ── Validaciones ──────────────────────────────────────────────────
        if (empty($idNumber) || empty($firstName) || empty($lastName)) {
            $this->errors[] = "Fila {$rowNum}: numero_documento, primer_nombre y primer_apellido son obligatorios.";
            return;
        }

        if (Employee::where('identification_number', $idNumber)->exists()) {
            $this->errors[] = "Fila {$rowNum}: El empleado con documento {$idNumber} ya existe.";
            return;
        }

        if ($salary < $this->smmlv) {
            $this->errors[] = "Fila {$rowNum}: El salario ({$salary}) es inferior al SMMLV ({$this->smmlv}).";
            return;
        }

        if (!in_array($arlClass, [1, 2, 3, 4, 5])) {
            $this->errors[] = "Fila {$rowNum}: clase_arl debe ser entre 1 y 5.";
            return;
        }

        $contractTypeId  = $this->contractTypes[$contractCode]   ?? (reset($this->contractTypes) ?: null);
        $workerTypeId    = $this->workerTypes[$workerCode]        ?? (reset($this->workerTypes) ?: null);
        $periodId        = $this->periods[$periodCode]            ?? (reset($this->periods) ?: null);
        $maritalStatusId = $this->maritalStatuses[$maritalCode]   ?? (reset($this->maritalStatuses) ?: null);

        // Transporte: solo para salarios ≤ 2 SMMLV
        if ($salary > $this->smmlv * 2) {
            $hasTransport = false;
        }

        DB::transaction(function () use (
            $docType, $idNumber, $firstName, $middleName, $lastName, $secondLast,
            $email, $phone, $address, $city, $department, $birthdate, $gender, $maritalStatusId,
            $contractTypeId, $workerTypeId, $periodId, $jobTitle, $salary, $isIntegral,
            $hasTransport, $arlClass, $startDate, $epsName, $afpName, $arlName, $ccfName
        ) {
            $employee = Employee::create([
                'document_type'        => $docType,
                'identification_number'=> $idNumber,
                'first_name'           => $firstName,
                'middle_name'          => $middleName  ?: null,
                'last_name'            => $lastName,
                'second_lastname'      => $secondLast  ?: null,
                'email'                => $email       ?: null,
                'phone'                => $phone       ?: null,
                'address'              => $address     ?: null,
                'city'                 => $city        ?: null,
                'department'           => $department  ?: null,
                'birthdate'            => $birthdate,
                'gender'               => $gender,
                'marital_status_id'    => $maritalStatusId,
                'state'                => true,
            ]);

            EmployeeContract::create([
                'employee_id'           => $employee->id,
                'type_contract_id'      => $contractTypeId,
                'type_worker_id'        => $workerTypeId,
                'payroll_period_id'     => $periodId,
                'job_title'             => $jobTitle    ?: null,
                'salary'                => $salary,
                'is_comprehensive_salary'   => $isIntegral,
                'has_transport_allowance'   => $hasTransport,
                'arl_risk_class'            => $arlClass,
                'eps_name'              => $epsName     ?: null,
                'afp_name'              => $afpName     ?: null,
                'arl_name'              => $arlName     ?: null,
                'ccf_name'              => $ccfName     ?: null,
                'start_date'            => $startDate   ?: now()->toDateString(),
                'has_income_tax_withholding' => false,
                'state'                 => true,
            ]);
        });

        $this->imported++;
    }

    private function parseDate(string $val): ?string
    {
        if (empty($val)) return null;
        // Intentar formato DD/MM/YYYY o YYYY-MM-DD
        $val = trim($val);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $val, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
            return $val;
        }
        return null;
    }

    private function parseGender(string $val): int
    {
        return match(strtoupper(trim($val))) {
            'M', 'MASCULINO', 'HOMBRE' => 1,
            'F', 'FEMENINO', 'MUJER'   => 2,
            default                    => 3,
        };
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtoupper(trim($value)), ['SI', 'SÍ', 'S', 'YES', 'Y', '1', 'TRUE']);
    }

    private function parseNum(mixed $val): float
    {
        $val = str_replace(['.', ',', '$', ' '], ['', '.', '', ''], (string) $val);
        return (float) $val;
    }
}
