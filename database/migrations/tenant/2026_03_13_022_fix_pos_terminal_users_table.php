<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas faltantes a pos_terminal_users y pos_terminals.
     * La tabla fue creada inicialmente por 2026_03_03_015 sin todas las columnas.
     */
    public function up(): void
    {
        // ── pos_terminal_users ────────────────────────────────────────────────
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_terminal_users', 'pos_terminal_id')) {
                $table->uuid('pos_terminal_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'state')) {
                $table->boolean('state')->default(true)->after('cashier_session_key');
            }
            if (! Schema::hasColumn('pos_terminal_users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Agregar FK si no existe
        try {
            Schema::table('pos_terminal_users', function (Blueprint $table) {
                $table->foreign('pos_terminal_id')
                      ->references('id')->on('pos_terminals')->nullOnDelete();
            });
        } catch (\Throwable $e) {
            // FK ya existe, ignorar
        }

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
        Schema::table('pos_terminal_users', function (Blueprint $table) {
            $table->dropColumn(['pos_terminal_id', 'state', 'deleted_at']);
        });
    }
};
