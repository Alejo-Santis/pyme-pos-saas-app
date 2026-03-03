<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos geográficos de Colombia (DANE) y tipo de documento.
     * Viven en el schema public — compartidos por todos los tenants.
     * Se seedean una sola vez con datos oficiales DIAN/DANE.
     */
    public function up(): void
    {
        // Países (estándar ISO 3166 — Colombia = código 169 en DIAN)
        Schema::create('countries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique();  // código DIAN (ej: "CO" o "169")
            $table->timestamps();
        });

        // Departamentos de Colombia (DANE)
        Schema::create('departments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('country_id');
            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 10)->unique();  // código DANE (ej: "05" para Antioquia)
            $table->timestamps();
        });

        // Municipios de Colombia (DANE — con código para DIAN)
        Schema::create('municipalities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('department_id');
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 10)->unique();           // código DANE municipio (ej: "05001" para Medellín)
            $table->string('codefacturador', 10)->nullable(); // código específico facturador electrónico
            $table->timestamps();
        });

        // Tipos de organización (Persona Natural / Persona Jurídica)
        Schema::create('type_organizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique(); // 1=Persona Jurídica, 2=Persona Natural
            $table->timestamps();
        });

        // Tipos de identificación de documento (NIT, CC, CE, TI, Pasaporte...)
        Schema::create('type_document_identifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique();           // código DIAN (ej: "31" para NIT)
            $table->string('code_agency', 10)->nullable();  // código agencia (ej: "195" para DIAN)
            $table->timestamps();
        });

        // Regímenes tributarios (Responsable IVA / No Responsable IVA)
        Schema::create('type_regimes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('code', 10)->unique(); // O=Responsable IVA (común), P=No responsable IVA (simplificado)
            $table->timestamps();
        });

        // Responsabilidades tributarias (Gran contribuyente, Agente de retención, etc.)
        Schema::create('type_liabilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('code', 10)->unique(); // código DIAN (ej: "O-13" para gran contribuyente)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('type_liabilities');
        Schema::dropIfExists('type_regimes');
        Schema::dropIfExists('type_document_identifications');
        Schema::dropIfExists('type_organizations');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('countries');
    }
};
