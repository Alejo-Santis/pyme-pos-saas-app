<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Información tributaria y DIAN de la empresa del tenant.
     * FK a catálogos globales en schema public (accesibles via search_path).
     * Cada tenant tiene una empresa principal (1 a 1 en la mayoría de casos).
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identificación tributaria Colombia
            $table->string('identification_number', 20)->unique();       // NIT sin dígito de verificación
            $table->char('dv', 1);                                       // Dígito de verificación
            $table->string('business_name');                             // Razón social
            $table->string('trade_name')->nullable();                    // Nombre comercial

            // Clasificación tributaria — FK a catálogos globales (public schema)
            $table->unsignedBigInteger('type_document_identification_id'); // NIT, CC, CE... (para persona natural)
            $table->unsignedBigInteger('type_organization_id');            // Persona Natural / Jurídica
            $table->unsignedBigInteger('type_regime_id');                  // Régimen: Responsable IVA / No responsable
            $table->unsignedBigInteger('type_liability_id');               // Responsabilidad tributaria principal

            // Ubicación — FK a catálogos globales (códigos DANE para DIAN)
            $table->unsignedBigInteger('country_id');                    // FK → public.countries
            $table->unsignedBigInteger('municipality_id');               // FK → public.municipalities (incluye departamento)

            // Datos de contacto
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('address');

            // Configuración operativa de la empresa
            $table->boolean('electronic_documents')->default(false);     // FE activada en DIAN
            $table->boolean('use_price_list')->default(false);           // habilitar listas de precios
            $table->boolean('prices_with_taxes_included')->default(false); // precios con IVA incluido
            $table->boolean('use_aiu')->default(false);                  // usar AIU (servicios de construcción)
            $table->boolean('use_ibua')->default(false);                 // impuesto bebidas azucaradas
            $table->boolean('use_icui')->default(false);                 // impuesto cigarrillos

            // Configuración DIAN Factura Electrónica
            $table->tinyInteger('type_environment_id')->default(2);      // 1=producción, 2=habilitación (test)
            $table->string('dian_software_id')->nullable();              // ID software registrado en DIAN
            $table->string('dian_software_security_code')->nullable();   // Código seguridad del software
            $table->string('dian_test_set_id')->nullable();              // ID set de pruebas DIAN
            $table->string('dian_provider')->nullable();                 // Proveedor tecnológico (nextpyme, etc.)

            // Personalización
            $table->string('logo_path')->nullable();                     // ruta del logo en storage

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
