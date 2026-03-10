<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columna deleted_at a las tablas que usan SoftDeletes pero no la tenían.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establishments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('third_parties', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('establishments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
