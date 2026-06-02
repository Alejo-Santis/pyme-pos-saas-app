<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de lotes y números de serie de inventario.
 *
 * Un producto puede tener tracking_type:
 *   none   → sin trazabilidad (default)
 *   lot    → por lote (ej: alimentos, farmacéutica)
 *   serial → por número de serie (ej: electrónicos, equipos)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tipo de trazabilidad en el item
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'tracking_type')) {
                $table->string('tracking_type', 10)->default('none')->after('state'); // none|lot|serial
            }
        });

        // Lotes registrados por item y bodega
        Schema::create('item_lots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('lot_number', 80);           // número de lote o serie
            $table->string('tracking_type', 10);         // lot | serial
            $table->decimal('quantity', 15, 4)->default(0); // solo para lotes (serial siempre 1)
            $table->date('expiry_date')->nullable();     // fecha de vencimiento (lotes)
            $table->date('manufacture_date')->nullable();
            $table->string('status', 20)->default('active'); // active | consumed | expired
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'status']);
            $table->index(['expiry_date', 'status']);
            $table->unique(['item_id', 'lot_number', 'warehouse_id']);
        });

        // Movimientos de lotes (trazabilidad de entrada/salida por documento)
        Schema::create('item_lot_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('item_lot_id')->constrained('item_lots')->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('movement_type', 5);         // IN | OUT
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->index(['item_lot_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_lot_movements');
        Schema::dropIfExists('item_lots');
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumnIfExists('tracking_type');
        });
    }
};
