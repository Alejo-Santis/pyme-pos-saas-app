<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de acciones del sistema por tenant.
     *
     * Registra: quién hizo qué, sobre qué modelo, cuándo y desde dónde.
     * Los campos old_values / new_values guardan el diff como JSON.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Quién
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('user_name', 150)->nullable();     // denormalizado para historial

            // Qué acción
            $table->string('event', 50);                     // created, updated, deleted, login, logout, export...
            $table->string('action', 150);                   // descripción legible: "Creó factura FE-001"

            // Sobre qué modelo / registro
            $table->string('auditable_type', 150)->nullable(); // App\Modules\Invoice\Models\Document
            $table->string('auditable_id', 36)->nullable();    // UUID del registro

            // Datos antes / después (diff)
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            // Contexto de la petición
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('method', 10)->nullable();         // GET, POST, PUT, DELETE

            // Módulo / área del sistema
            $table->string('module', 50)->nullable();        // Invoice, POS, Inventory, Auth...

            $table->timestamp('created_at')->useCurrent();

            // Índices para consultas frecuentes
            $table->index(['user_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event', 'created_at']);
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
