<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas debit/credit/user_id a cash_movements.
 * La migración original (_010) solo tenía la columna "amount".
 * Los controladores y modelos esperan debit/credit para el balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'debit')) {
                $table->decimal('debit', 15, 2)->default(0)->after('cash_box_id');
            }
            if (! Schema::hasColumn('cash_movements', 'credit')) {
                $table->decimal('credit', 15, 2)->default(0)->after('debit');
            }
            if (! Schema::hasColumn('cash_movements', 'user_id')) {
                $table->uuid('user_id')->nullable()->after('credit');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Migrar columna "amount" existente a debit (todos los movimientos previos
        // eran entradas — no hay créditos históricos)
        \DB::statement('UPDATE cash_movements SET debit = amount WHERE debit = 0 AND amount > 0');
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (Schema::hasColumn('cash_movements', 'credit')) {
                $table->dropColumn('credit');
            }
            if (Schema::hasColumn('cash_movements', 'debit')) {
                $table->dropColumn('debit');
            }
        });
    }
};
