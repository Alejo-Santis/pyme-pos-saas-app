<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\PartyLinkage;
use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\ThirdParty;
use Illuminate\Support\Str;

/**
 * Siembra los datos mínimos necesarios que todo tenant nuevo debe tener:
 *   - Resoluciones por defecto (una por cada tipo de documento interno)
 *   - Tercero "propia empresa" + tercero "Consumidor Final"
 *
 * Adaptado de xedoc-laravel-svelte / ToolTrait::setDefaultResolutions y
 * setDefaultThirds, con los ajustes del nuevo schema (UUID, sin company_id
 * explícito en tabla tenant, current_number = from - 1).
 */
class DefaultsService
{
    /**
     * Crea todas las resoluciones predeterminadas para la empresa dada.
     * Si ya existen (por prefix + type_document_operation_id) hace updateOrCreate.
     *
     * NOTA sobre current_number:
     *   current_number = from - 1  →  el primer número emitido será exactamente "from".
     *   InvoiceService hace: $next = current_number + 1
     */
    public function seedResolutions(Company $company): void
    {
        $defaults = $this->defaultResolutions($company->id);

        foreach ($defaults as $data) {
            Resolution::updateOrCreate(
                [
                    'company_id'                 => $company->id,
                    'type_document_operation_id' => $data['type_document_operation_id'],
                    'prefix'                     => $data['prefix'],
                ],
                [
                    'uuid'                        => (string) Str::uuid(),
                    'type_document_id'            => $data['type_document_id'],
                    'resolution'                  => $data['resolution'],
                    'resolution_date'             => $data['resolution_date'],
                    'technical_key'               => $data['technical_key'],
                    'from'                        => $data['from'],
                    'to'                          => $data['to'],
                    'current_number'              => $data['from'] - 1,  // primer número emitido = from
                    'date_from'                   => $data['date_from'],
                    'date_to'                     => $data['date_to'],
                    'is_active'                   => true,
                ]
            );
        }
    }

    /**
     * Crea los dos terceros mínimos necesarios:
     *   1. La propia empresa (para documentos donde la empresa actúa como tercero)
     *   2. "CONSUMIDOR FINAL" (222222222222) — cliente por defecto en POS y FEV
     */
    public function seedThirds(Company $company): void
    {
        // 1. Propia empresa como tercero
        $own = ThirdParty::firstOrCreate(
            ['identification_number' => $company->identification_number],
            [
                'type_document_identification_id' => $company->type_document_identification_id ?? 6,
                'dv'                              => $company->dv,
                'type_organization_id'            => $company->type_organization_id,
                'type_regime_id'                  => $company->type_regime_id,
                'type_liability_id'               => $company->type_liability_id,
                'type_third_id'                   => 5, // Otro — no es cliente ni proveedor de sí misma
                'name'                            => $company->business_name,
                'email'                           => $company->email,
                'phone'                           => $company->phone,
                'address'                         => $company->address,
                'municipality_id'                 => $company->municipality_id,
                'country_id'                      => $company->country_id,
                'is_active'                       => true,
            ]
        );

        $own->linkage()->updateOrCreate(
            ['third_party_id' => $own->id],
            ['customer' => true, 'provider' => true, 'other' => true]
        );

        // 2. Consumidor Final (NIT especial DIAN: 222222222222)
        $consumidorFinal = ThirdParty::firstOrCreate(
            ['identification_number' => '222222222222'],
            [
                'type_document_identification_id' => 13,   // NIT
                'type_organization_id'            => 1,    // Persona Natural
                'type_regime_id'                  => 2,    // No responsable de IVA
                'type_liability_id'               => 7,    // No aplica - Otros (R-99-PN)
                'type_third_id'                   => 1,    // Cliente
                'name'                            => 'CONSUMIDOR FINAL',
                'address'                         => $company->address ?? 'CR 00 00 00',
                'municipality_id'                 => $company->municipality_id,
                'country_id'                      => $company->country_id ?? 1,
                'is_active'                       => true,
            ]
        );

        $consumidorFinal->linkage()->updateOrCreate(
            ['third_party_id' => $consumidorFinal->id],
            ['customer' => true, 'provider' => false, 'other' => true]
        );
    }

    // ── Catálogo de resoluciones predeterminadas ───────────────────────────

    private function defaultResolutions(string $companyId): array
    {
        // Prefijos y operation IDs basados en xedoc ToolTrait::setDefaultResolutions
        // La resolución SETP es la de habilitación DIAN para FEV (type_document_operation_id=1)
        return [
            // ─── Factura de Venta Electrónica (DIAN habilitación) ─────────
            [
                'type_document_id'            => 1,
                'type_document_operation_id'  => 1,
                'resolution'                  => '18760000001',
                'prefix'                      => 'SETP',
                'resolution_date'             => '2019-01-19',
                'technical_key'               => 'fc8eac422eba16e22ffd8c6f94b3f40a6e38162c',
                'from'                        => 990000000,
                'to'                          => 995000000,
                'date_from'                   => '2019-01-19',
                'date_to'                     => '2030-01-19',
            ],
            // ─── Nota Crédito ─────────────────────────────────────────────
            [
                'type_document_id'            => 4,
                'type_document_operation_id'  => 3,
                'resolution'                  => null,
                'prefix'                      => 'NC',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Nota Débito ──────────────────────────────────────────────
            [
                'type_document_id'            => 5,
                'type_document_operation_id'  => 4,
                'resolution'                  => null,
                'prefix'                      => 'ND',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Factura POS ──────────────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 14,
                'resolution'                  => null,
                'prefix'                      => 'FC',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Documento Soporte de Compra ──────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 5,
                'resolution'                  => null,
                'prefix'                      => 'DS',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── NC Documento Soporte ─────────────────────────────────────
            [
                'type_document_id'            => 13,
                'type_document_operation_id'  => 6,
                'resolution'                  => null,
                'prefix'                      => 'NDS',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Ajuste de inventario ─────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 9,
                'resolution'                  => null,
                'prefix'                      => 'AJ',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Traslado de bodega ───────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 10,
                'resolution'                  => null,
                'prefix'                      => 'TA',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Nota crédito compras ─────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 11,
                'resolution'                  => null,
                'prefix'                      => 'NCO',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Nota ingreso ─────────────────────────────────────────────
            [
                'type_document_id'            => 9,
                'type_document_operation_id'  => 12,
                'resolution'                  => null,
                'prefix'                      => 'NI',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Nota ajuste ──────────────────────────────────────────────
            [
                'type_document_id'            => 10,
                'type_document_operation_id'  => 13,
                'resolution'                  => null,
                'prefix'                      => 'NA',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Cotización ───────────────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 8,
                'resolution'                  => null,
                'prefix'                      => 'COT',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Recibo de caja ───────────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 15,
                'resolution'                  => null,
                'prefix'                      => 'CI',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Salida de inventario ─────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 19,
                'resolution'                  => null,
                'prefix'                      => 'SI',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Remisión / traslado externo ──────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 22,
                'resolution'                  => null,
                'prefix'                      => 'RT',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
            // ─── Cierre de caja ───────────────────────────────────────────
            [
                'type_document_id'            => null,
                'type_document_operation_id'  => 20,
                'resolution'                  => null,
                'prefix'                      => 'CE',
                'resolution_date'             => null,
                'technical_key'               => null,
                'from'                        => 1,
                'to'                          => 999999,
                'date_from'                   => null,
                'date_to'                     => null,
            ],
        ];
    }
}
