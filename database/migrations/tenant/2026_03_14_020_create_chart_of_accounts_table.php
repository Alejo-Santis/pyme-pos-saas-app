<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Único de Cuentas (PUC) Colombia.
 * Tabla plana jerárquica: el nivel se determina por la longitud del código.
 *   código 1 dígito  → Clase    (1-9)
 *   código 2 dígitos → Grupo    (10-99)
 *   código 4 dígitos → Cuenta   (1000-9999)
 *   código 6 dígitos → Subcuenta
 *   código 8+ dígitos → Auxiliar (solo éstas admiten movimientos)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chart_of_accounts')) return;

        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();       // código PUC (ej: "13050501")
            $table->string('name', 255);                // nombre de la cuenta
            $table->tinyInteger('class')->index();      // 1=Activo ... 9=Cuentas de orden acreedoras
            $table->tinyInteger('level');               // 1=clase, 2=grupo, 3=cuenta, 4=subcuenta, 5=auxiliar
            $table->string('parent_code', 20)->nullable()->index(); // código del padre
            $table->char('nature', 1)->default('D');   // D=Débito, C=Crédito
            $table->boolean('allows_movement')->default(false); // solo auxiliares
            $table->boolean('state')->default(true);
            $table->timestamps();

            $table->index(['class', 'level']);
            $table->index('allows_movement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
