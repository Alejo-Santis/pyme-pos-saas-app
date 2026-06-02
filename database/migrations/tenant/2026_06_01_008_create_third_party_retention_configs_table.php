<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasas de retención configuradas por tercero (cliente).
 *
 * Cuando un tercero es Gran Contribuyente o Agente Retenedor, aplica
 * retenciones sobre las facturas que recibe. Estas tasas se autocompletan
 * en el formulario de factura al seleccionar el cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('third_party_retention_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('third_party_id')->constrained('third_parties')->cascadeOnDelete();
            $table->string('retention_type', 20); // retefuente | reteiva | reteica
            $table->string('label', 80);          // ej: "ReteFuente 2.5% - Compras generales"
            $table->decimal('percent', 8, 4);     // tasa porcentual (ej: 2.5)
            $table->string('base', 20)->default('subtotal'); // subtotal | tax | total
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['third_party_id', 'is_active']);
            $table->unique(['third_party_id', 'retention_type', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('third_party_retention_configs');
    }
};
