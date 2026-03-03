<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planes de suscripción del SaaS (landlord - schema public).
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');                           // nombre visible: "Básico", "Pro", "Enterprise"
            $table->string('slug')->unique();                 // identificador: "basic", "pro", "enterprise"
            $table->text('description')->nullable();

            // Precio y facturación
            $table->decimal('price_monthly', 10, 2)->default(0);   // precio mensual en COP
            $table->decimal('price_yearly', 10, 2)->default(0);    // precio anual en COP (descuento)

            // Límites del plan
            $table->unsignedInteger('max_users')->nullable();       // null = ilimitado
            $table->unsignedInteger('max_products')->nullable();    // null = ilimitado
            $table->unsignedInteger('max_invoices_monthly')->nullable(); // facturas/mes

            // Features habilitadas (flags booleanos serializados)
            $table->json('features')->nullable();                   // ej: {"dian_fe": true, "pos": true, "accounting": false}

            // Días de trial al crear un tenant con este plan
            $table->unsignedSmallInteger('trial_days')->default(14);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0); // orden en la pantalla de precios

            $table->timestamps();
        });

        // FK retroactiva desde tenants hacia plans
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::dropIfExists('plans');
    }
};
