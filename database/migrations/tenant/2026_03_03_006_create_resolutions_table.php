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

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            // Tipo de documento que ampara la resolución — FK a catálogos globales
            $table->unsignedBigInteger('type_document_id');            // FK → public.type_documents (FEV, Doc. Equivalente...)
            $table->unsignedBigInteger('type_document_operation_id'); // FK → public.type_document_operations (Factura VE, NC, ND...)

            // Datos de la resolución DIAN
            $table->string('resolution');                               // número de resolución (ej: "18760000001")
            $table->date('resolution_date');                            // fecha de la resolución
            $table->string('prefix', 10)->nullable();                   // prefijo (ej: "FE", "POS", "NC") — nullable para POS

            // Rango autorizado de numeración
            $table->unsignedInteger('from');                            // inicio del rango
            $table->unsignedInteger('to');                              // fin del rango
            $table->unsignedInteger('current_number');                  // consecutivo actual (sincronizado con sends)

            // Vigencia de la resolución
            $table->date('date_from');                                  // inicio vigencia
            $table->date('date_to');                                    // fin vigencia

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Índices para búsquedas frecuentes en facturación
            $table->index(['company_id', 'type_document_id', 'is_active']);
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
