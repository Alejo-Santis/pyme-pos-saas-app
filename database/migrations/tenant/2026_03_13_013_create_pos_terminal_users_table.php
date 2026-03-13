<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de usuarios asignados a terminales POS y control de turnos de caja.
 * Un usuario puede tener un solo turno activo (active_shift = true) a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_terminal_users')) {
            Schema::create('pos_terminal_users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('pos_terminal_id');
                $table->foreign('pos_terminal_id')->references('id')->on('pos_terminals')->cascadeOnDelete();
                $table->uuid('user_id');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->decimal('initial_balance', 15, 2)->default(0);   // base de caja al abrir
                $table->decimal('current_balance', 15, 2)->default(0);   // saldo calculado
                $table->boolean('active_shift')->default(false);           // turno en curso
                $table->string('cashier_session_key')->nullable();         // llave de sesión única
                $table->boolean('state')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_terminal_users');
    }
};
