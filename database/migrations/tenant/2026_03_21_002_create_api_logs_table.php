<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de llamadas a la API del proveedor DIAN (Nextpyme u otros).
     *
     * Permite diagnosticar problemas de transmisión electrónica,
     * ver reintentos, tiempos de respuesta y errores DIAN.
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Documento relacionado (puede ser null si falla antes de crearse)
            $table->uuid('document_id')->nullable();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();

            // Proveedor y operación
            $table->string('provider', 50)->default('nextpyme'); // nextpyme, dian_directo...
            $table->string('operation', 100);                    // send_invoice, send_credit_note, get_status...

            // Petición enviada
            $table->string('endpoint', 300)->nullable();
            $table->string('method', 10)->default('POST');
            $table->jsonb('request_payload')->nullable();

            // Respuesta recibida
            $table->smallInteger('http_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->boolean('success')->default(false);

            // Errores y reintentos
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1); // número de intento (1, 2, 3...)

            // Tiempo de respuesta en ms
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'created_at']);
            $table->index(['provider', 'operation', 'success']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
