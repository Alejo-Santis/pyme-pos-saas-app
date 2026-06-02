<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encabezado del presupuesto
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->unsignedSmallInteger('year');
            $table->string('status', 20)->default('draft'); // draft | approved | closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'name']);
        });

        // Líneas presupuestales por cuenta PUC y mes
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('account_code', 20);       // código PUC
            $table->string('account_name', 200);
            $table->unsignedTinyInteger('month');     // 1–12
            $table->decimal('amount', 15, 4);         // valor presupuestado
            $table->timestamps();

            $table->index(['budget_id', 'month']);
            $table->unique(['budget_id', 'account_code', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
