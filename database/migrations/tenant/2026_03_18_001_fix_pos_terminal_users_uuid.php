<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recrea pos_terminal_users con UUID como PK y todas las columnas que necesita el modelo.
 * La migración original (_015) usó bigIncrements — esto lo corrige.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Eliminar y recrear con esquema correcto (sin datos en dev)
        Schema::dropIfExists('pos_terminal_users');

        Schema::create('pos_terminal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pos_terminal_id')->nullable();
            $table->foreign('pos_terminal_id')->references('id')->on('pos_terminals')->nullOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('initial_balance',  15, 2)->default(0);
            $table->decimal('current_balance',  15, 2)->default(0);
            $table->decimal('final_balance',    15, 2)->default(0);
            $table->decimal('total_sales',      15, 2)->default(0);
            $table->decimal('total_cash',       15, 2)->default(0);
            $table->decimal('total_card',       15, 2)->default(0);
            $table->decimal('total_transfer',   15, 2)->default(0);
            $table->boolean('active_shift')->default(false);
            $table->string('cashier_session_key', 100)->nullable();
            $table->timestamp('shift_opened_at')->nullable();
            $table->timestamp('shift_closed_at')->nullable();
            $table->boolean('state')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminal_users');
    }
};
