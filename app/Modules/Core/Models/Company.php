<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Empresa del tenant — datos tributarios DIAN y de contacto.
 * Vive en el schema del tenant (no en public).
 * Cada tenant tiene normalmente una empresa principal.
 */
class Company extends Model
{
    use HasUuids;

    protected $fillable = [
        'identification_number',
        'dv',
        'business_name',
        'trade_name',
        'type_document_identification_id',
        'type_organization_id',
        'type_regime_id',
        'type_liability_id',
        'country_id',
        'municipality_id',
        'email',
        'phone',
        'address',
        'electronic_documents',
        'use_price_list',
        'prices_with_taxes_included',
        'use_aiu',
        'use_ibua',
        'use_icui',
        'type_environment_id',
        'dian_software_id',
        'dian_software_security_code',
        'dian_test_set_id',
        'dian_provider',
        'api_path_fe',
        'api_token_fe',
        'logo_path',
        'is_active',
    ];

    protected $casts = [
        'electronic_documents'          => 'boolean',
        'use_price_list'                => 'boolean',
        'prices_with_taxes_included'    => 'boolean',
        'use_aiu'                       => 'boolean',
        'use_ibua'                      => 'boolean',
        'use_icui'                      => 'boolean',
        'is_active'                     => 'boolean',
    ];


}
