<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas faltantes a pos_terminal_users y pos_terminals.
     *
     * pos_terminal_users ya se crea completa (con pos_terminal_id, state,
     * deleted_at y su FK) desde 2026_03_13_013_create_pos_terminal_users_table —
     * el bloque que la parcheaba aquí se eliminó porque quedó redundante y su
     * intento de agregar una FK duplicada abortaba la transacción de la migración.
     */
    public function up(): void
    {
        // ── pos_terminals: verificar columnas necesarias ───────────────────────
        Schema::table('pos_terminals', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminals', 'serial_number')) {
                $table->string('serial_number', 50)->nullable()->after('name');
            }
            if (! Schema::hasColumn('pos_terminals', 'location')) {
                $table->string('location', 150)->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('pos_terminals', 'resolution_id')) {
                $table->uuid('resolution_id')->nullable();
                $table->foreign('resolution_id')->references('id')->on('resolutions')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_terminals', 'warehouse_id')) {
                $table->uuid('warehouse_id')->nullable();
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_terminals', 'establishment_id')) {
                $table->uuid('establishment_id')->nullable();
                $table->foreign('establishment_id')->references('id')->on('establishments')->nullOnDelete();
            }
            if (! Schema::hasColumn('pos_terminals', 'state')) {
                $table->boolean('state')->default(true);
            }
            if (! Schema::hasColumn('pos_terminals', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        // No-op: las columnas que este archivo pudo agregar en pos_terminals
        // son todas nullable/guardadas por hasColumn — no se revierten aquí
        // para no arriesgar borrar columnas creadas por otra migración.
    }
};
