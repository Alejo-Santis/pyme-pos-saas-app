<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayrollLegalParametersSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('payroll_legal_parameters')) {
            return;
        }

        $now = now();

        $rows = [
            [
                'year'                    => 2025,
                'smmlv'                   => 1423500,
                'transport_allowance'     => 200000,
                'uvt'                     => 49799,
                'notes'                   => 'Parámetros base 2025. Revisar contra decretos vigentes antes de liquidar producción.',
            ],
            [
                'year'                    => 2026,
                'smmlv'                   => 1750905,
                'transport_allowance'     => 249095,
                'uvt'                     => 52374,
                'notes'                   => 'Parámetros base 2026. SMMLV/auxilio según decretos 1469/1470 de 2025; UVT según Resolución DIAN 000238 de 2025.',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('payroll_legal_parameters')->updateOrInsert(
                ['year' => $row['year']],
                $row + [
                    'health_employee_rate'     => 0.04,
                    'pension_employee_rate'    => 0.04,
                    'solidarity_base_rate'     => 0.01,
                    'health_employer_rate'     => 0.085,
                    'pension_employer_rate'    => 0.12,
                    'ccf_rate'                 => 0.04,
                    'sena_rate'                => 0.02,
                    'icbf_rate'                => 0.03,
                    'cesantias_interest_rate'  => 0.12,
                    'is_active'                => true,
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ],
            );
        }
    }
}
