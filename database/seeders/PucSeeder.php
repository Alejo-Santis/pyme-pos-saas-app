<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el PUC Colombia con las cuentas operativas mínimas necesarias.
 * Cubre todas las cuentas usadas por el motor contable (AccountingEngineTrait)
 * más las cuentas de estructura para P&G y Balance General.
 *
 * Estructura: código → [nombre, clase, nivel, padre, naturaleza, permite_movimiento]
 * Naturaleza: D=Débito (saldo normal en débito), C=Crédito
 */
class PucSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->accounts();
        $now = now();

        foreach (array_chunk($accounts, 100) as $chunk) {
            $rows = [];
            foreach ($chunk as $row) {
                $rows[] = [
                    'code'            => $row[0],
                    'name'            => $row[1],
                    'class'           => $row[2],
                    'level'           => $row[3],
                    'parent_code'     => $row[4],
                    'nature'          => $row[5],
                    'allows_movement' => $row[6],
                    'state'           => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            DB::table('chart_of_accounts')->upsert($rows, ['code'], ['name', 'class', 'level', 'parent_code', 'nature', 'allows_movement']);
        }
    }

    /**
     * [código, nombre, clase, nivel, padre, naturaleza(D/C), permite_movimiento]
     */
    private function accounts(): array
    {
        return [
            // ── CLASES (nivel 1) ──────────────────────────────────────────────────
            ['1', 'ACTIVO',                                          1, 1, null, 'D', false],
            ['2', 'PASIVO',                                          2, 1, null, 'C', false],
            ['3', 'PATRIMONIO',                                      3, 1, null, 'C', false],
            ['4', 'INGRESOS',                                        4, 1, null, 'C', false],
            ['5', 'GASTOS',                                          5, 1, null, 'D', false],
            ['6', 'COSTO DE VENTAS Y PRESTACION DE SERVICIOS',       6, 1, null, 'D', false],

            // ── GRUPOS ACTIVO (nivel 2) ───────────────────────────────────────────
            ['11', 'EFECTIVO Y EQUIVALENTES AL EFECTIVO',            1, 2, '1', 'D', false],
            ['12', 'INVERSIONES E INSTRUMENTOS DERIVADOS',           1, 2, '1', 'D', false],
            ['13', 'DEUDORES COMERCIALES Y OTRAS CUENTAS POR COBRAR',1, 2, '1', 'D', false],
            ['14', 'INVENTARIOS',                                    1, 2, '1', 'D', false],
            ['15', 'PROPIEDADES PLANTA Y EQUIPO',                    1, 2, '1', 'D', false],
            ['16', 'INTANGIBLES',                                    1, 2, '1', 'D', false],
            ['17', 'DIFERIDOS',                                      1, 2, '1', 'D', false],

            // ── GRUPOS PASIVO (nivel 2) ───────────────────────────────────────────
            ['21', 'OBLIGACIONES FINANCIERAS',                       2, 2, '2', 'C', false],
            ['22', 'CUENTAS POR PAGAR',                              2, 2, '2', 'C', false],
            ['23', 'IMPUESTOS GRAVAMENES Y TASAS',                   2, 2, '2', 'C', false],
            ['24', 'CUENTAS POR PAGAR',                              2, 2, '2', 'C', false],
            ['25', 'OBLIGACIONES LABORALES',                         2, 2, '2', 'C', false],
            ['26', 'PASIVOS ESTIMADOS Y PROVISIONES',                2, 2, '2', 'C', false],
            ['27', 'DIFERIDOS',                                      2, 2, '2', 'C', false],
            ['29', 'OTROS PASIVOS',                                  2, 2, '2', 'C', false],

            // ── GRUPOS PATRIMONIO (nivel 2) ───────────────────────────────────────
            ['31', 'CAPITAL SOCIAL',                                 3, 2, '3', 'C', false],
            ['33', 'RESERVAS',                                       3, 2, '3', 'C', false],
            ['36', 'RESULTADOS DEL EJERCICIO',                       3, 2, '3', 'C', false],
            ['37', 'RESULTADOS DE EJERCICIOS ANTERIORES',            3, 2, '3', 'C', false],

            // ── GRUPOS INGRESOS (nivel 2) ─────────────────────────────────────────
            ['41', 'INGRESOS OPERACIONALES',                         4, 2, '4', 'C', false],
            ['42', 'INGRESOS NO OPERACIONALES',                      4, 2, '4', 'C', false],

            // ── GRUPOS COSTOS (nivel 2) ───────────────────────────────────────────
            ['61', 'COSTO DE VENTAS',                                6, 2, '6', 'D', false],

            // ── GRUPOS GASTOS (nivel 2) ───────────────────────────────────────────
            ['51', 'GASTOS OPERACIONALES DE ADMINISTRACION',         5, 2, '5', 'D', false],
            ['52', 'GASTOS OPERACIONALES DE VENTAS',                 5, 2, '5', 'D', false],
            ['53', 'GASTOS NO OPERACIONALES',                        5, 2, '5', 'D', false],

            // ── CUENTAS ACTIVO (nivel 3) ──────────────────────────────────────────
            ['1105', 'CAJA',                                         1, 3, '11', 'D', false],
            ['1110', 'BANCOS',                                       1, 3, '11', 'D', false],
            ['1305', 'CLIENTES',                                     1, 3, '13', 'D', false],
            ['1330', 'ANTICIPOS Y AVANCES',                          1, 3, '13', 'D', false],
            ['1435', 'MERCANCIAS NO FABRICADAS POR LA EMPRESA',      1, 3, '14', 'D', false],
            ['1524', 'EQUIPO DE OFICINA',                            1, 3, '15', 'D', false],
            ['1528', 'EQUIPO DE COMPUTACION Y COMUNICACION',         1, 3, '15', 'D', false],

            // ── CUENTAS PASIVO (nivel 3) ──────────────────────────────────────────
            ['2205', 'PROVEEDORES NACIONALES',                       2, 3, '22', 'C', false],
            ['2208', 'PROVEEDORES DEL EXTERIOR',                     2, 3, '22', 'C', false],
            ['2335', 'COSTOS Y GASTOS POR PAGAR',                    2, 3, '23', 'C', false],
            ['2365', 'RETENCION EN LA FUENTE',                       2, 3, '23', 'C', false],
            ['2367', 'IMPUESTO A LAS VENTAS RETENIDO',               2, 3, '23', 'C', false],
            ['2368', 'IMPUESTO DE INDUSTRIA Y COMERCIO RETENIDO',    2, 3, '23', 'C', false],
            ['2408', 'IVA POR PAGAR',                                2, 3, '24', 'C', false],
            ['2505', 'SALARIOS POR PAGAR',                           2, 3, '25', 'C', false],

            // ── CUENTAS INGRESOS (nivel 3) ────────────────────────────────────────
            ['4135', 'COMERCIO AL POR MAYOR Y AL POR MENOR',         4, 3, '41', 'C', false],
            ['4210', 'FINANCIEROS',                                  4, 3, '42', 'C', false],
            ['4215', 'DESCUENTOS COMERCIALES CONDICIONADOS',         4, 3, '42', 'C', false],

            // ── CUENTAS COSTOS (nivel 3) ──────────────────────────────────────────
            ['6135', 'COMERCIO AL POR MAYOR Y AL POR MENOR',         6, 3, '61', 'D', false],

            // ── CUENTAS GASTOS (nivel 3) ──────────────────────────────────────────
            ['5105', 'GASTOS DE PERSONAL',                           5, 3, '51', 'D', false],
            ['5110', 'HONORARIOS',                                   5, 3, '51', 'D', false],
            ['5115', 'IMPUESTOS',                                    5, 3, '51', 'D', false],
            ['5120', 'ARRENDAMIENTOS',                               5, 3, '51', 'D', false],
            ['5135', 'SERVICIOS',                                    5, 3, '51', 'D', false],
            ['5145', 'MANTENIMIENTO Y REPARACIONES',                 5, 3, '51', 'D', false],
            ['5195', 'DIVERSOS',                                     5, 3, '51', 'D', false],

            // ── SUBCUENTAS CRÍTICAS (nivel 4) ─────────────────────────────────────
            ['110505', 'CAJA GENERAL',                               1, 4, '1105', 'D', false],
            ['110510', 'CAJAS MENORES',                              1, 4, '1105', 'D', false],
            ['111005', 'BANCO DE BOGOTA',                            1, 4, '1110', 'D', false],
            ['130505', 'CLIENTES DEL PAIS',                          1, 4, '1305', 'D', false],
            ['143505', 'MERCANCIAS EN EXISTENCIA',                   1, 4, '1435', 'D', false],
            ['220505', 'PROVEEDORES NACIONALES',                     2, 4, '2205', 'C', false],
            ['240805', 'IVA GENERADO',                               2, 4, '2408', 'C', false],
            ['240810', 'IVA DESCONTABLE',                            2, 4, '2408', 'D', false],
            ['413510', 'VENTAS',                                     4, 4, '4135', 'C', false],
            ['613510', 'COSTO DE VENTAS',                            6, 4, '6135', 'D', false],

            // ── AUXILIARES (nivel 5) — SOLO ÉSTAS ADMITEN MOVIMIENTOS ─────────────
            // Caja / efectivo
            ['11050501', 'Caja principal',                           1, 5, '110505', 'D', true],
            ['11050502', 'Caja menor',                               1, 5, '110505', 'D', true],
            // Bancos
            ['11100501', 'Cuenta corriente principal',               1, 5, '111005', 'D', true],
            // Clientes
            ['13050501', 'Clientes nacionales',                      1, 5, '130505', 'D', true],
            // Inventario
            ['14350101', 'Mercancías no fabricadas por la empresa',  1, 5, '143505', 'D', true],
            // Proveedores
            ['22050101', 'Proveedores nacionales',                   2, 5, '220505', 'C', true],
            // IVA generado / descontable
            ['24080101', 'IVA por pagar generado 19%',               2, 5, '240805', 'C', true],
            ['24080102', 'IVA por pagar generado 5%',                2, 5, '240805', 'C', true],
            ['24080501', 'IVA descontable 19%',                      2, 5, '240810', 'D', true],
            // Ingresos
            ['41351001', 'Ingresos por ventas nacionales',           4, 5, '413510', 'C', true],
            // Costos
            ['61351001', 'Costo de ventas mercancías',               6, 5, '613510', 'D', true],
            // Gastos admin
            ['51050501', 'Sueldos y salarios',                       5, 5, '5105', 'D', true],
            ['51100501', 'Honorarios a personas naturales',          5, 5, '5110', 'D', true],
            ['51950501', 'Gastos diversos',                          5, 5, '5195', 'D', true],
            // Cuenta genérica de error
            ['99999999', 'Cuenta no configurada (revisar)',          9, 5, null,    'D', true],
        ];
    }
}
