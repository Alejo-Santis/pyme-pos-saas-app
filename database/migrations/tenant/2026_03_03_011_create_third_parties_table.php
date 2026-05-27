<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terceros: clientes, proveedores y otros (sin company_id — aislados por schema).
     * FK a catálogos globales en public schema via search_path.
     */
    public function up(): void
    {
        Schema::create('third_parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();

            // Identificación tributaria
            $table->unsignedBigInteger('type_document_identification_id')->nullable(); // FK → public
            $table->string('identification_number', 20);
            $table->char('dv', 1)->nullable();               // dígito de verificación (solo NIT)

            // Clasificación tributaria — FK a catálogos públicos
            $table->unsignedBigInteger('type_organization_id')->nullable();  // Persona Natural/Jurídica
            $table->unsignedBigInteger('type_regime_id')->nullable();        // Régimen tributario
            $table->unsignedBigInteger('type_liability_id')->nullable();     // Responsabilidad tributaria
            $table->unsignedBigInteger('type_third_id')->nullable();         // Cliente/Proveedor/Otro → public.type_thirds

            // Datos personales/empresa
            $table->string('name');                           // razón social o primer nombre
            $table->string('surname')->nullable();            // apellido (persona natural)
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();

            // Ubicación — FK a catálogos globales (códigos DANE)
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('municipality_id')->nullable();

            // Atributos fiscales Colombia
            $table->boolean('excluded_from_taxes')->default(false);  // excluido de impuestos
            $table->boolean('great_contributor')->default(false);     // gran contribuyente
            $table->boolean('self_retaining')->default(false);        // auto-retenedor
            $table->boolean('seller')->default(false);                // vendedor propio de la empresa

            // Condiciones comerciales
            $table->decimal('credit_limit', 15, 2)->default(0);      // cupo de crédito
            $table->unsignedSmallInteger('payment_days')->default(0); // días de crédito

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('identification_number');
        });

        // Clasificación de roles del tercero (cliente Y/O proveedor a la vez)
        Schema::create('party_linkages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('third_party_id');
            $table->foreign('third_party_id')->references('id')->on('third_parties')->cascadeOnDelete();
            $table->boolean('customer')->default(false);
            $table->boolean('provider')->default(false);
            $table->boolean('other')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_linkages');
        Schema::dropIfExists('third_parties');
    }
};
