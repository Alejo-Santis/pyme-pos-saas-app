<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resoluciones de numeración DIAN por empresa.
     * Nomenclatura alineada con DIAN y XEDOCS:
     * - resolution: número de resolución
     * - from/to: rango autorizado
     * - date_from/date_to: vigencia
     * FK a catálogos globales (public schema) via search_path.
     */
    public function up(): void
    {
        Schema::create('resolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();                              // UUID para API DIAN

            // Tipo de documento que ampara la resolución — FK a catálogos globales
            $table->unsignedBigInteger('type_document_id')->nullable();            // FK → public.type_documents (null para POS, ajustes, etc.)
            $table->unsignedBigInteger('type_document_operation_id');              // FK → public.type_document_operations (Factura VE, NC, ND...)

            // Datos de la resolución DIAN (nullable: NC, ND, POS y otros no tienen resolución DIAN)
            $table->string('resolution')->nullable();                               // número de resolución (ej: "18760000001")
            $table->date('resolution_date')->nullable();                            // fecha de la resolución
            $table->string('technical_key')->nullable();                            // clave técnica DIAN para FEV en habilitación
            $table->string('prefix', 10)->nullable();                               // prefijo (ej: "FE", "POS", "NC")

            // Rango autorizado de numeración
            $table->unsignedInteger('from')->default(1);                // inicio del rango
            $table->unsignedInteger('to')->default(999999);             // fin del rango
            $table->unsignedInteger('current_number')->default(0);      // consecutivo actual (sincronizado con sends)

            // Vigencia de la resolución (nullable: solo aplica a FEV con resolución DIAN)
            $table->date('date_from')->nullable();                      // inicio vigencia
            $table->date('date_to')->nullable();                        // fin vigencia

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices para búsquedas frecuentes en facturación
            $table->index(['type_document_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resolutions');
    }
};
