<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Módulo de Nómina Colombiana.
     * Cubre: empleados, contratos, liquidaciones mensuales,
     * novedades (horas extra, incapacidades, permisos, comisiones),
     * prestaciones sociales y parafiscales.
     */
    public function up(): void
    {
        // ──────────────────────────────────────────────────────────
        // CATÁLOGOS (se pueblan aquí mismo al crear el schema)
        // ──────────────────────────────────────────────────────────

        // Tipos de contrato
        Schema::create('payroll_type_contracts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 60);
            $table->string('code', 10)->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        DB::table('payroll_type_contracts')->insert([
            ['id' => 1, 'name' => 'Término Fijo',       'code' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Término Indefinido',  'code' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Obra o Labor',        'code' => '3', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Aprendizaje',         'code' => '4', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Prácticas',           'code' => '5', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Períodos de pago
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 40);
            $table->string('code', 10)->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        DB::table('payroll_periods')->insert([
            ['id' => 1, 'name' => 'Mensual',     'code' => 'M',  'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Quincenal',   'code' => 'Q',  'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Semanal',     'code' => 'S',  'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Decenal',     'code' => 'D',  'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tipos de trabajador (DIAN nómina electrónica)
        Schema::create('payroll_type_workers', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 60);
            $table->string('code', 10)->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        DB::table('payroll_type_workers')->insert([
            ['id' => 1, 'name' => 'Empleado',                     'code' => '01', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Pensionado',                   'code' => '02', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Aprendiz SENA',                'code' => '03', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Empleado-Pensionado',          'code' => '04', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Trabajador Independiente',     'code' => '05', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Estado civil
        Schema::create('payroll_marital_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 40);
            $table->string('code', 10)->nullable();
            $table->timestamps();
        });

        DB::table('payroll_marital_statuses')->insert([
            ['id' => 1, 'name' => 'Soltero/a',         'code' => 'S', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Casado/a',           'code' => 'C', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Unión Libre',        'code' => 'U', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Divorciado/a',       'code' => 'D', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Viudo/a',            'code' => 'V', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tipos de incapacidad
        Schema::create('payroll_type_disabilities', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 60);
            $table->string('code', 10)->nullable();
            $table->timestamps();
        });

        DB::table('payroll_type_disabilities')->insert([
            ['id' => 1, 'name' => 'Enfermedad General',  'code' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Accidente de Trabajo','code' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Enfermedad Laboral',  'code' => '3', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tipos de horas extra y recargos (Colombia)
        Schema::create('payroll_type_overtime_surcharges', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80);
            $table->string('code', 20)->nullable();
            $table->decimal('factor', 5, 4)->default(1.0000); // 1.25 = 25% extra
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        // Factores según Art. 168-171 CST Colombia
        DB::table('payroll_type_overtime_surcharges')->insert([
            ['id' => 1, 'name' => 'Hora Extra Diurna (6am-9pm)',           'code' => 'HED', 'factor' => 1.2500, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Hora Extra Nocturna (9pm-6am)',         'code' => 'HEN', 'factor' => 1.7500, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Recargo Nocturno Ordinario',            'code' => 'RNO', 'factor' => 1.3500, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'Hora Dominical/Festivo Diurna',         'code' => 'HDD', 'factor' => 1.7500, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'Hora Dominical/Festivo Nocturna',       'code' => 'HDN', 'factor' => 2.1000, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'Extra Dominical/Festivo Diurna',        'code' => 'EDD', 'factor' => 2.0000, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'Extra Dominical/Festivo Nocturna',      'code' => 'EDN', 'factor' => 2.5000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ──────────────────────────────────────────────────────────
        // EMPLEADOS
        // ──────────────────────────────────────────────────────────

        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Identificación
            $table->string('document_type', 5)->default('CC');  // CC, CE, PA, TI, NIT
            $table->string('identification_number', 20)->unique();
            $table->string('dv', 2)->nullable();                  // dígito de verificación

            // Datos personales
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('last_name', 80);
            $table->string('second_lastname', 80)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 80)->nullable();               // municipio
            $table->string('department', 80)->nullable();         // departamento
            $table->date('birthdate')->nullable();
            $table->string('blood_type', 5)->nullable();          // A+, B-, O+, etc.
            $table->unsignedTinyInteger('gender')->default(1);    // 1=M, 2=F, 3=Otro
            $table->unsignedSmallInteger('marital_status_id')->nullable();
            $table->foreign('marital_status_id')->references('id')->on('payroll_marital_statuses')->nullOnDelete();

            // Contacto de emergencia
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 20)->nullable();

            $table->boolean('state')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // ──────────────────────────────────────────────────────────
        // CONTRATOS
        // ──────────────────────────────────────────────────────────

        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('finished_by')->nullable()->constrained('users')->nullOnDelete();

            // Tipo de contrato y trabajador
            $table->unsignedSmallInteger('type_contract_id')->default(2);
            $table->foreign('type_contract_id')->references('id')->on('payroll_type_contracts');
            $table->unsignedSmallInteger('type_worker_id')->default(1);
            $table->foreign('type_worker_id')->references('id')->on('payroll_type_workers');
            $table->unsignedSmallInteger('payroll_period_id')->default(1);
            $table->foreign('payroll_period_id')->references('id')->on('payroll_periods');

            // Datos del cargo
            $table->string('contract_number', 30)->nullable();
            $table->string('job_title', 100);                     // cargo
            $table->string('cost_center', 60)->nullable();        // centro de costo
            $table->unsignedTinyInteger('arl_risk_class')->default(1); // clase riesgo ARL 1-5

            // Salario
            $table->decimal('salary', 15, 2);                    // salario básico mensual
            $table->boolean('is_comprehensive_salary')->default(false); // salario integral
            $table->boolean('has_transport_allowance')->default(true);  // auxilio de transporte

            // Aportes voluntarios
            $table->decimal('voluntary_health_amount', 15, 2)->default(0);   // AFC salud
            $table->decimal('voluntary_pension_amount', 15, 2)->default(0);  // pensión voluntaria

            // Entidades de seguridad social (nombre libre por simplicidad)
            $table->string('eps_name', 100)->nullable();          // administradora de salud
            $table->string('afp_name', 100)->nullable();          // fondo de pensiones
            $table->string('arl_name', 100)->nullable();          // ARL
            $table->string('ccf_name', 100)->nullable();          // caja de compensación

            // Retención en la fuente
            $table->boolean('has_income_tax_withholding')->default(false);
            $table->decimal('income_tax_withholding_pct', 5, 2)->default(0); // % fijo si aplica

            // Fechas
            $table->date('start_date');
            $table->date('end_date')->nullable();                  // null = indefinido
            $table->date('trial_end_date')->nullable();            // fin período de prueba

            $table->boolean('state')->default(true);               // true = vigente
            $table->softDeletes();
            $table->timestamps();
        });

        // ──────────────────────────────────────────────────────────
        // LIQUIDACIONES (RUNS DE NÓMINA)
        // ──────────────────────────────────────────────────────────

        // Encabezado de una liquidación de nómina
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 100);                          // ej: "Nómina Mayo 2025"
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('payroll_period_id')->default(1);
            $table->foreign('payroll_period_id')->references('id')->on('payroll_periods');

            // Totales del período
            $table->decimal('total_earned', 15, 2)->default(0);   // total devengado
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);       // neto a pagar
            $table->decimal('total_employer_cost', 15, 2)->default(0); // costo total empleador

            // Estados: draft, approved, paid, cancelled
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();

            $table->timestamps();
        });

        // Detalle por empleado dentro de una liquidación
        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('employees');
            $table->foreignUuid('contract_id')->constrained('employee_contracts');

            // Datos del período
            $table->integer('worked_days')->default(30);           // días trabajados (max 30)
            $table->decimal('salary', 15, 2);                     // salario base del período
            $table->boolean('is_comprehensive_salary')->default(false);

            // ── DEVENGADOS ──
            $table->decimal('basic_salary', 15, 2)->default(0);   // salario proporcional días
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0); // horas extra y recargos
            $table->decimal('commissions', 15, 2)->default(0);
            $table->decimal('bonuses', 15, 2)->default(0);         // bonificaciones no salariales
            $table->decimal('vacation_amount', 15, 2)->default(0); // vacaciones disfrutadas
            $table->decimal('prima_amount', 15, 2)->default(0);    // prima de servicios
            $table->decimal('severance_amount', 15, 2)->default(0); // cesantías
            $table->decimal('severance_interests', 15, 2)->default(0); // intereses cesantías
            $table->decimal('disability_amount', 15, 2)->default(0); // incapacidades a cargo empresa
            $table->decimal('other_income', 15, 2)->default(0);
            $table->decimal('total_earned', 15, 2)->default(0);    // suma devengados

            // ── DEDUCCIONES EMPLEADO ──
            $table->decimal('health_employee', 15, 2)->default(0); // 4% salud empleado
            $table->decimal('pension_employee', 15, 2)->default(0); // 4% pensión empleado
            $table->decimal('solidarity_fund', 15, 2)->default(0); // fondo solidaridad (>4 SMMLV)
            $table->decimal('income_tax_withholding', 15, 2)->default(0); // retención en la fuente
            $table->decimal('voluntary_health_deduction', 15, 2)->default(0);
            $table->decimal('voluntary_pension_deduction', 15, 2)->default(0);
            $table->decimal('loans_deduction', 15, 2)->default(0); // libranzas / préstamos
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);

            // ── NETO ──
            $table->decimal('net_pay', 15, 2)->default(0);         // total_earned - total_deductions

            // ── APORTES EMPLEADOR (costo empresa, no descuento empleado) ──
            $table->decimal('health_employer', 15, 2)->default(0); // 8.5%
            $table->decimal('pension_employer', 15, 2)->default(0); // 12%
            $table->decimal('arl_employer', 15, 2)->default(0);    // 0.522% - 6.96%
            $table->decimal('ccf_employer', 15, 2)->default(0);    // 4% Caja Compensación
            $table->decimal('sena_employer', 15, 2)->default(0);   // 2% (exonerado si ≤10 SMMLV)
            $table->decimal('icbf_employer', 15, 2)->default(0);   // 3% (exonerado si ≤10 SMMLV)
            $table->decimal('total_employer_cost', 15, 2)->default(0); // salary + todas las cargas

            // Detalle de novedades en JSON (horas extra, incapacidades del período)
            $table->json('novelties_detail')->nullable();

            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
        });

        // ──────────────────────────────────────────────────────────
        // NOVEDADES (horas extra, incapacidades, permisos, comisiones)
        // Independientes de la liquidación: se acumulan y se incluyen
        // en la próxima liquidación del período.
        // ──────────────────────────────────────────────────────────

        Schema::create('payroll_novelties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained('employee_contracts');
            $table->foreignUuid('payroll_run_employee_id')->nullable()->constrained('payroll_run_employees')->nullOnDelete();
            $table->foreignUuid('created_by')->constrained('users');

            // Tipo de novedad:
            // overtime, disability, unpaid_leave, commission, bonus, loan, vacation, other
            $table->string('type', 30);

            // Para horas extra
            $table->unsignedSmallInteger('overtime_type_id')->nullable();
            $table->foreign('overtime_type_id')->references('id')->on('payroll_type_overtime_surcharges')->nullOnDelete();
            $table->decimal('overtime_hours', 6, 2)->nullable();

            // Para incapacidades
            $table->unsignedSmallInteger('disability_type_id')->nullable();
            $table->foreign('disability_type_id')->references('id')->on('payroll_type_disabilities')->nullOnDelete();
            $table->integer('disability_days')->nullable();

            // Para vacaciones
            $table->integer('vacation_days')->nullable();

            // Para permisos no remunerados
            $table->integer('unpaid_leave_days')->nullable();

            // Valor calculado o manual
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('date_from');
            $table->date('date_to')->nullable();
            $table->text('description')->nullable();

            // null = pendiente de liquidar, uuid = ya liquidada
            $table->boolean('is_processed')->default(false);

            $table->timestamps();
        });

        // ──────────────────────────────────────────────────────────
        // PRESTACIONES SOCIALES ACUMULADAS (por empleado, por año)
        // Se calcula mes a mes y se paga en los cortes legales
        // ──────────────────────────────────────────────────────────

        Schema::create('payroll_social_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained('employee_contracts');

            // prima, cesantias, intereses_cesantias, vacaciones
            $table->string('type', 30);
            $table->integer('year');
            $table->integer('semester')->nullable();               // 1 o 2 (solo prima y cesantías)

            $table->decimal('base_salary', 15, 2);
            $table->integer('days_worked');
            $table->decimal('amount', 15, 2);                     // valor calculado
            $table->decimal('paid_amount', 15, 2)->default(0);    // lo que ya se pagó
            $table->date('pay_date')->nullable();                  // fecha real de pago
            $table->boolean('is_paid')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_social_benefits');
        Schema::dropIfExists('payroll_novelties');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_contracts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('payroll_type_overtime_surcharges');
        Schema::dropIfExists('payroll_type_disabilities');
        Schema::dropIfExists('payroll_marital_statuses');
        Schema::dropIfExists('payroll_type_workers');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_type_contracts');
    }
};
