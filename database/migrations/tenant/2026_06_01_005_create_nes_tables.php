<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nómina Electrónica DIAN (NES).
 *
 * Agrega:
 *  - Columnas de estado NES a payroll_runs
 *  - Tabla payroll_electronic_sendings para tracking de envíos por empleado
 */
return new class extends Migration
{
    public function up(): void
    {
        // Columnas NES en la liquidación
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->boolean('is_electronic')->default(false)->after('status');
            $table->string('nes_status', 20)->nullable()->after('is_electronic'); // pending|processing|sent|failed|partial
            $table->timestamp('nes_sent_at')->nullable()->after('nes_status');
        });

        // Registro de envío NES por empleado
        Schema::create('payroll_electronic_sendings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_employee_id')->constrained('payroll_run_employees')->cascadeOnDelete();
            $table->string('cune', 120)->nullable();            // código único NES (respuesta DIAN)
            $table->string('status', 20)->default('pending');   // pending|sent|failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('error_message')->nullable();
            $table->json('response_api')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'payroll_run_employee_id']);
            $table->index(['payroll_run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_electronic_sendings');
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['is_electronic', 'nes_status', 'nes_sent_at']);
        });
    }
};
