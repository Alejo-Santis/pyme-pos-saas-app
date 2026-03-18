<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->decimal('percent', 8, 4)->default(0)->after('code');
            $table->string('tax_type', 20)->default('IVA')->after('percent'); // IVA, INC, ICL, IBU
        });

        // Actualizar con los valores estándar DIAN Colombia
        // Los registros ya deben existir desde GlobalCatalogsSeeder
        DB::table('taxes')->where('code', '01a')->update(['percent' => 0.00,  'tax_type' => 'IVA']);
        DB::table('taxes')->where('code', '01b')->update(['percent' => 5.00,  'tax_type' => 'IVA']);
        DB::table('taxes')->where('code', '01')->update(['percent'  => 19.00, 'tax_type' => 'IVA']);
        DB::table('taxes')->where('code', '04')->update(['percent'  => 8.00,  'tax_type' => 'INC']);
        DB::table('taxes')->where('code', '03')->update(['percent'  => 0.00,  'tax_type' => 'ICA']);

        // Si no existen aún, insertar los principales
        $existing = DB::table('taxes')->pluck('code')->toArray();

        $defaults = [
            ['name' => 'IVA 0%',  'description' => 'IVA tarifa 0%',  'code' => 'IVA_0',  'percent' => 0.00,  'tax_type' => 'IVA'],
            ['name' => 'IVA 5%',  'description' => 'IVA tarifa 5%',  'code' => 'IVA_5',  'percent' => 5.00,  'tax_type' => 'IVA'],
            ['name' => 'IVA 19%', 'description' => 'IVA tarifa 19%', 'code' => 'IVA_19', 'percent' => 19.00, 'tax_type' => 'IVA'],
            ['name' => 'INC 8%',  'description' => 'Impo. Nacional al Consumo 8%', 'code' => 'INC_8', 'percent' => 8.00, 'tax_type' => 'INC'],
        ];

        foreach ($defaults as $tax) {
            if (!in_array($tax['code'], $existing)) {
                DB::table('taxes')->insert(array_merge($tax, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn(['percent', 'tax_type']);
        });
    }
};
