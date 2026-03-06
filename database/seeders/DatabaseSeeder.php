<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de la base de datos (schema public — landlord).
     *
     * Orden:
     * 1. Catálogos globales hardcoded (DIAN, tipos, clasificaciones)
     * 2. Catálogos masivos desde CSV (países, departamentos, municipios,
     *    unidades de medida, medios de pago, monedas, impuestos)
     * 3. Planes SaaS base
     */
    public function run(): void
    {
        $this->call([
            GlobalCatalogsSeeder::class,
            CsvCatalogsSeeder::class,
            PlansSeeder::class,
        ]);
    }
}
