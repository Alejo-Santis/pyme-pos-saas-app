<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de productos/servicios e inventario por tenant.
     * FK a catálogos globales (item_clasification, unit_measures, taxes) via search_path.
     */
    public function up(): void
    {
        // Grupos de ítems (agrupación por categoría de negocio)
        Schema::create('item_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('internal_code', 50)->nullable();
            $table->string('name');
            $table->timestamps();
        });

        // Líneas de ítems (sub-categorización dentro de un grupo)
        Schema::create('item_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('internal_code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Productos y servicios
        Schema::create('items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('internal_code', 100)->unique();  // código interno del producto
            $table->string('name');                           // nombre completo
            $table->string('short_name', 100)->nullable();    // nombre corto para tickets POS
            $table->string('reference', 100)->nullable();     // referencia del fabricante

            // Clasificación — FK a catálogos globales (public schema via search_path)
            $table->unsignedBigInteger('clasification_id');  // Producto/Servicio/Otro → public.item_clasification
            $table->unsignedBigInteger('tax_category_id');   // Gravado/Exento/Excluido → public.item_tax_categories
            $table->unsignedBigInteger('unit_measure_id');   // FK → public.unit_measures
            $table->uuid('group_id')->nullable();             // FK → item_groups
            $table->uuid('line_id')->nullable();              // FK → item_lines

            // Precios y costos
            $table->decimal('last_purchase_price', 15, 4)->default(0); // último precio de compra
            $table->decimal('average_cost', 15, 4)->default(0);         // costo promedio ponderado
            $table->decimal('default_sale_price', 15, 4)->default(0);   // precio de venta por defecto

            // Impuestos especiales Colombia
            $table->decimal('consumption_tax', 8, 4)->default(0);       // INC porcentaje
            $table->decimal('icui', 8, 4)->default(0);                  // impuesto cigarrillos
            $table->decimal('ibua', 8, 4)->default(0);                  // impuesto bebidas azucaradas

            // Control de doble tributación
            $table->boolean('double_taxes')->default(false);             // aplica doble impuesto

            // Códigos de barras
            $table->string('barcode_one', 100)->nullable();
            $table->string('barcode_two', 100)->nullable();

            // Control de existencias
            $table->decimal('minimum_existence', 15, 4)->default(0);
            $table->decimal('maximum_existence', 15, 4)->default(0);

            // Vinculación contable
            $table->string('minor_account_id')->nullable();  // cuenta contable (código)

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // FK internas
            $table->foreign('group_id')->references('id')->on('item_groups')->nullOnDelete();
            $table->foreign('line_id')->references('id')->on('item_lines')->nullOnDelete();
        });

        // Impuestos por ítem (IVA 19%, INC 8%, etc.)
        Schema::create('item_taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedBigInteger('tax_id');            // FK → public.taxes
            $table->tinyInteger('application');              // 1=venta, 2=compra, 3=ambos
            $table->decimal('percent', 8, 4)->default(0);   // porcentaje (si aplica)
            $table->decimal('amount', 15, 4)->default(0);   // monto fijo (si aplica)
            $table->timestamps();
        });

        // Listas de precios por ítem y bodega
        Schema::create('item_price_lists', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->uuid('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->decimal('list_one', 15, 4)->default(0);   // precio lista 1
            $table->decimal('list_two', 15, 4)->default(0);   // precio lista 2
            $table->decimal('list_three', 15, 4)->default(0); // precio lista 3
            $table->timestamps();
            $table->unique(['item_id', 'warehouse_id']);
        });

        // Stock por ítem y bodega
        Schema::create('item_warehouse', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->uuid('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->decimal('stock', 15, 4)->default(0);        // existencia actual
            $table->decimal('average_cost', 15, 4)->default(0); // costo promedio en esta bodega
            $table->timestamps();
            $table->unique(['item_id', 'warehouse_id']);
        });

        // Presentaciones del ítem (Caja x12, Bulto x20, etc.)
        Schema::create('item_presentations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->unsignedBigInteger('unit_measure_id');       // FK → public.unit_measures
            $table->uuid('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->string('name', 100);                         // nombre presentación
            $table->decimal('units_per_pack', 10, 4);            // unidades por presentación
            $table->string('barcode', 100)->nullable();
            $table->decimal('price', 15, 4)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Relación ítem — tercero (ítems que vende un proveedor específico)
        Schema::create('item_by_third_parties', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('item_id');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->uuid('third_party_id');
            $table->foreign('third_party_id')->references('id')->on('third_parties')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['item_id', 'third_party_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_by_third_parties');
        Schema::dropIfExists('item_presentations');
        Schema::dropIfExists('item_warehouse');
        Schema::dropIfExists('item_price_lists');
        Schema::dropIfExists('item_taxes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('item_lines');
        Schema::dropIfExists('item_groups');
    }
};
