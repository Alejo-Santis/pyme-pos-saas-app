<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de auditoría por tenant (spatie/laravel-activitylog).
     * Registra todas las acciones de usuarios sobre modelos.
     */
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->create(config('activitylog.table_name'), function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->index('log_name');
                $table->text('description');

                // Sujeto (el modelo sobre el que se ejecutó la acción)
                $table->nullableUuidMorphs('subject', 'subject');

                // Evento realizado (created, updated, deleted...)
                $table->string('event')->nullable();

                // Causer (quien realizó la acción — User UUID)
                $table->nullableUuidMorphs('causer', 'causer');

                $table->json('properties')->nullable();

                // Referencia a jobs batch para agrupar operaciones masivas
                $table->uuid('batch_uuid')->nullable();

                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))
            ->dropIfExists(config('activitylog.table_name'));
    }
};
