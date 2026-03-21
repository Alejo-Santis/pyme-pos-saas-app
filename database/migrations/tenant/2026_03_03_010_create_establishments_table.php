<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Establecimientos (sucursales/locales) de la empresa.
     * Una empresa puede tener múltiples establecimientos.
     * Cada establecimiento puede tener varias bodegas.
     */
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();

            $table->string('name');                          // nombre del establecimiento
            $table->string('business_name')->nullable();     // razón social si difiere
            $table->string('address')->nullable();
            $table->unsignedBigInteger('municipality_id')->nullable(); // FK → public.municipalities

            $table->boolean('is_main')->default(false);      // establecimiento principal
            $table->boolean('sync_items_full')->default(true); // sincronizar ítems completos

            $table->timestamps();
        });

        // Bodegas del establecimiento
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uuid')->unique();

            $table->uuid('establishment_id');
            $table->foreign('establishment_id')->references('id')->on('establishments')->cascadeOnDelete();

            $table->string('name');
            $table->string('internal_code', 50)->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_main')->default(false);      // bodega principal del establecimiento

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('establishments');
    }
};
