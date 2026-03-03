<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de suscripciones por tenant (landlord - schema public).
     * Un tenant puede tener múltiples suscripciones históricas pero solo una activa.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Tenant al que pertenece la suscripción
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Plan contratado
            $table->uuid('plan_id');
            $table->foreign('plan_id')->references('id')->on('plans')->restrictOnDelete();

            // Ciclo de facturación
            $table->enum('billing_period', ['monthly', 'yearly'])->default('monthly');
            $table->decimal('price', 10, 2);                     // precio al momento de la suscripción

            // Estado de la suscripción
            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])
                  ->default('trial');

            // Fechas
            $table->timestamp('trial_ends_at')->nullable();       // fin del trial (si aplica)
            $table->timestamp('starts_at');                       // inicio del período activo
            $table->timestamp('ends_at')->nullable();             // fin del período (null = indefinido)
            $table->timestamp('cancelled_at')->nullable();        // fecha de cancelación
            $table->timestamp('grace_ends_at')->nullable();       // fin del periodo de gracia

            // Referencia externa (pasarela de pago)
            $table->string('gateway')->nullable();                // "epayco", "wompi", "stripe"
            $table->string('gateway_subscription_id')->nullable(); // ID en la pasarela
            $table->json('gateway_data')->nullable();             // metadata de la pasarela

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
