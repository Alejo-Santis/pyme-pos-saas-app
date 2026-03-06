<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Planes SaaS base — se crean en el schema public (tabla plans).
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('plans')->insertOrIgnore([
            [
                'id'                    => (string) Str::uuid(),
                'name'                  => 'Básico',
                'slug'                  => 'basico',
                'description'           => 'Facturación electrónica + POS básico. Ideal para pequeños negocios.',
                'price_monthly'         => 59900.00,
                'price_yearly'          => 599000.00,
                'max_users'             => 2,
                'max_products'          => 200,
                'max_invoices_monthly'  => 200,
                'trial_days'            => 5,
                'is_active'             => true,
                'sort_order'            => 1,
                'features'              => json_encode([
                    'electronic_invoicing' => true,
                    'pos'                  => true,
                    'inventory'            => false,
                    'accounting'           => false,
                    'multi_warehouse'      => false,
                ]),
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'id'                    => (string) Str::uuid(),
                'name'                  => 'Profesional',
                'slug'                  => 'profesional',
                'description'           => 'Facturación + POS + Inventario multi-bodega + Compras.',
                'price_monthly'         => 129900.00,
                'price_yearly'          => 1299000.00,
                'max_users'             => 5,
                'max_products'          => null,
                'max_invoices_monthly'  => 1000,
                'trial_days'            => 5,
                'is_active'             => true,
                'sort_order'            => 2,
                'features'              => json_encode([
                    'electronic_invoicing' => true,
                    'pos'                  => true,
                    'inventory'            => true,
                    'accounting'           => false,
                    'multi_warehouse'      => true,
                ]),
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                'id'                    => (string) Str::uuid(),
                'name'                  => 'Empresarial',
                'slug'                  => 'empresarial',
                'description'           => 'Suite completa: FE + POS + Inventario + Contabilidad + Nómina.',
                'price_monthly'         => 249900.00,
                'price_yearly'          => 2499000.00,
                'max_users'             => null,
                'max_products'          => null,
                'max_invoices_monthly'  => null,
                'trial_days'            => 5,
                'is_active'             => true,
                'sort_order'            => 3,
                'features'              => json_encode([
                    'electronic_invoicing' => true,
                    'pos'                  => true,
                    'inventory'            => true,
                    'accounting'           => true,
                    'multi_warehouse'      => true,
                ]),
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
        ]);
    }
}
