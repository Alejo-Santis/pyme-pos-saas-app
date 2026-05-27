<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hace nullable las FK a catálogos globales en items.
 * Necesario para servicios que no tienen unidad de medida o clasificación obligatoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // PostgreSQL requiere ALTER COLUMN ... DROP NOT NULL directamente
        DB::statement('ALTER TABLE items ALTER COLUMN clasification_id DROP NOT NULL');
        DB::statement('ALTER TABLE items ALTER COLUMN tax_category_id DROP NOT NULL');
        DB::statement('ALTER TABLE items ALTER COLUMN unit_measure_id DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE items ALTER COLUMN clasification_id SET NOT NULL');
        DB::statement('ALTER TABLE items ALTER COLUMN tax_category_id SET NOT NULL');
        DB::statement('ALTER TABLE items ALTER COLUMN unit_measure_id SET NOT NULL');
    }
};
