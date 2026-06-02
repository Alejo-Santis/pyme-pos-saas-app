<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega columnas de retenciones calculadas a los documentos.
 *
 * withholdings_tax ya existe como JSON (retenciones recibidas del frontend).
 * total_withholding: suma total de retenciones para cálculo de saldo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'total_withholding')) {
                $table->decimal('total_withholding', 15, 4)->default(0)->after('total_tax');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumnIfExists('total_withholding');
        });
    }
};
