<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de integración FE (Nextpyme) a la tabla companies.
 * Estas columnas ya están en el modelo Company->$fillable pero faltaban en la migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'api_path_fe')) {
                $table->string('api_path_fe')->nullable()->after('dian_provider');
            }
            if (! Schema::hasColumn('companies', 'api_token_fe')) {
                $table->string('api_token_fe', 500)->nullable()->after('api_path_fe');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(array_filter(['api_path_fe', 'api_token_fe'], fn ($c) => Schema::hasColumn('companies', $c)));
        });
    }
};
