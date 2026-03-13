<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega technical_key a resolutions (clave técnica DIAN para FEV en habilitación).
 * También corrige current_number: debe arrancar en (from - 1) para que el primer
 * número emitido sea exactamente "from".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            if (! Schema::hasColumn('resolutions', 'technical_key')) {
                $table->string('technical_key')->nullable()->after('resolution_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resolutions', function (Blueprint $table) {
            if (Schema::hasColumn('resolutions', 'technical_key')) {
                $table->dropColumn('technical_key');
            }
        });
    }
};
