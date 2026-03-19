<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de arqueo/cuadre de caja al cierre de turno.
 *
 * counted_cash  → efectivo físico contado por el cajero al cerrar
 * difference    → diferencia entre lo contado y lo esperado (+ sobrante, - faltante)
 * close_notes   → observaciones del cajero al cerrar el turno
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminal_users', 'counted_cash')) {
                $table->decimal('counted_cash', 15, 2)->nullable()->after('final_balance');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'difference')) {
                $table->decimal('difference', 15, 2)->nullable()->after('counted_cash');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'close_notes')) {
                $table->string('close_notes', 500)->nullable()->after('difference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            $table->dropColumn(['counted_cash', 'difference', 'close_notes']);
        });
    }
};
