<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de usuarios por tenant (schema aislado).
     * PKs UUID — nunca bigInteger para evitar colisiones entre tenants.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identificación personal (necesaria para documentos DIAN — cajero/vendedor)
            $table->unsignedBigInteger('type_document_identification_id')->nullable(); // FK → public.type_document_identifications (CC, CE, NIT...)
            $table->string('identification_number', 20)->nullable();                   // número de cédula/pasaporte

            // Datos de acceso
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Datos de contacto y perfil
            $table->string('phone', 20)->nullable();     // teléfono
            $table->string('avatar')->nullable();         // ruta del avatar en storage

            $table->boolean('is_active')->default(true); // permite deshabilitar sin borrar

            $table->timestamps();
        });

        // Tokens de restablecimiento de contraseña
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Sesiones web (referencia UUID al usuario del tenant)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index(); // UUID en lugar de bigInteger
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
