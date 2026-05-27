<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_legal_parameters')) {
            return;
        }

        Schema::create('payroll_legal_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('smmlv', 15, 2);
            $table->decimal('transport_allowance', 15, 2)->default(0);
            $table->decimal('uvt', 15, 2)->default(0);
            $table->decimal('health_employee_rate', 7, 6)->default(0.04);
            $table->decimal('pension_employee_rate', 7, 6)->default(0.04);
            $table->decimal('solidarity_base_rate', 7, 6)->default(0.01);
            $table->decimal('health_employer_rate', 7, 6)->default(0.085);
            $table->decimal('pension_employer_rate', 7, 6)->default(0.12);
            $table->decimal('ccf_rate', 7, 6)->default(0.04);
            $table->decimal('sena_rate', 7, 6)->default(0.02);
            $table->decimal('icbf_rate', 7, 6)->default(0.03);
            $table->decimal('cesantias_interest_rate', 7, 6)->default(0.12);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_legal_parameters');
    }
};
