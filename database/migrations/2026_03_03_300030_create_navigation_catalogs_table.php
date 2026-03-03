<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menús y sub-menús de navegación de la aplicación.
     * Viven en schema public — son globales para todos los tenants.
     * Los permisos (permission_name) se cruzan con spatie/permission por tenant.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('uri', 255)->nullable();
            $table->string('icon', 100)->nullable();         // ej: "mdi:home", "mdi:file-document"
            $table->enum('type', ['dropdown', 'single'])->default('dropdown');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('sub_menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('menu_id');
            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 100);
            $table->string('uri', 255)->nullable();
            $table->string('icon', 100)->nullable();
            $table->json('childs')->nullable();               // sub-ítems anidados (si aplica)
            $table->enum('type', ['single', 'child'])->default('single');
            $table->string('permission_name', 150)->nullable(); // permiso spatie requerido
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_menus');
        Schema::dropIfExists('menus');
    }
};
