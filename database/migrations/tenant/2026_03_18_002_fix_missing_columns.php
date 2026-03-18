<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas faltantes detectadas en pruebas:
 * - items: note, type, manages_stock, is_service
 * - warehouses: is_active
 * - purchase_orders: softDeletes
 * - cash_movements: softDeletes
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── items: columnas faltantes ──────────────────────────────────────
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'note')) {
                $table->text('note')->nullable()->after('reference');
            }
            if (! Schema::hasColumn('items', 'type')) {
                $table->string('type', 20)->default('product')->after('note');
            }
            if (! Schema::hasColumn('items', 'manages_stock')) {
                $table->boolean('manages_stock')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('items', 'is_service')) {
                $table->boolean('is_service')->default(false)->after('manages_stock');
            }
        });

        // ── warehouses: columna is_active faltante ─────────────────────────
        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_main');
            }
        });

        // ── purchase_orders: softDeletes faltante ──────────────────────────
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // ── cash_movements: softDeletes faltante ───────────────────────────
        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(array_filter(['note', 'type', 'manages_stock', 'is_service'], fn ($col) => Schema::hasColumn('items', $col)));
        });

        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
