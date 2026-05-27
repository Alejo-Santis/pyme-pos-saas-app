<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingConceptSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $concepts = [
            ['1_CXC', 'Venta - cuenta por cobrar clientes', '13050501', false],
            ['1_INGRESO', 'Venta - ingreso operacional', '41351001', false],
            ['1_IVA_GEN', 'Venta - IVA generado', '24080101', true],
            ['1_COSTO', 'Venta - costo de venta', '61351001', false],
            ['1_INV_SALIDA', 'Venta - salida de inventario', '14350101', false],

            ['14_INVENTARIO', 'Compra - inventario', '14350101', false],
            ['14_IVA_DESC', 'Compra - IVA descontable', '24080501', true],
            ['14_CXP', 'Compra - cuenta por pagar proveedor', '22050101', false],

            ['91_CXC', 'Nota credito - reversa cuenta por cobrar', '13050501', false],
            ['91_INGRESO', 'Nota credito - reversa ingreso', '41351001', false],
            ['91_IVA_GEN', 'Nota credito - reversa IVA generado', '24080101', true],
            ['91_INV_ENTRA', 'Nota credito - entrada de inventario', '14350101', false],
            ['91_COSTO', 'Nota credito - reversa costo', '61351001', false],
        ];

        foreach ($concepts as $concept) {
            $values = [
                'internal_code' => $concept[0],
                'name' => $concept[1],
                'accountable_id' => $concept[2],
                'accountable_type' => 'chart_account',
                'is_tax_concept' => $concept[3],
                'updated_at' => $now,
            ];

            $exists = DB::table('accounting_concepts')
                ->where('type_concept', $concept[0])
                ->exists();

            if ($exists) {
                DB::table('accounting_concepts')
                    ->where('type_concept', $concept[0])
                    ->update($values);

                continue;
            }

            DB::table('accounting_concepts')->insert($values + [
                'id' => (string) Str::uuid(),
                'uuid' => (string) Str::uuid(),
                'type_concept' => $concept[0],
                'created_at' => $now,
            ]);
        }
    }
}
