<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogos globales (schema public) — datos oficiales DIAN / Colombia.
 * Se ejecuta una sola vez al instalar el sistema.
 */
class GlobalCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ------------------------------------------------------------------ //
        // IDENTIFICACIÓN Y ORGANIZACIÓN
        // ------------------------------------------------------------------ //

        DB::table('type_organizations')->insert([
            ['id' => 1, 'name' => 'Persona Jurídica', 'code' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Persona Natural',  'code' => '2', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('type_document_identifications')->insert([
            ['id' => 1,  'name' => 'Registro civil',                                                'code' => '11', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 2,  'name' => 'Tarjeta de identidad',                                          'code' => '12', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 3,  'name' => 'Cédula de ciudadanía',                                          'code' => '13', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 4,  'name' => 'Tarjeta de extranjería',                                        'code' => '21', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 5,  'name' => 'Cédula de extranjería',                                         'code' => '22', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 6,  'name' => 'NIT',                                                           'code' => '31', 'code_agency' => '195', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7,  'name' => 'Pasaporte',                                                     'code' => '41', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 8,  'name' => 'Documento de identificación extranjero',                        'code' => '42', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 9,  'name' => 'Sin identificación del exterior o uso definido por la DIAN',    'code' => '43', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'Documento de identificación extranjero persona jurídica',       'code' => '44', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Carné diplomático',                                             'code' => '46', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'name' => 'PEP - Permiso Especial de Permanencia',                         'code' => '47', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'name' => 'PPT - Permiso por Protección Temporal',                         'code' => '48', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'name' => 'NUIP',                                                          'code' => '91', 'code_agency' => null,  'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('type_regimes')->insert([
            ['id' => 1, 'name' => 'Responsable de IVA',    'code' => 'O', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'No responsable de IVA', 'code' => 'P', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('type_liabilities')->insert([
            ['id' => 1, 'name' => 'Gran contribuyente',                                  'code' => 'O-07',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Autorretenedor',                                      'code' => 'O-09',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Gran contribuyente autorretenedor',                   'code' => 'O-13',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Informante de exógena',                               'code' => 'O-15',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Agente de retención en el impuesto sobre las ventas', 'code' => 'O-23',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Obligado a llevar contabilidad',                      'code' => 'O-24',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'No aplica - Otros',                                   'code' => 'R-99-PN', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — FORMAS Y MEDIOS DE PAGO
        // ------------------------------------------------------------------ //

        DB::table('payment_forms')->insert([
            ['id' => 1, 'name' => 'Contado', 'code' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Crédito',  'code' => '2', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — TIPOS DE OPERACIÓN
        // ------------------------------------------------------------------ //

        DB::table('type_operations')->insert([
            ['id' => 1, 'name' => 'AIU',                                  'code' => '09', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Estándar',                              'code' => '10', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Mandato',                               'code' => '11', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Transporte de carga',                   'code' => '12', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Cambiario',                             'code' => '13', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Factura electrónica de exportación',    'code' => '14', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — IDENTIFICACIÓN DE ÍTEMS Y PRECIOS DE REFERENCIA
        // ------------------------------------------------------------------ //

        DB::table('type_item_identifications')->insert([
            ['id' => 1, 'name' => 'Estándar de adopción del contribuyente',                       'code' => '001', 'code_agency' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'GTIN (Global Trade Item Number)',                              'code' => '010', 'code_agency' => '9',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Partida arancelaria',                                          'code' => '020', 'code_agency' => '6',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Código de Estándar de Adopción del Contribuyente (Importaciones)', 'code' => '040', 'code_agency' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Estándar de adopción del contribuyente (número de orden)',     'code' => '999', 'code_agency' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('reference_prices')->insert([
            ['id' => 1, 'name' => 'Precio de lista 1', 'code' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Precio de lista 2', 'code' => '2', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Precio de lista 3', 'code' => '3', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — TIPOS DE DOCUMENTO Y OPERACIONES DE DOCUMENTO
        // ------------------------------------------------------------------ //

        DB::table('type_documents')->insert([
            ['id' => 1, 'name' => 'Factura Electrónica de Venta',                     'code' => '01', 'cufe_algorithm' => 'SHA-256', 'prefix' => 'FEV', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Factura de Venta (No electrónica)',                'code' => '02', 'cufe_algorithm' => null,       'prefix' => 'FV',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Documento equivalente (tiquete de máquina)',       'code' => '03', 'cufe_algorithm' => null,       'prefix' => 'T',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Factura de contingencia - papel',                  'code' => '04', 'cufe_algorithm' => null,       'prefix' => null,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Documento soporte en adquisiciones a no obligados','code' => '05', 'cufe_algorithm' => 'SHA-256', 'prefix' => 'DS',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Nota de ajuste al documento soporte',              'code' => '06', 'cufe_algorithm' => 'SHA-256', 'prefix' => 'NDS', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Nota Crédito',                                     'code' => '91', 'cufe_algorithm' => 'SHA-384', 'prefix' => 'NC',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Nota Débito',                                      'code' => '92', 'cufe_algorithm' => 'SHA-384', 'prefix' => 'ND',  'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('type_document_operations')->insert([
            ['id' =>  1, 'uuid' => (string) Str::uuid(), 'code' => 'FEV', 'name' => 'Factura Electrónica de Venta',   'dian_name' => 'Invoice',    'prefix' => 'FEV', 'type_print' => 'invoice',             'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'uuid' => (string) Str::uuid(), 'code' => 'POS', 'name' => 'Factura POS',                    'dian_name' => null,         'prefix' => 'POS', 'type_print' => 'pos',                 'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'uuid' => (string) Str::uuid(), 'code' => 'CF',  'name' => 'Cotización / Pedido',            'dian_name' => null,         'prefix' => 'CF',  'type_print' => 'quotation',           'operation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'uuid' => (string) Str::uuid(), 'code' => 'REM', 'name' => 'Remisión',                       'dian_name' => null,         'prefix' => 'REM', 'type_print' => 'remission',           'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'uuid' => (string) Str::uuid(), 'code' => 'NC',  'name' => 'Nota Crédito',                   'dian_name' => 'CreditNote', 'prefix' => 'NC',  'type_print' => 'credit_note',         'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'uuid' => (string) Str::uuid(), 'code' => 'ND',  'name' => 'Nota Débito',                    'dian_name' => 'DebitNote',  'prefix' => 'ND',  'type_print' => 'debit_note',          'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'uuid' => (string) Str::uuid(), 'code' => 'OC',  'name' => 'Orden de Compra',                'dian_name' => null,         'prefix' => 'OC',  'type_print' => 'purchase_order',      'operation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'uuid' => (string) Str::uuid(), 'code' => 'CP',  'name' => 'Compra',                         'dian_name' => null,         'prefix' => 'CP',  'type_print' => 'purchase',            'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'uuid' => (string) Str::uuid(), 'code' => 'DEV', 'name' => 'Devolución en Compra',           'dian_name' => null,         'prefix' => 'DV',  'type_print' => 'purchase_return',     'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'uuid' => (string) Str::uuid(), 'code' => 'TRN', 'name' => 'Traslado entre Bodegas',         'dian_name' => null,         'prefix' => 'TR',  'type_print' => 'transfer',            'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'uuid' => (string) Str::uuid(), 'code' => 'AIE', 'name' => 'Ajuste de Inventario Entrada',   'dian_name' => null,         'prefix' => 'AIE', 'type_print' => 'inventory_adjustment', 'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'uuid' => (string) Str::uuid(), 'code' => 'AIS', 'name' => 'Ajuste de Inventario Salida',    'dian_name' => null,         'prefix' => 'AIS', 'type_print' => 'inventory_adjustment', 'operation' => true,  'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'uuid' => (string) Str::uuid(), 'code' => 'RC',  'name' => 'Recibo de Caja',                 'dian_name' => null,         'prefix' => 'RC',  'type_print' => 'cash_receipt',        'operation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'uuid' => (string) Str::uuid(), 'code' => 'PG',  'name' => 'Comprobante de Pago',            'dian_name' => null,         'prefix' => 'PG',  'type_print' => 'payment_receipt',     'operation' => false, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'uuid' => (string) Str::uuid(), 'code' => 'CC',  'name' => 'Comprobante Contable',           'dian_name' => null,         'prefix' => 'CC',  'type_print' => 'accounting_voucher',  'operation' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — RESPUESTAS DE DISCREPANCIA (NC / ND)
        // ------------------------------------------------------------------ //

        DB::table('credit_note_discrepancy_responses')->insert([
            ['id' => 1, 'name' => 'Devolución parcial de los bienes y/o no aceptación parcial del servicio', 'code' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Anulación de factura electrónica',                                         'code' => '2', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Rebaja o descuento parcial',                                               'code' => '3', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Ajuste de precio',                                                         'code' => '4', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Otros',                                                                    'code' => '5', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('debit_note_discrepancy_responses')->insert([
            ['id' => 1, 'name' => 'Intereses',        'code' => '1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Gastos por cobrar','code' => '2', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Cambio del valor', 'code' => '3', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Otros',            'code' => '4', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DIAN — EVENTOS
        // ------------------------------------------------------------------ //

        DB::table('type_events')->insert([
            ['id' => 1, 'name' => 'Acuse de recibo de Factura Electrónica de Venta',       'code' => '030', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Reclamo de la Factura Electrónica de Venta',            'code' => '031', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Recibo del bien y/o prestación del servicio',           'code' => '032', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Aceptación expresa de la Factura Electrónica de Venta', 'code' => '033', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Aceptación tácita de la Factura Electrónica de Venta',  'code' => '034', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Emisión de Factura Electrónica como Título Valor',      'code' => '045', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // DESCUENTOS PERMITIDOS DIAN
        // ------------------------------------------------------------------ //

        DB::table('discounts')->insert([
            ['id' => 1, 'name' => 'Descuento comercial',          'code' => '00', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Descuento por pago anticipado','code' => '01', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Otros descuentos',             'code' => '02', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // CONTABILIDAD
        // ------------------------------------------------------------------ //

        DB::table('accounting_natures')->insert([
            ['id' => 1, 'name' => 'Débito',   'key' => 'D', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Crédito',  'key' => 'C', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('type_operation_accounts')->insert([
            ['id' => 1, 'name' => 'Clase',     'description' => 'Nivel 1 del PUC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Grupo',     'description' => 'Nivel 2 del PUC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Cuenta',    'description' => 'Nivel 3 del PUC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Subcuenta', 'description' => 'Nivel 4 del PUC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Auxiliar',  'description' => 'Nivel 5 del PUC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Detalle',   'description' => 'Nivel 6 del PUC', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // TERCEROS Y POSICIONES
        // ------------------------------------------------------------------ //

        DB::table('type_thirds')->insert([
            ['id' => 1, 'name' => 'Cliente',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Proveedor', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Usuario',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Empleado',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Otro',      'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('management_positions')->insert([
            ['id' => 1, 'name' => 'Representante Legal', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Gerente General',     'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Director Financiero', 'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Director Comercial',  'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Contador',            'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Auxiliar Contable',   'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Vendedor',            'description' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Almacenista',         'description' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // ÍTEMS / PRODUCTOS
        // ------------------------------------------------------------------ //

        DB::table('item_clasification')->insert([
            ['id' => 1, 'name' => 'Producto', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Servicio', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Otro',     'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('item_tax_categories')->insert([
            ['id' => 1, 'name' => 'Gravado',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Excluido',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Exento',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'No aplica',  'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // CONCEPTOS DE INVENTARIO (dependen de type_document_operations)
        // ------------------------------------------------------------------ //

        DB::table('inventory_concepts')->insert([
            ['id' => 1, 'type_document_operation_id' =>  1, 'code' => 'V_SAL',  'name' => 'Salida por venta',                 'type' => 'output', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'type_document_operation_id' =>  4, 'code' => 'R_SAL',  'name' => 'Salida por remisión',              'type' => 'output', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'type_document_operation_id' =>  5, 'code' => 'NC_ENT', 'name' => 'Entrada por devolución en venta',  'type' => 'input',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'type_document_operation_id' =>  8, 'code' => 'C_ENT',  'name' => 'Entrada por compra',               'type' => 'input',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'type_document_operation_id' =>  9, 'code' => 'DC_SAL', 'name' => 'Salida por devolución en compra',  'type' => 'output', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'type_document_operation_id' => 10, 'code' => 'TR_SAL', 'name' => 'Salida por traslado',              'type' => 'output', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'type_document_operation_id' => 10, 'code' => 'TR_ENT', 'name' => 'Entrada por traslado',             'type' => 'input',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'type_document_operation_id' => 11, 'code' => 'AJ_ENT', 'name' => 'Ajuste de entrada de inventario',  'type' => 'input',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'type_document_operation_id' => 12, 'code' => 'AJ_SAL', 'name' => 'Ajuste de salida de inventario',   'type' => 'output', 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // RETENCIONES EN LA FUENTE (Colombia) — principales tarifas
        // ------------------------------------------------------------------ //

        DB::table('rate_holding_taxes')->insert([
            ['uuid' => (string) Str::uuid(), 'description' => 'Servicios en general 4%',                          'fee' => 4.0000,  'minimum_base_uvt' =>  4.0000, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Honorarios y comisiones - personas jurídicas 11%', 'fee' => 11.0000, 'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Honorarios - personas naturales 10%',              'fee' => 10.0000, 'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Compras en general 2.5%',                          'fee' => 2.5000,  'minimum_base_uvt' => 27.0000, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Arrendamientos bienes muebles 4%',                 'fee' => 4.0000,  'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Arrendamientos bienes inmuebles 3.5%',             'fee' => 3.5000,  'minimum_base_uvt' => 27.0000, 'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'ReteIVA 15% del IVA generado',                     'fee' => 15.0000, 'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'ReteICA - Comercio 0.4‰',                          'fee' => 0.4000,  'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'ReteICA - Servicios 0.69‰',                        'fee' => 0.6900,  'minimum_base_uvt' =>  null,   'created_at' => $now, 'updated_at' => $now],
            ['uuid' => (string) Str::uuid(), 'description' => 'Transporte nacional de carga 1%',                  'fee' => 1.0000,  'minimum_base_uvt' => 27.0000, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ------------------------------------------------------------------ //
        // RESET DE SECUENCIAS PostgreSQL
        // ------------------------------------------------------------------ //

        $tables = [
            'type_organizations', 'type_document_identifications', 'type_regimes', 'type_liabilities',
            'payment_forms', 'type_operations', 'type_item_identifications', 'reference_prices',
            'type_documents', 'type_document_operations',
            'credit_note_discrepancy_responses', 'debit_note_discrepancy_responses',
            'type_events', 'discounts', 'accounting_natures', 'type_operation_accounts',
            'type_thirds', 'management_positions', 'item_clasification', 'item_tax_categories',
            'inventory_concepts',
        ];

        foreach ($tables as $table) {
            DB::statement("SELECT setval('{$table}_id_seq', (SELECT MAX(id) FROM \"{$table}\"))");
        }
    }
}
