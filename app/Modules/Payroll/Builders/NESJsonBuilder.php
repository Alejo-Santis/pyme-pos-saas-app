<?php

namespace App\Modules\Payroll\Builders;

use App\Modules\Core\Models\Company;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\PayrollRunEmployee;
use Carbon\Carbon;

/**
 * Constructor del JSON para Nómina Electrónica DIAN (NES).
 * Endpoint Nextpyme: POST /ubl2.1/payroll
 *
 * Genera el payload por empleado siguiendo el estándar UBL Colombia
 * para Documento Soporte de Pago de Nómina Electrónica.
 *
 * Referencia: Resolución DIAN 000013 de 2021 y Anexo Técnico NES v1.
 */
class NESJsonBuilder
{
    /**
     * Construye el JSON NES para un empleado específico de una liquidación.
     */
    public static function fromEmployee(
        PayrollRun         $run,
        PayrollRunEmployee $detail,
        Company            $company,
        bool               $sendmail = false
    ): array {
        $employee = $detail->employee;
        $contract = $detail->contract ?? $employee?->activeContract;

        $periodStart = Carbon::parse($run->period_start);
        $periodEnd   = Carbon::parse($run->period_end);

        return [
            // ── Encabezado del documento ──────────────────────────────
            'type_payroll_document_id' => 102, // 102 = Nómina individual
            'payroll_period_id'        => self::periodCode($run),
            'date_issue'               => now()->format('Y-m-d'),
            'time_issue'               => now()->format('H:i:s'),
            'settlement_start_date'    => $periodStart->format('Y-m-d'),
            'settlement_end_date'      => $periodEnd->format('Y-m-d'),
            'payroll_period_date_start' => $periodStart->format('Y-m-d'),
            'payroll_period_date_end'   => $periodEnd->format('Y-m-d'),
            'worked_time'              => (int) $detail->worked_days,
            'currency_id'              => 35, // 35 = COP
            'notes'                    => $run->notes ?? null,
            'sendmail'                 => $sendmail,

            // ── Empleador (empresa activa) ────────────────────────────
            'employer' => [
                'identification_number' => $company->identification_number,
                'dv'                    => $company->dv,
                'company'               => $company->business_name,
                'type_document_identification_id' => $company->type_document_identification_id ?? 6,
                'type_organization_id'  => $company->type_organization_id ?? 1,
                'type_regime_id'        => $company->type_regime_id ?? 1,
                'type_liability_id'     => $company->type_liability_id ?? 7,
                'municipality_id'       => $company->municipality_id,
                'address'               => $company->address,
            ],

            // ── Empleado ──────────────────────────────────────────────
            'employee' => [
                'identification_number'           => $employee?->identification_number ?? '',
                'type_document_identification_id' => self::docTypeId($employee?->document_type),
                'first_name'                      => $employee?->first_name ?? '',
                'middle_name'                     => $employee?->middle_name ?? null,
                'surname'                         => $employee?->last_name ?? '',
                'second_surname'                  => $employee?->second_lastname ?? null,
                'address'                         => $employee?->address ?? $company->address,
                'municipality_id'                 => self::municipalityId($employee?->city),
                'type_worker_id'                  => $contract?->type_worker_id ?? 1,
                'type_contract_id'                => $contract?->type_contract_id ?? 1,
                'high_risk_pension'               => false,
                'date_start_work'                 => $contract?->start_date?->format('Y-m-d'),
                'salary'                          => (float) $detail->salary,
                'comprehensive_salary'            => (bool) ($detail->is_comprehensive_salary ?? false),
                'sub_type_worker_id'              => 1,
            ],

            // ── Devengados ───────────────────────────────────────────
            'payment_dates'   => [['date' => $periodEnd->format('Y-m-d')]],
            'accrued_total'   => [
                'worked_days'         => (int) $detail->worked_days,
                'salary'              => round((float) $detail->basic_salary, 2),
                'transportation_allowance' => round((float) ($detail->transport_allowance ?? 0), 2),
                'overtimes_surcharges'     => self::buildOvertimes($detail),
                'bonuses'                  => self::buildBonuses($detail),
                'assistances'              => [],
                'legal_strikes'            => [],
                'other_concepts'           => self::buildOtherConcepts($detail),
                'compensations'            => [],
                'epic_bonuses'             => [],
                'commissions'              => round((float) ($detail->commissions ?? 0), 2),
                'third_party_payments'     => 0,
                'advances'                 => 0,
                'endowments'               => 0,
                'sustainment_support'      => 0,
                'telecommuting'            => 0,
                'withdrawal_bonus'         => 0,
                'compensation'             => 0,
                'indemnity'                => 0,
                'vacation'                 => self::buildVacation($detail),
                'primas'                   => self::buildPrima($detail),
                'layoffs'                  => self::buildCesantias($detail),
            ],

            // ── Deducciones ──────────────────────────────────────────
            'deductions_total' => [
                'health_deduction'   => round((float) $detail->health_employee, 2),
                'pension_fund'       => round((float) $detail->pension_employee, 2),
                'pension_security_fund'   => round((float) ($detail->solidarity_fund ?? 0), 2),
                'voluntary_pension'  => round((float) ($detail->voluntary_pension_deduction ?? 0), 2),
                'withholding_source' => round((float) ($detail->income_tax_withholding ?? 0), 2),
                'afc'                => 0,
                'cooperative'        => 0,
                'tax_lien'           => 0,
                'complementary_plans'=> 0,
                'education'          => 0,
                'refund'             => 0,
                'debt'               => round((float) ($detail->loans_deduction ?? 0), 2),
                'other_deductions'   => round((float) ($detail->other_deductions ?? 0), 2),
            ],

            // ── Totales ───────────────────────────────────────────────
            'accrued_total_amount'    => round((float) $detail->total_earned, 2),
            'deductions_total_amount' => round((float) $detail->total_deductions, 2),
            'total_voucher'           => round((float) $detail->net_pay, 2),

            // ── Pagos de seguridad social (aportes empleador) ─────────
            'social_security_payments' => [
                'health'   => round((float) $detail->health_employer, 2),
                'pension'  => round((float) $detail->pension_employer, 2),
                'arl'      => round((float) $detail->arl_employer, 2),
            ],

            // ── Parafiscales (aportes empleador) ────────────────────
            'parafiscal_payments' => [
                'sena'  => round((float) $detail->sena_employer, 2),
                'icbf'  => round((float) $detail->icbf_employer, 2),
                'caja'  => round((float) $detail->ccf_employer, 2),
            ],
        ];
    }

    // ── Helpers privados ─────────────────────────────────────────────────

    private static function periodCode(PayrollRun $run): int
    {
        // DIAN: 1=Diario, 2=Semanal, 3=Decena, 4=Catorce días, 5=Quincena, 6=Mensual
        return 6; // Mensual por defecto — puede parametrizarse según payroll_period_id
    }

    private static function docTypeId(?string $type): int
    {
        return match ($type) {
            'CC'  => 3,
            'CE'  => 5,
            'NIT' => 6,
            'TI'  => 2,
            'RC'  => 1,
            'PA'  => 7,
            default => 3, // CC
        };
    }

    private static function municipalityId(?string $city): int
    {
        // Bogotá por defecto si no hay municipio configurado
        return 149;
    }

    private static function buildOvertimes(PayrollRunEmployee $detail): array
    {
        if (empty($detail->novelties_detail)) {
            return [];
        }
        $overtimes = [];
        foreach (($detail->novelties_detail ?? []) as $novelty) {
            if (($novelty['type'] ?? '') === 'overtime') {
                $overtimes[] = [
                    'type'    => $novelty['overtime_type'] ?? 'DIURNA',
                    'quantity'=> (int) ($novelty['quantity'] ?? 0),
                    'payment' => round((float) ($novelty['amount'] ?? 0), 2),
                    'start_time'=> $novelty['start_time'] ?? null,
                    'end_time'  => $novelty['end_time'] ?? null,
                ];
            }
        }
        return $overtimes;
    }

    private static function buildBonuses(PayrollRunEmployee $detail): array
    {
        $amount = (float) ($detail->bonuses ?? 0);
        if ($amount <= 0) {
            return [];
        }
        return [['salary_non_const' => $amount, 'salary_non_const_food' => 0]];
    }

    private static function buildOtherConcepts(PayrollRunEmployee $detail): array
    {
        $amount = (float) ($detail->other_income ?? 0);
        if ($amount <= 0) {
            return [];
        }
        return [['description' => 'Otros ingresos', 'salary_non_const' => $amount]];
    }

    private static function buildVacation(PayrollRunEmployee $detail): array
    {
        $amount = (float) ($detail->vacation_amount ?? 0);
        if ($amount <= 0) {
            return [];
        }
        return [['common' => [['quantity' => 1, 'payment' => $amount]]]];
    }

    private static function buildPrima(PayrollRunEmployee $detail): array
    {
        $amount = (float) ($detail->prima_amount ?? 0);
        if ($amount <= 0) {
            return [];
        }
        return [['quantity' => 1, 'payment' => $amount, 'payment_non_salarial' => 0]];
    }

    private static function buildCesantias(PayrollRunEmployee $detail): array
    {
        $amount    = (float) ($detail->severance_amount ?? 0);
        $interests = (float) ($detail->severance_interests ?? 0);
        if ($amount <= 0) {
            return [];
        }
        return [[
            'payment'              => $amount,
            'percentage'           => 8.33,
            'interests_payment'    => $interests,
            'interests_percentage' => 12,
        ]];
    }
}
