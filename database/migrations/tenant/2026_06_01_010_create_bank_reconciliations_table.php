<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cabecera de conciliación bancaria
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->string('period', 7);              // ej: "2026-05"
            $table->date('statement_date');            // fecha del extracto bancario
            $table->decimal('statement_balance', 15, 4); // saldo según extracto
            $table->decimal('book_balance', 15, 4);      // saldo según libros
            $table->decimal('difference', 15, 4)->default(0); // statement - book
            $table->string('status', 20)->default('open');    // open | reconciled
            $table->uuid('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bank_account_id', 'period']);
        });

        // Líneas de conciliación (cruces de movimientos)
        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignUuid('bank_account_movement_id')->nullable()->constrained('bank_account_movements')->nullOnDelete();
            $table->date('movement_date');
            $table->string('description', 200);
            $table->decimal('amount', 15, 4);          // positivo = ingreso, negativo = egreso
            $table->string('source', 20);              // book | statement
            $table->boolean('matched')->default(false); // cruzado/conciliado
            $table->timestamps();

            $table->index(['reconciliation_id', 'matched']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
        Schema::dropIfExists('bank_reconciliations');
    }
};
