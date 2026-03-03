<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de navegación y acceso por tenant.
     * Los menús y sub-menús base vienen del schema public (catálogo global).
     * Esta tabla registra qué sub-menús tiene habilitados cada usuario del tenant.
     */
    public function up(): void
    {
        // Menús habilitados por usuario (customización de acceso por empleado)
        Schema::create('user_sub_menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('sub_menu_id'); // FK → public.sub_menus
            $table->timestamps();
            $table->unique(['user_id', 'sub_menu_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_sub_menus');
    }
};
