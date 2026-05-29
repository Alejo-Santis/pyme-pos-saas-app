<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantCeramicsDemoSeeder extends Seeder
{
    private const PASSWORD = 'DemoTenant-2026!';

    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $adminId = $this->seedUsers($now);
            $companyId = $this->seedCompany($now);
            [$establishments, $warehouses] = $this->seedLocations($now);
            $resolutions = $this->seedResolutions($now);
            $thirdParties = $this->seedThirdParties($now);
            $items = $this->seedInventory($warehouses, $thirdParties, $now);
            $cash = $this->seedCashAndBanks($thirdParties, $adminId, $now);
            $terminals = $this->seedPos($establishments, $warehouses, $resolutions, $cash, $adminId, $now);
            $purchaseOrders = $this->seedPurchases($items, $thirdParties, $adminId, $now);
            $documents = $this->seedDocuments($items, $thirdParties, $warehouses, $resolutions, $terminals, $adminId, $now);
            $this->seedCashReceipts($documents, $thirdParties, $adminId, $now);
            $this->seedElectronicFlow($documents, $purchaseOrders, $thirdParties, $now);
            $this->seedPayroll($adminId, $now);
            $this->seedOperationalExtras($documents, $adminId, $now);

            DB::table('companies')->where('id', $companyId)->update([
                'electronic_documents' => true,
                'updated_at' => $now,
            ]);
        });
    }

    private function seedUsers($now): string
    {
        $users = [
            ['name' => 'Laura Marcela Rios', 'email' => 'admin@maxicasa-demo.test', 'identification_number' => '1020456789', 'phone' => '3004567890'],
            ['name' => 'Carlos Andres Yepes', 'email' => 'ventas.medellin@maxicasa-demo.test', 'identification_number' => '71548963', 'phone' => '3107894561'],
            ['name' => 'Sandra Milena Ospina', 'email' => 'caja@maxicasa-demo.test', 'identification_number' => '43567901', 'phone' => '3018897712'],
            ['name' => 'Jhon Alexander Giraldo', 'email' => 'bodega@maxicasa-demo.test', 'identification_number' => '1037629184', 'phone' => '3126601144'],
        ];

        $adminId = '';

        foreach ($users as $index => $user) {
            $record = $this->uuidRecord('users', ['email' => $user['email']], [
                'type_document_identification_id' => 3,
                'identification_number' => $user['identification_number'],
                'name' => $user['name'],
                'email' => $user['email'],
                'email_verified_at' => $now,
                'password' => Hash::make(self::PASSWORD),
                'phone' => $user['phone'],
                'is_active' => true,
                'onboarding_completed' => true,
            ], $now);

            if ($index === 0) {
                $adminId = $record;
            }
        }

        return $adminId;
    }

    private function seedCompany($now): string
    {
        return $this->uuidRecord('companies', ['identification_number' => '900736281'], [
            'dv' => '4',
            'business_name' => 'Maxicasa Ceramicas y Acabados S.A.S.',
            'trade_name' => 'Maxicasa Demo',
            'type_document_identification_id' => 6,
            'type_organization_id' => 1,
            'type_regime_id' => 1,
            'type_liability_id' => 6,
            'country_id' => 46,
            'municipality_id' => $this->municipalityId('05001', 149),
            'email' => 'facturacion@maxicasa-demo.test',
            'phone' => '6044442080',
            'address' => 'Carrera 48 # 35-70, Medellin, Antioquia',
            'electronic_documents' => true,
            'use_price_list' => true,
            'prices_with_taxes_included' => false,
            'use_aiu' => false,
            'use_ibua' => false,
            'use_icui' => false,
            'type_environment_id' => 2,
            'dian_software_id' => 'demo-software-id-pendiente',
            'dian_software_security_code' => 'demo-security-code-pendiente',
            'dian_test_set_id' => 'demo-test-set-pendiente',
            'dian_provider' => 'nextpyme',
            'api_path_fe' => 'https://api-demo.nextpyme.test',
            'api_token_fe' => 'demo-token-pendiente-de-reemplazar',
            'logo_path' => 'demo/maxicasa/logo.png',
            'is_active' => true,
        ], $now);
    }

    private function seedLocations($now): array
    {
        $locations = [
            [
                'key' => 'principal',
                'name' => 'Sala Principal Medellin',
                'address' => 'Carrera 48 # 35-70, Medellin',
                'municipality_id' => $this->municipalityId('05001', 149),
                'warehouses' => [
                    ['key' => 'med-bodega', 'name' => 'Bodega Principal Medellin', 'code' => 'BOD-MED', 'main' => true],
                    ['key' => 'med-exhibicion', 'name' => 'Exhibicion Medellin', 'code' => 'EXH-MED', 'main' => false],
                ],
            ],
            [
                'key' => 'oriente',
                'name' => 'Sala Oriente Antioqueno',
                'address' => 'Autopista Medellin-Bogota KM 32, Rionegro',
                'municipality_id' => $this->municipalityId('05615', 604),
                'warehouses' => [
                    ['key' => 'rio-bodega', 'name' => 'Bodega Rionegro', 'code' => 'BOD-RIO', 'main' => true],
                ],
            ],
            [
                'key' => 'bogota',
                'name' => 'Centro de Distribucion Bogota',
                'address' => 'Av. Calle 80 # 116-40, Bogota',
                'municipality_id' => $this->municipalityId('11001', 149),
                'warehouses' => [
                    ['key' => 'bog-cedi', 'name' => 'CEDI Bogota', 'code' => 'CEDI-BOG', 'main' => true],
                ],
            ],
        ];

        $establishments = [];
        $warehouses = [];

        foreach ($locations as $index => $location) {
            $establishmentId = $this->uuidRecord('establishments', ['name' => $location['name']], [
                'business_name' => 'Maxicasa Ceramicas y Acabados S.A.S.',
                'address' => $location['address'],
                'municipality_id' => $location['municipality_id'],
                'is_main' => $index === 0,
                'sync_items_full' => true,
            ], $now, withSecondaryUuid: true);

            $establishments[$location['key']] = $establishmentId;

            foreach ($location['warehouses'] as $warehouse) {
                $warehouseId = $this->uuidRecord('warehouses', ['internal_code' => $warehouse['code']], [
                    'establishment_id' => $establishmentId,
                    'name' => $warehouse['name'],
                    'description' => 'Inventario operativo para ' . $location['name'],
                    'is_main' => $warehouse['main'],
                    'is_active' => true,
                ], $now, withSecondaryUuid: true);

                $warehouses[$warehouse['key']] = $warehouseId;
            }
        }

        return [$establishments, $warehouses];
    }

    private function seedResolutions($now): array
    {
        $defs = [
            'fev' => ['type_document_id' => 1, 'type_document_operation_id' => 1, 'resolution' => '18764072000111', 'prefix' => 'FEV', 'from' => 1001, 'to' => 5000, 'current_number' => 1015],
            'pos' => ['type_document_id' => 3, 'type_document_operation_id' => 2, 'resolution' => '18764072000991', 'prefix' => 'POS', 'from' => 20001, 'to' => 90000, 'current_number' => 20043],
            'nc' => ['type_document_id' => 7, 'type_document_operation_id' => 5, 'resolution' => null, 'prefix' => 'NC', 'from' => 1, 'to' => 9999, 'current_number' => 4],
            'ds' => ['type_document_id' => 5, 'type_document_operation_id' => 8, 'resolution' => '18764072000888', 'prefix' => 'DS', 'from' => 3001, 'to' => 8000, 'current_number' => 3012],
        ];

        $ids = [];
        foreach ($defs as $key => $data) {
            $ids[$key] = $this->uuidRecord('resolutions', ['prefix' => $data['prefix']], [
                ...$data,
                'resolution_date' => '2026-01-15',
                'technical_key' => 'demo-technical-key-' . $data['prefix'],
                'date_from' => '2026-01-15',
                'date_to' => '2027-01-15',
                'is_active' => true,
            ], $now, withSecondaryUuid: true);

            $this->uuidRecord('sends',
                ['resolution_id' => $ids[$key], 'type_document_id' => $data['type_document_id']],
                ['next_consecutive' => $data['current_number'] + 1],
                $now
            );
        }

        return $ids;
    }

    private function seedThirdParties($now): array
    {
        $thirds = [
            'constructor' => ['nit' => '901248116', 'dv' => '8', 'name' => 'Constructora Alto Horizonte S.A.S.', 'email' => 'compras@altohorizonte.test', 'phone' => '6017425580', 'address' => 'Calle 100 # 19-54, Bogota', 'city' => '11001', 'customer' => true, 'provider' => false, 'credit' => 85000000, 'days' => 45],
            'hotel' => ['nit' => '900552431', 'dv' => '2', 'name' => 'Hoteles La Sierra S.A.S.', 'email' => 'proveeduria@hoteleslasierra.test', 'phone' => '6044482100', 'address' => 'Calle 10 # 43A-30, Medellin', 'city' => '05001', 'customer' => true, 'provider' => false, 'credit' => 42000000, 'days' => 30],
            'ferreteria' => ['nit' => '830129774', 'dv' => '5', 'name' => 'Ferreteria Nacional de Acabados Ltda.', 'email' => 'facturas@ferreacabados.test', 'phone' => '6053851122', 'address' => 'Via 40 # 73-290, Barranquilla', 'city' => '08001', 'customer' => true, 'provider' => true, 'credit' => 65000000, 'days' => 30],
            'distribuidor' => ['nit' => '890923501', 'dv' => '1', 'name' => 'Distribuciones Porcelanicas Andinas S.A.', 'email' => 'cartera@porcelanicasandinas.test', 'phone' => '6024893321', 'address' => 'Carrera 100 # 16-20, Cali', 'city' => '76001', 'customer' => false, 'provider' => true, 'credit' => 0, 'days' => 0],
            'adhesivos' => ['nit' => '900418672', 'dv' => '9', 'name' => 'Pegantes y Boquillas del Valle S.A.S.', 'email' => 'ventas@pegantesvalle.test', 'phone' => '6025554421', 'address' => 'Calle 25 # 7-11, Yumbo', 'city' => '76892', 'customer' => false, 'provider' => true, 'credit' => 0, 'days' => 0],
            'retail' => ['nit' => '1037654321', 'dv' => null, 'name' => 'Marcela', 'surname' => 'Restrepo Gomez', 'email' => 'marcela.restrepo@example.test', 'phone' => '3006679911', 'address' => 'Transversal 39B # 72-15, Medellin', 'city' => '05001', 'customer' => true, 'provider' => false, 'credit' => 2500000, 'days' => 15, 'natural' => true],
        ];

        $ids = [];
        foreach ($thirds as $key => $third) {
            $id = $this->uuidRecord('third_parties', ['identification_number' => $third['nit']], [
                'type_document_identification_id' => ($third['natural'] ?? false) ? 3 : 6,
                'dv' => $third['dv'],
                'type_organization_id' => ($third['natural'] ?? false) ? 2 : 1,
                'type_regime_id' => 1,
                'type_liability_id' => 7,
                'type_third_id' => $third['customer'] ? 1 : 2,
                'name' => $third['name'],
                'surname' => $third['surname'] ?? null,
                'email' => $third['email'],
                'phone' => $third['phone'],
                'address' => $third['address'],
                'country_id' => 46,
                'municipality_id' => $this->municipalityId($third['city'], 149),
                'excluded_from_taxes' => false,
                'great_contributor' => in_array($key, ['constructor', 'distribuidor'], true),
                'self_retaining' => $key === 'constructor',
                'seller' => false,
                'credit_limit' => $third['credit'],
                'payment_days' => $third['days'],
                'is_active' => true,
            ], $now, withSecondaryUuid: true);

            DB::table('party_linkages')->updateOrInsert(
                ['third_party_id' => $id],
                ['customer' => $third['customer'], 'provider' => $third['provider'], 'other' => false, 'created_at' => $now, 'updated_at' => $now]
            );

            $ids[$key] = $id;
        }

        return $ids;
    }

    private function seedInventory(array $warehouses, array $thirdParties, $now): array
    {
        $categories = [];
        foreach ([
            'pisos' => ['Pisos ceramicos', null],
            'porcelanato' => ['Porcelanatos', null],
            'paredes' => ['Revestimientos de pared', null],
            'banos' => ['Banos y sanitarios', null],
            'adhesivos' => ['Adhesivos y boquillas', null],
            'griferia' => ['Griferia y accesorios', null],
        ] as $key => [$name, $parent]) {
            $categories[$key] = $this->uuidRecord('item_categories', ['name' => $name], [
                'description' => 'Categoria demo para ' . strtolower($name),
                'parent_id' => $parent,
            ], $now);
        }

        $groups = [];
        foreach (['CER' => 'Ceramicas', 'POR' => 'Porcelanatos', 'INS' => 'Insumos de instalacion', 'SAN' => 'Sanitarios y complementos'] as $code => $name) {
            $groups[$code] = $this->uuidRecord('item_groups', ['internal_code' => $code], ['name' => $name], $now);
        }

        $lines = [];
        foreach (['INT' => 'Interior residencial', 'EXT' => 'Exterior alto trafico', 'MUR' => 'Muros y decorados', 'PRO' => 'Productos profesionales'] as $code => $name) {
            $lines[$code] = $this->uuidRecord('item_lines', ['internal_code' => $code], ['name' => $name, 'description' => 'Linea demo ' . strtolower($name)], $now);
        }

        $products = [
            ['code' => 'CER-ALP-45X45-GR', 'name' => 'Ceramica Alpes gris 45x45 caja 1.62 m2', 'short' => 'Alpes gris 45', 'ref' => 'ALP4545GR', 'cat' => 'pisos', 'group' => 'CER', 'line' => 'INT', 'cost' => 31500, 'price' => 48900, 'min' => 80, 'max' => 420, 'provider' => 'distribuidor', 'stock' => [180, 42, 95, 210], 'unit' => 70],
            ['code' => 'CER-NAT-60X60-BE', 'name' => 'Ceramica Natura beige 60x60 caja 1.80 m2', 'short' => 'Natura beige', 'ref' => 'NAT6060BE', 'cat' => 'pisos', 'group' => 'CER', 'line' => 'INT', 'cost' => 38200, 'price' => 57900, 'min' => 60, 'max' => 360, 'provider' => 'distribuidor', 'stock' => [120, 38, 70, 160], 'unit' => 70],
            ['code' => 'POR-CAL-60X120-MA', 'name' => 'Porcelanato Calacatta mate 60x120 caja 1.44 m2', 'short' => 'Calacatta mate', 'ref' => 'CAL60120MA', 'cat' => 'porcelanato', 'group' => 'POR', 'line' => 'INT', 'cost' => 74500, 'price' => 112900, 'min' => 35, 'max' => 220, 'provider' => 'distribuidor', 'stock' => [64, 16, 28, 90], 'unit' => 70],
            ['code' => 'POR-CEM-80X80-GR', 'name' => 'Porcelanato cemento gris 80x80 caja 1.92 m2', 'short' => 'Cemento 80', 'ref' => 'CEM8080GR', 'cat' => 'porcelanato', 'group' => 'POR', 'line' => 'EXT', 'cost' => 81200, 'price' => 124500, 'min' => 30, 'max' => 200, 'provider' => 'distribuidor', 'stock' => [54, 14, 22, 74], 'unit' => 70],
            ['code' => 'REV-MET-30X60-BL', 'name' => 'Revestimiento Metro blanco 30x60 caja 1.50 m2', 'short' => 'Metro blanco', 'ref' => 'MET3060BL', 'cat' => 'paredes', 'group' => 'CER', 'line' => 'MUR', 'cost' => 28900, 'price' => 43900, 'min' => 70, 'max' => 360, 'provider' => 'distribuidor', 'stock' => [140, 44, 82, 130], 'unit' => 70],
            ['code' => 'REV-DEK-25X75-AR', 'name' => 'Decorado Aranda relieve 25x75 caja 1.31 m2', 'short' => 'Aranda relieve', 'ref' => 'DEK2575AR', 'cat' => 'paredes', 'group' => 'CER', 'line' => 'MUR', 'cost' => 43200, 'price' => 68900, 'min' => 28, 'max' => 160, 'provider' => 'distribuidor', 'stock' => [48, 12, 20, 65], 'unit' => 70],
            ['code' => 'PEG-FLEX-25KG', 'name' => 'Pegante flexible gris saco 25 kg', 'short' => 'Pegante flex', 'ref' => 'PFLEX25', 'cat' => 'adhesivos', 'group' => 'INS', 'line' => 'PRO', 'cost' => 22600, 'price' => 34900, 'min' => 120, 'max' => 600, 'provider' => 'adhesivos', 'stock' => [260, 70, 120, 310], 'unit' => 94],
            ['code' => 'BOQ-PLUS-2KG-BL', 'name' => 'Boquilla plus blanca bolsa 2 kg', 'short' => 'Boquilla blanca', 'ref' => 'BOQ2BL', 'cat' => 'adhesivos', 'group' => 'INS', 'line' => 'PRO', 'cost' => 7200, 'price' => 11900, 'min' => 160, 'max' => 900, 'provider' => 'adhesivos', 'stock' => [420, 96, 210, 500], 'unit' => 94],
            ['code' => 'SAN-ONEP-BL', 'name' => 'Sanitario una pieza blanco elongado', 'short' => 'Sanitario one', 'ref' => 'SANONEPBL', 'cat' => 'banos', 'group' => 'SAN', 'line' => 'INT', 'cost' => 286000, 'price' => 429900, 'min' => 8, 'max' => 60, 'provider' => 'ferreteria', 'stock' => [18, 4, 8, 24], 'unit' => 94],
            ['code' => 'GRI-LAV-MON-CRO', 'name' => 'Griferia lavamanos monocontrol cromada', 'short' => 'Grif lavamanos', 'ref' => 'GLMCR', 'cat' => 'griferia', 'group' => 'SAN', 'line' => 'INT', 'cost' => 92000, 'price' => 149900, 'min' => 12, 'max' => 80, 'provider' => 'ferreteria', 'stock' => [34, 8, 16, 48], 'unit' => 94],
            ['code' => 'SRV-INST-M2', 'name' => 'Servicio de instalacion de piso por m2', 'short' => 'Instalacion m2', 'ref' => 'SRVINSTM2', 'cat' => 'adhesivos', 'group' => 'INS', 'line' => 'PRO', 'cost' => 0, 'price' => 38000, 'min' => 0, 'max' => 0, 'provider' => null, 'stock' => [0, 0, 0, 0], 'unit' => 70, 'service' => true],
        ];

        $warehouseIds = array_values($warehouses);
        $ids = [];

        foreach ($products as $product) {
            $id = $this->uuidRecord('items', ['internal_code' => $product['code']], [
                'name' => $product['name'],
                'short_name' => $product['short'],
                'reference' => $product['ref'],
                'note' => 'Producto demo para flujo comercial de ceramicas y acabados.',
                'type' => ($product['service'] ?? false) ? 'service' : 'product',
                'item_category_id' => $categories[$product['cat']],
                'clasification_id' => ($product['service'] ?? false) ? 2 : 1,
                'tax_category_id' => 1,
                'unit_measure_id' => $product['unit'],
                'group_id' => $groups[$product['group']],
                'line_id' => $lines[$product['line']],
                'last_purchase_price' => $product['cost'],
                'average_cost' => $product['cost'],
                'default_sale_price' => $product['price'],
                'consumption_tax' => 0,
                'icui' => 0,
                'ibua' => 0,
                'double_taxes' => false,
                'barcode_one' => '770' . str_pad((string) crc32($product['code']), 10, '0', STR_PAD_LEFT),
                'minimum_existence' => $product['min'],
                'maximum_existence' => $product['max'],
                'minor_account_id' => '143505',
                'is_active' => true,
                'manages_stock' => ! ($product['service'] ?? false),
                'is_service' => $product['service'] ?? false,
            ], $now);

            DB::table('item_taxes')->updateOrInsert(
                ['item_id' => $id, 'tax_id' => 1],
                ['application' => 3, 'percent' => 19, 'amount' => 0, 'created_at' => $now, 'updated_at' => $now]
            );

            foreach ($warehouseIds as $index => $warehouseId) {
                $stock = $product['stock'][$index] ?? 0;
                DB::table('item_warehouse')->updateOrInsert(
                    ['item_id' => $id, 'warehouse_id' => $warehouseId],
                    ['stock' => $stock, 'average_cost' => $product['cost'], 'created_at' => $now, 'updated_at' => $now]
                );

                DB::table('item_price_lists')->updateOrInsert(
                    ['item_id' => $id, 'warehouse_id' => $warehouseId],
                    ['list_one' => $product['price'], 'list_two' => round($product['price'] * 0.96, 2), 'list_three' => round($product['price'] * 0.91, 2), 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $this->uuidRecord('item_presentations', ['item_id' => $id, 'name' => 'Unidad / caja base'], [
                'unit_measure_id' => $product['unit'],
                'warehouse_id' => $warehouseIds[0] ?? null,
                'units_per_pack' => 1,
                'barcode' => '1780' . str_pad((string) abs(crc32($product['ref'])), 9, '0', STR_PAD_LEFT),
                'price' => $product['price'],
                'is_default' => true,
            ], $now, withSecondaryUuid: true);

            if ($product['provider']) {
                DB::table('item_by_third_parties')->updateOrInsert(
                    ['item_id' => $id, 'third_party_id' => $thirdParties[$product['provider']]],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            $ids[$product['code']] = $id;
        }

        return $ids;
    }

    private function seedCashAndBanks(array $thirdParties, string $adminId, $now): array
    {
        $bankId = $this->uuidRecord('banks', ['name' => 'Bancolombia Demo'], [
            'internal_code' => 'BAN-BCO',
            'third_party_id' => null,
            'code' => '007',
            'state' => true,
        ], $now);

        $accountId = $this->uuidRecord('bank_accounts', ['account_bank_number' => '03100012345'], [
            'internal_code' => 'ACC-DEMO-001',
            'name' => 'Cuenta Corriente Principal Bancolombia',
            'type' => 'Corriente',
            'bank_id' => $bankId,
            'currency_id' => 35,
            'balance' => 184250000,
            'has_gmf' => true,
            'state' => true,
        ], $now);

        $cashBoxId = $this->uuidRecord('cash_boxes', ['name' => 'Caja Principal Medellin'], [
            'internal_code' => 'CB-DEMO-001',
            'is_main' => true,
            'state' => true,
        ], $now);

        foreach ([
            ['debit' => 184250000, 'credit' => 0, 'description' => 'Saldo inicial demo cuenta principal', 'reference' => 'SI-BAN-2026'],
            ['debit' => 0, 'credit' => 12500000, 'description' => 'Pago anticipo proveedor porcelanatos', 'reference' => 'TRF-ANT-001', 'third_party_id' => $thirdParties['distribuidor']],
            ['debit' => 28475000, 'credit' => 0, 'description' => 'Recaudo cliente Constructora Alto Horizonte', 'reference' => 'RC-CLI-001', 'third_party_id' => $thirdParties['constructor']],
        ] as $movement) {
            $this->uuidRecord('bank_account_movements', ['reference' => $movement['reference']], [
                'bank_account_id' => $accountId,
                'debit' => $movement['debit'],
                'credit' => $movement['credit'],
                'user_id' => $adminId,
                'third_party_id' => $movement['third_party_id'] ?? null,
                'document_id' => null,
                'type_document_operation_id' => null,
                'amount' => max($movement['debit'], $movement['credit']),
                'description' => $movement['description'],
                'issue_date' => '2026-05-20 10:00:00',
                'state' => true,
            ], $now);
        }

        foreach ([
            ['debit' => 2500000, 'credit' => 0, 'description' => 'Base operativa caja principal', 'reference' => 'BASE-CAJA-001'],
            ['debit' => 689900, 'credit' => 0, 'description' => 'Venta POS de contado demo', 'reference' => 'POS-20041'],
            ['debit' => 0, 'credit' => 180000, 'description' => 'Retiro menor para mensajeria y parqueaderos', 'reference' => 'RET-CAJA-001'],
        ] as $movement) {
            $this->uuidRecord('cash_movements', ['reference' => $movement['reference']], [
                'cash_box_id' => $cashBoxId,
                'debit' => $movement['debit'],
                'credit' => $movement['credit'],
                'third_party_id' => null,
                'document_id' => null,
                'type_document_operation_id' => null,
                'amount' => max($movement['debit'], $movement['credit']),
                'description' => $movement['description'],
                'issue_date' => '2026-05-20 09:00:00',
                'state' => true,
            ], $now);
        }

        return ['bank' => $bankId, 'account' => $accountId, 'cash_box' => $cashBoxId];
    }

    private function seedPos(array $establishments, array $warehouses, array $resolutions, array $cash, string $adminId, $now): array
    {
        $terminalId = $this->uuidRecord('pos_terminals', ['serial_number' => 'POS-MED-001'], [
            'name' => 'POS Sala Medellin 01',
            'resolution_id' => $resolutions['pos'],
            'warehouse_id' => $warehouses['med-exhibicion'],
            'establishment_id' => $establishments['principal'],
            'cash_box_id' => $cash['cash_box'],
            'location' => 'Mostrador principal',
            'printer_name' => 'EPSON TM-T20III',
            'printer_ip' => '192.168.10.45',
            'printer_port' => '9100',
            'printer_type' => 'escpos',
            'printer_start_with' => 'drawer',
            'is_usb' => false,
            'state' => true,
        ], $now);

        $terminalUserWhere = $this->filterColumns('pos_terminal_users', [
            'user_id' => $adminId,
            'warehouse_id' => $warehouses['med-exhibicion'],
        ]);

        $this->uuidRecord('pos_terminal_users',
            $terminalUserWhere,
            [
                'establishment_id' => $establishments['principal'],
                'warehouse_id' => $warehouses['med-exhibicion'],
                'initial_balance' => 500000,
                'current_balance' => 1189900,
                'final_balance' => 0,
                'total_sales' => 689900,
                'total_cash' => 689900,
                'total_card' => 0,
                'total_transfer' => 0,
                'active_shift' => true,
                'cashier_session_key' => 'shift-demo-20260520-001',
                'shift_opened_at' => '2026-05-20 08:00:00',
                'shift_closed_at' => null,
            ],
            $now
        );

        $countId = DB::table('cash_register_counts')->where('opening_date', '2026-05-19 08:00:00')->value('id');
        if (! $countId) {
            $countId = DB::table('cash_register_counts')->insertGetId($this->filterColumns('cash_register_counts', [
                'user_id' => $adminId,
                'establishment_id' => $establishments['principal'],
                'warehouse_id' => $warehouses['med-exhibicion'],
                'opening_date' => '2026-05-19 08:00:00',
                'closing_date' => '2026-05-19 18:12:00',
                'cash_amount' => 2450000,
                'mismatch_amount' => 0,
                'surplus_amount' => 0,
                'details' => json_encode(['100000' => 12, '50000' => 18, '20000' => 11, '10000' => 13]),
                'calculated_payment_methods' => json_encode(['cash' => 2450000, 'card' => 3720000, 'transfer' => 1450000]),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->uuidRecord('cash_register_summaries', ['pos_terminal_id' => $terminalId, 'closing_date' => '2026-05-19 18:12:00'], [
            'cash_register_count_id' => null,
            'total_sales' => 7620000,
            'total_taxes' => 1216638.66,
            'total_discounts' => 185000,
            'payment_summary' => json_encode(['cash' => 2450000, 'card' => 3720000, 'transfer' => 1450000]),
            'difference' => 0,
            'warehouse_id' => $warehouses['med-exhibicion'],
            'establishment_id' => $establishments['principal'],
        ], $now);

        return ['main' => $terminalId];
    }

    private function seedPurchases(array $items, array $thirdParties, string $adminId, $now): array
    {
        $purchaseId = $this->uuidRecord('purchase_orders', ['internal_code' => 'OC-DEMO-0001'], [
            'third_party_id' => $thirdParties['distribuidor'],
            'user_id' => $adminId,
            'approver_user_id' => $adminId,
            'document_id' => null,
            'reference' => 'COT-PA-2026-0418',
            'amount' => 37645000,
            'issue_date' => '2026-05-13',
            'notes' => 'Reposicion de porcelanatos y ceramicas de alta rotacion para temporada de remodelaciones.',
            'status' => 'approved',
            'approved' => true,
            'annulled' => false,
        ], $now, withSecondaryUuid: true);

        foreach ([
            ['item' => 'POR-CAL-60X120-MA', 'qty' => 120, 'cost' => 74500],
            ['item' => 'POR-CEM-80X80-GR', 'qty' => 90, 'cost' => 81200],
            ['item' => 'CER-NAT-60X60-BE', 'qty' => 240, 'cost' => 38200],
        ] as $line) {
            DB::table('items_purchase_orders')->updateOrInsert(
                ['purchase_order_id' => $purchaseId, 'item_id' => $items[$line['item']]],
                [
                    'invoice_quantity' => $line['qty'],
                    'average_cost' => $line['cost'],
                    'tax' => json_encode([['tax_id' => 1, 'percent' => 19, 'amount' => round($line['qty'] * $line['cost'] * 0.19, 2)]]),
                    'line_extension_amount' => $line['qty'] * $line['cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('purchase_order_histories')->updateOrInsert(
            ['purchase_order_id' => $purchaseId, 'history' => 'Orden aprobada por gerencia comercial.'],
            ['user_id' => $adminId, 'history_issue_date' => '2026-05-13 16:25:00', 'notes' => 'Proveedor confirma despacho parcial en 72 horas.', 'created_at' => $now, 'updated_at' => $now]
        );

        return ['main' => $purchaseId];
    }

    private function seedDocuments(array $items, array $thirdParties, array $warehouses, array $resolutions, array $terminals, string $adminId, $now): array
    {
        $documents = [];

        $documents['fev'] = $this->createDocument('FEV-1012', [
            ['item' => 'POR-CAL-60X120-MA', 'qty' => 48, 'price' => 112900, 'discount' => 4],
            ['item' => 'PEG-FLEX-25KG', 'qty' => 36, 'price' => 34900, 'discount' => 0],
            ['item' => 'BOQ-PLUS-2KG-BL', 'qty' => 28, 'price' => 11900, 'discount' => 0],
        ], $items, [
            'third_party_id' => $thirdParties['constructor'],
            'user_id' => $adminId,
            'seller_id' => $adminId,
            'type_document_id' => 1,
            'type_document_operation_id' => 1,
            'resolution_id' => $resolutions['fev'],
            'prefix' => 'FEV',
            'number' => 1012,
            'issue_date' => '2026-05-17',
            'paid' => false,
            'electronic' => true,
            'accounted' => true,
            'balance_factor' => 0.45,
            'warehouse_out' => $warehouses['med-bodega'],
            'pos_terminal_id' => null,
        ], $now);

        $documents['pos'] = $this->createDocument('POS-20041', [
            ['item' => 'SAN-ONEP-BL', 'qty' => 1, 'price' => 429900, 'discount' => 0],
            ['item' => 'GRI-LAV-MON-CRO', 'qty' => 1, 'price' => 149900, 'discount' => 0],
        ], $items, [
            'third_party_id' => $thirdParties['retail'],
            'user_id' => $adminId,
            'seller_id' => $adminId,
            'type_document_id' => 3,
            'type_document_operation_id' => 2,
            'resolution_id' => $resolutions['pos'],
            'prefix' => 'POS',
            'number' => 20041,
            'issue_date' => '2026-05-19',
            'paid' => true,
            'electronic' => false,
            'accounted' => true,
            'balance_factor' => 0,
            'warehouse_out' => $warehouses['med-exhibicion'],
            'pos_terminal_id' => $terminals['main'],
        ], $now);

        $documents['credit_note'] = $this->createDocument('NC-4', [
            ['item' => 'BOQ-PLUS-2KG-BL', 'qty' => 4, 'price' => 11900, 'discount' => 0],
        ], $items, [
            'third_party_id' => $thirdParties['constructor'],
            'user_id' => $adminId,
            'seller_id' => $adminId,
            'type_document_id' => 7,
            'type_document_operation_id' => 5,
            'resolution_id' => $resolutions['nc'],
            'prefix' => 'NC',
            'number' => 4,
            'issue_date' => '2026-05-20',
            'paid' => true,
            'electronic' => true,
            'accounted' => true,
            'balance_factor' => 0,
            'warehouse_in' => $warehouses['med-bodega'],
            'movement_type' => 'IN',
            'reference_id' => $documents['fev'],
        ], $now);

        DB::table('document_discrepancy_responses')->updateOrInsert(
            ['document_id' => $documents['credit_note']],
            ['discrepancy_responsable_id' => 1, 'discrepancy_responsable_type' => 'credit_note', 'created_at' => $now, 'updated_at' => $now]
        );

        return $documents;
    }

    private function seedCashReceipts(array $documents, array $thirdParties, string $adminId, $now): void
    {
        $incomeId = $this->uuidRecord('income_and_expenses', ['internal_code' => 'ING-CARTERA'], [
            'name' => 'Recaudo de cartera clientes',
            'description' => 'Ingreso por abonos de facturas a credito.',
            'type_document_operation_id' => 13,
            'accountable_id' => null,
            'accountable_type' => null,
            'account_nature_id' => 1,
            'user_id' => $adminId,
        ], $now, withSecondaryUuid: true);

        $receiptId = $this->uuidRecord('cash_receipts', ['internal_code' => 'RC-DEMO-0001'], [
            'type_document_operation_id' => 13,
            'user_id' => $adminId,
            'third_party_id' => $thirdParties['constructor'],
            'income_and_expense_id' => $incomeId,
            'resolution_id' => null,
            'prefix' => 'RC',
            'number' => 1,
            'total_amount' => 28475000,
            'amount_received' => 28475000,
            'notes' => 'Abono parcial factura FEV-1012 recibido por transferencia.',
            'annulled' => false,
            'issue_date' => '2026-05-20',
        ], $now, withSecondaryUuid: true);

        $this->uuidRecord('cash_receipt_details', ['cash_receipt_id' => $receiptId, 'transaction_reference' => 'TRF-BAN-20260520-01'], [
            'document_id' => $documents['fev'],
            'rate_holding_tax_id' => null,
            'withholdings_tax' => 0,
            'quantity' => 1,
            'payment_form_id' => 1,
            'currency_id' => 35,
            'amount' => 28475000,
        ], $now, withSecondaryUuid: true);
    }

    private function seedElectronicFlow(array $documents, array $purchaseOrders, array $thirdParties, $now): void
    {
        foreach ([
            $documents['fev'] => ['is_invoice' => true, 'key' => 'CUFE-DEMO-FEV-1012', 'status' => 'Procesado correctamente'],
            $documents['credit_note'] => ['is_nc' => true, 'key' => 'CUDE-DEMO-NC-4', 'status' => 'Nota credito validada'],
        ] as $documentId => $data) {
            $this->uuidRecord('sending_electronic_documents', ['document_id' => $documentId, 'xml_document_key' => $data['key']], [
                'event_id' => null,
                'error_message' => null,
                'is_valid' => true,
                'is_invoice' => $data['is_invoice'] ?? false,
                'is_event' => false,
                'is_payroll' => false,
                'is_nc' => $data['is_nc'] ?? false,
                'is_nd' => false,
                'is_ds' => false,
                'is_nds' => false,
                'is_eqdocs' => false,
                'status_code' => '00',
                'status_description' => 'Validado',
                'status_message' => $data['status'],
                'qr_str' => 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=' . $data['key'],
                'response_api' => json_encode(['demo' => true, 'message' => $data['status']]),
                'dian_validation_date_time' => json_encode(['date' => '2026-05-20', 'time' => '10:35:00']),
                'attempts' => 1,
                'status' => true,
            ], $now);
        }

        DB::table('events')->updateOrInsert(
            ['document_id' => $documents['fev'], 'cude_030' => 'CUDE-DEMO-ACUSE-1012'],
            ['cufe' => 'CUFE-DEMO-FEV-1012', 'cude_031' => null, 'cude_032' => 'CUDE-DEMO-RECIBO-1012', 'cude_033' => null, 'cude_034' => null, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]
        );

        $mailboxId = $this->uuidRecord('tax_mailboxes', ['cufe' => 'CUFE-PROV-DEMO-5561'], [
            'type_document_id' => 1,
            'document_id' => null,
            'identification_number_provider' => '890923501',
            'business_name_provider' => 'Distribuciones Porcelanicas Andinas S.A.',
            'subject' => 'Factura electronica FEP-5561 - Porcelanatos mayo',
            'xml_file_name' => 'ad0900000000000000005561.xml',
            'pdf_file_name' => 'FEP-5561.pdf',
            'date' => '2026-05-18 07:44:00',
            'tax_inclusive_amount' => 37645000,
            'base64_attacheddocument' => json_encode(['demo' => true, 'content' => 'Pendiente reemplazar por XML real']),
            'events' => json_encode([['code' => '030', 'name' => 'Acuse de recibo', 'date' => '2026-05-18']]),
            'has_order_reference' => true,
            'order_reference' => json_encode(['id' => 'OC-DEMO-0001']),
            'payment_form' => json_encode(['payment_form_id' => 2, 'payment_method_id' => 30, 'payment_due_date' => '2026-06-17']),
        ], $now, withSecondaryUuid: true);

        $this->uuidRecord('purchase_in_progress_mail_boxes', ['tax_mailbox_id' => $mailboxId], [
            'document_id' => null,
            'purchase_order_id' => $purchaseOrders['main'],
            'data' => json_encode(['supplier_id' => $thirdParties['distribuidor'], 'stage' => 'matching_purchase_order']),
            'status' => false,
        ], $now, withSecondaryUuid: true);
    }

    private function seedPayroll(string $adminId, $now): void
    {
        $employees = [
            ['doc' => '43567901', 'first' => 'Sandra', 'middle' => 'Milena', 'last' => 'Ospina', 'second' => 'Vargas', 'email' => 'sandra.ospina@maxicasa-demo.test', 'phone' => '3018897712', 'job' => 'Cajera principal', 'salary' => 2380000, 'risk' => 1],
            ['doc' => '1037629184', 'first' => 'Jhon', 'middle' => 'Alexander', 'last' => 'Giraldo', 'second' => 'Lopez', 'email' => 'jhon.giraldo@maxicasa-demo.test', 'phone' => '3126601144', 'job' => 'Coordinador de bodega', 'salary' => 2860000, 'risk' => 3],
            ['doc' => '71548963', 'first' => 'Carlos', 'middle' => 'Andres', 'last' => 'Yepes', 'second' => 'Marin', 'email' => 'carlos.yepes@maxicasa-demo.test', 'phone' => '3107894561', 'job' => 'Asesor comercial senior', 'salary' => 3250000, 'risk' => 1],
        ];

        $runId = $this->uuidRecord('payroll_runs', ['name' => 'Nomina mayo 2026'], [
            'created_by' => $adminId,
            'approved_by' => $adminId,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'payroll_period_id' => 1,
            'total_earned' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'total_employer_cost' => 0,
            'status' => 'approved',
            'notes' => 'Liquidacion demo para validar flujo de nomina.',
        ], $now);

        $totals = ['earned' => 0, 'deductions' => 0, 'net' => 0, 'employer' => 0];

        foreach ($employees as $employee) {
            $employeeId = $this->uuidRecord('employees', ['identification_number' => $employee['doc']], [
                'user_id' => null,
                'document_type' => 'CC',
                'dv' => null,
                'first_name' => $employee['first'],
                'middle_name' => $employee['middle'],
                'last_name' => $employee['last'],
                'second_lastname' => $employee['second'],
                'email' => $employee['email'],
                'phone' => $employee['phone'],
                'address' => 'Direccion residencial demo Medellin',
                'city' => 'Medellin',
                'department' => 'Antioquia',
                'birthdate' => '1988-04-12',
                'blood_type' => 'O+',
                'gender' => 1,
                'marital_status_id' => 2,
                'emergency_contact' => 'Contacto familiar',
                'emergency_phone' => '3001234567',
                'state' => true,
            ], $now);

            $contractId = $this->uuidRecord('employee_contracts', ['contract_number' => 'CT-' . $employee['doc']], [
                'employee_id' => $employeeId,
                'created_by' => $adminId,
                'finished_by' => null,
                'type_contract_id' => 2,
                'type_worker_id' => 1,
                'payroll_period_id' => 1,
                'job_title' => $employee['job'],
                'cost_center' => str_contains($employee['job'], 'bodega') ? 'Logistica' : 'Comercial',
                'arl_risk_class' => $employee['risk'],
                'salary' => $employee['salary'],
                'is_comprehensive_salary' => false,
                'has_transport_allowance' => $employee['salary'] <= 2600000,
                'voluntary_health_amount' => 0,
                'voluntary_pension_amount' => 0,
                'eps_name' => 'Sura EPS',
                'afp_name' => 'Proteccion',
                'arl_name' => 'ARL Sura',
                'ccf_name' => 'Comfama',
                'has_income_tax_withholding' => $employee['salary'] > 3000000,
                'income_tax_withholding_pct' => $employee['salary'] > 3000000 ? 2.5 : 0,
                'start_date' => '2025-02-01',
                'end_date' => null,
                'trial_end_date' => '2025-04-01',
                'state' => true,
            ], $now);

            $transport = $employee['salary'] <= 2600000 ? 200000 : 0;
            $commission = str_contains($employee['job'], 'comercial') ? 420000 : 0;
            $earned = $employee['salary'] + $transport + $commission;
            $health = round($employee['salary'] * 0.04, 2);
            $pension = round($employee['salary'] * 0.04, 2);
            $withholding = $employee['salary'] > 3000000 ? round($employee['salary'] * 0.025, 2) : 0;
            $deductions = $health + $pension + $withholding;
            $net = $earned - $deductions;
            $employer = $employee['salary'] + round($employee['salary'] * 0.085, 2) + round($employee['salary'] * 0.12, 2) + round($employee['salary'] * 0.04, 2);

            $runEmployeeId = $this->uuidRecord('payroll_run_employees', ['payroll_run_id' => $runId, 'employee_id' => $employeeId], [
                'contract_id' => $contractId,
                'worked_days' => 30,
                'salary' => $employee['salary'],
                'is_comprehensive_salary' => false,
                'basic_salary' => $employee['salary'],
                'transport_allowance' => $transport,
                'overtime_amount' => 0,
                'commissions' => $commission,
                'bonuses' => 0,
                'vacation_amount' => 0,
                'prima_amount' => 0,
                'severance_amount' => 0,
                'severance_interests' => 0,
                'disability_amount' => 0,
                'other_income' => 0,
                'total_earned' => $earned,
                'health_employee' => $health,
                'pension_employee' => $pension,
                'solidarity_fund' => 0,
                'income_tax_withholding' => $withholding,
                'voluntary_health_deduction' => 0,
                'voluntary_pension_deduction' => 0,
                'loans_deduction' => 0,
                'other_deductions' => 0,
                'total_deductions' => $deductions,
                'net_pay' => $net,
                'health_employer' => round($employee['salary'] * 0.085, 2),
                'pension_employer' => round($employee['salary'] * 0.12, 2),
                'arl_employer' => round($employee['salary'] * 0.00522, 2),
                'ccf_employer' => round($employee['salary'] * 0.04, 2),
                'sena_employer' => 0,
                'icbf_employer' => 0,
                'total_employer_cost' => $employer,
                'novelties_detail' => json_encode(['demo' => true, 'commission' => $commission]),
            ], $now);

            if ($commission > 0) {
                $this->uuidRecord('payroll_novelties', ['employee_id' => $employeeId, 'type' => 'commission', 'date_from' => '2026-05-15'], [
                    'contract_id' => $contractId,
                    'payroll_run_employee_id' => $runEmployeeId,
                    'created_by' => $adminId,
                    'amount' => $commission,
                    'date_to' => '2026-05-15',
                    'description' => 'Comision demo por cumplimiento de meta mensual.',
                    'is_processed' => true,
                ], $now);
            }

            foreach (['prima' => 1, 'cesantias' => 1, 'vacaciones' => null] as $type => $semester) {
                $this->uuidRecord('payroll_social_benefits', ['employee_id' => $employeeId, 'contract_id' => $contractId, 'type' => $type, 'year' => 2026], [
                    'semester' => $semester,
                    'base_salary' => $employee['salary'],
                    'days_worked' => 150,
                    'amount' => round($employee['salary'] * 150 / 360, 2),
                    'paid_amount' => 0,
                    'pay_date' => null,
                    'is_paid' => false,
                ], $now);
            }

            $totals['earned'] += $earned;
            $totals['deductions'] += $deductions;
            $totals['net'] += $net;
            $totals['employer'] += $employer;
        }

        DB::table('payroll_runs')->where('id', $runId)->update([
            'total_earned' => $totals['earned'],
            'total_deductions' => $totals['deductions'],
            'total_net' => $totals['net'],
            'total_employer_cost' => $totals['employer'],
            'updated_at' => $now,
        ]);
    }

    private function seedOperationalExtras(array $documents, string $adminId, $now): void
    {
        $this->uuidRecord('temporary_operations', ['type_document_operation_id' => 1, 'user_id' => $adminId], [
            'data' => json_encode([
                'customer' => 'Hoteles La Sierra S.A.S.',
                'items' => [['code' => 'REV-MET-30X60-BL', 'quantity' => 85]],
                'note' => 'Borrador demo de cotizacion convertida a factura.',
            ]),
            'state' => 0,
        ], $now, withSecondaryUuid: true);

        $this->uuidRecord('system_notifications', ['type' => 'inventory', 'title' => 'Inventario bajo en Porcelanato Calacatta'], [
            'message' => 'La bodega de exhibicion Medellin esta por debajo del punto minimo sugerido.',
            'read_at' => null,
        ], $now);

        $this->uuidRecord('document_transactions_id',
            ['document_id' => $documents['fev']],
            ['transaction_uuid' => 'TX-DEMO-FEV-1012', 'state' => true, 'issue_date' => '2026-05-17 10:31:00'],
            $now
        );
    }

    private function createDocument(string $internalCode, array $lines, array $items, array $header, $now): string
    {
        $subtotal = 0;
        $discount = 0;
        $tax = 0;

        foreach ($lines as $line) {
            $lineBase = $line['qty'] * $line['price'];
            $lineDiscount = round($lineBase * (($line['discount'] ?? 0) / 100), 2);
            $lineTaxable = $lineBase - $lineDiscount;
            $subtotal += $lineTaxable;
            $discount += $lineDiscount;
            $tax += round($lineTaxable * 0.19, 2);
        }

        $total = $subtotal + $tax;
        $balance = round($total * ($header['balance_factor'] ?? 0), 2);
        $documentId = $this->uuidRecord('documents', ['internal_code' => $internalCode], [
            'user_id' => $header['user_id'],
            'third_party_id' => $header['third_party_id'],
            'seller_id' => $header['seller_id'],
            'reference_id' => $header['reference_id'] ?? null,
            'type_document_id' => $header['type_document_id'],
            'type_document_operation_id' => $header['type_document_operation_id'],
            'resolution_id' => $header['resolution_id'],
            'prefix' => $header['prefix'],
            'number' => $header['number'],
            'currency_id' => 35,
            'subtotal' => $subtotal,
            'total_discount' => $discount,
            'total_tax' => $tax,
            'total' => $total,
            'balance' => $balance,
            'payment_forms' => json_encode([['payment_form_id' => $header['paid'] ? 1 : 2, 'payment_method_id' => $header['paid'] ? 10 : 30, 'amount' => $total]]),
            'taxes' => json_encode([['tax_id' => 1, 'percent' => 19, 'amount' => $tax]]),
            'issue_date' => $header['issue_date'],
            'paid' => $header['paid'],
            'electronic' => $header['electronic'],
            'accounted' => $header['accounted'],
            'annulled' => false,
            'cufe' => $header['electronic'] ? 'CUFE-DEMO-' . $internalCode : null,
            'qr_code' => $header['electronic'] ? 'https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=CUFE-DEMO-' . $internalCode : null,
            'cashier_shift' => ($header['pos_terminal_id'] ?? null) ? 'shift-demo-20260520-001' : null,
            'dian_validation_date_time' => $header['electronic'] ? json_encode(['date' => $header['issue_date'], 'time' => '10:35:00']) : null,
            'pos_terminal_id' => $header['pos_terminal_id'] ?? null,
            'dian_status' => $header['electronic'] ? 'accepted' : 'pending',
            'dian_error' => null,
            'dian_sent_at' => $header['electronic'] ? $header['issue_date'] . ' 10:35:00' : null,
            'dian_attempts' => $header['electronic'] ? 1 : 0,
        ], $now, withSecondaryUuid: true);

        foreach ($lines as $line) {
            $itemId = $items[$line['item']];
            $lineBase = $line['qty'] * $line['price'];
            $lineDiscount = round($lineBase * (($line['discount'] ?? 0) / 100), 2);
            $taxable = $lineBase - $lineDiscount;
            $taxAmount = round($taxable * 0.19, 2);

            DB::table('documents_details')->updateOrInsert(
                ['document_id' => $documentId, 'item_id' => $itemId],
                [
                    'amount' => $line['qty'],
                    'cost_value' => 0,
                    'sale_price' => $line['price'],
                    'discount' => $line['discount'] ?? 0,
                    'taxable_amount' => $taxable,
                    'tax_amount' => $taxAmount,
                    'taxes' => json_encode([['tax_id' => 1, 'percent' => 19, 'amount' => $taxAmount]]),
                    'unit_measure_id' => $line['unit'] ?? 70,
                    'warehouse_out' => $header['warehouse_out'] ?? null,
                    'warehouse_in' => $header['warehouse_in'] ?? null,
                    'movement_type' => $header['movement_type'] ?? 'OUT',
                    'annulled' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('item_stocktakings')->updateOrInsert(
                ['document_id' => $documentId, 'item_id' => $itemId],
                [
                    'inventory_concept_id' => ($header['movement_type'] ?? 'OUT') === 'IN' ? 3 : 1,
                    'input_quantity' => ($header['movement_type'] ?? 'OUT') === 'IN' ? $line['qty'] : 0,
                    'output_quantity' => ($header['movement_type'] ?? 'OUT') === 'IN' ? 0 : $line['qty'],
                    'purchase_price' => 0,
                    'new_average' => 0,
                    'warehouse_id' => $header['warehouse_out'] ?? $header['warehouse_in'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        DB::table('document_payment_methods')->updateOrInsert(
            ['document_id' => $documentId, 'payment_method_id' => $header['paid'] ? 10 : 30],
            ['amount' => $total - $balance, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('document_histories')->updateOrInsert(
            ['document_id' => $documentId, 'history' => 'Documento demo creado para validar flujo comercial.'],
            ['user_id' => $header['user_id'], 'history_issue_date' => $header['issue_date'] . ' 10:00:00', 'notes' => 'Seed demo ceramicas', 'created_at' => $now, 'updated_at' => $now]
        );

        return $documentId;
    }

    private function uuidRecord(string $table, array $where, array $values, $now, bool $withSecondaryUuid = false): string
    {
        $existing = DB::table($table)->where($where)->first();
        $payload = $this->filterColumns($table, $values);
        $payload['updated_at'] = $now;

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);
            return $existing->id;
        }

        $id = (string) Str::uuid();
        $insert = array_merge($where, $payload, [
            'id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($withSecondaryUuid && Schema::hasColumn($table, 'uuid') && ! isset($insert['uuid'])) {
            $insert['uuid'] = (string) Str::uuid();
        }

        DB::table($table)->insert($this->filterColumns($table, $insert));

        return $id;
    }

    private function filterColumns(string $table, array $values): array
    {
        return array_filter(
            $values,
            fn ($key) => Schema::hasColumn($table, $key),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function municipalityId(string $code, int $fallback): int
    {
        return (int) (DB::table('municipalities')->where('code', $code)->value('id') ?: $fallback);
    }
}
