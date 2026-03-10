<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de categorías de ítems y agrega columnas al catálogo
     * de ítems existente (tipo, categoría, control de inventario).
     *
     * Nota: la FK auto-referencial parent_id → id se omite intencionalmente.
     * PostgreSQL requiere que la columna referenciada tenga un índice único
     * nombrado explícitamente, lo que complica la migración. La integridad
     * jerárquica se gestiona a nivel aplicación (Eloquent).
     */
    public function up(): void
    {
        // ── Categorías de ítems ──────────────────────────────────────────────
        Schema::create('item_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable()->index();   // auto-ref — sin FK constraint
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Columnas adicionales en items (tabla ya existente) ───────────────
        Schema::table('items', function (Blueprint $table) {
            $table->string('type', 20)->default('product')->after('reference');

            $table->uuid('item_category_id')->nullable()->after('type');
            $table->foreign('item_category_id')
                  ->references('id')
                  ->on('item_categories')
                  ->nullOnDelete();

            $table->boolean('manages_stock')->default(true)->after('is_active');
            $table->boolean('is_service')->default(false)->after('manages_stock');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['item_category_id']);
            $table->dropColumn(['type', 'item_category_id', 'manages_stock', 'is_service']);
        });

        Schema::dropIfExists('item_categories');
    }
};
