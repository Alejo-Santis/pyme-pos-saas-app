<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos DIAN para facturación electrónica.
     * Viven en schema public — compartidos por todos los tenants.
     * Datos fijos según resoluciones DIAN vigentes.
     */
    public function up(): void
    {
        // Formas de pago (Contado / Crédito)
        Schema::create('payment_forms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique(); // 1=Contado, 2=Crédito
            $table->timestamps();
        });

        // Medios de pago DIAN (Efectivo, Tarjeta crédito, PSE, etc.)
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique(); // código DIAN (ej: "10"=Efectivo, "48"=Tarjeta crédito)
            $table->timestamps();
        });

        // Tipos de moneda (COP, USD, EUR...)
        Schema::create('type_currencies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50);
            $table->string('code', 10)->unique(); // ISO 4217 (ej: "COP", "USD")
            $table->timestamps();
        });

        // Unidades de medida DIAN (Unidad, Kilogramo, Metro, etc.)
        Schema::create('unit_measures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique(); // código UNECE (ej: "94"=Unidad, "KGM"=Kilogramo)
            $table->timestamps();
        });

        // Impuestos DIAN (IVA, INC, ICA, Retención...)
        Schema::create('taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('code', 10)->unique(); // código DIAN (ej: "01"=IVA, "04"=INC)
            $table->timestamps();
        });

        // Tipos de operación DIAN (Factura venta, Exportación, Estándar, etc.)
        Schema::create('type_operations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique(); // código DIAN (ej: "10"=Estándar, "09"=AIU)
            $table->timestamps();
        });

        // Tipos de ítems de identificación (UNSPSC, GTIN, Partida arancelaria, etc.)
        Schema::create('type_item_identifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique();
            $table->string('code_agency', 10)->nullable();
            $table->timestamps();
        });

        // Precios de referencia (Lista 1, Lista 2, Lista 3)
        Schema::create('reference_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique();
            $table->timestamps();
        });

        // Tasas de retención en la fuente (tabla oficial DIAN con UVT)
        Schema::create('rate_holding_taxes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('description', 255);
            $table->decimal('fee', 8, 4);           // porcentaje de retención
            $table->decimal('minimum_base_uvt', 10, 4)->nullable(); // base mínima en UVT
            $table->timestamps();
        });

        // Descuentos permitidos DIAN
        Schema::create('discounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('rate_holding_taxes');
        Schema::dropIfExists('reference_prices');
        Schema::dropIfExists('type_item_identifications');
        Schema::dropIfExists('type_operations');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('unit_measures');
        Schema::dropIfExists('type_currencies');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_forms');
    }
};
