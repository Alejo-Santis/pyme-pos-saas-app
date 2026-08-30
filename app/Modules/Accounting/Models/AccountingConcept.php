<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Conceptos contables configurables.
 * Mapean cada tipo de operación a una cuenta del PUC.
 *
 * Ejemplo:
 *   type_document_operation_id = 1 (Venta)
 *   concept_slug = 'VENTA_INGRESO'
 *   accountable_id = '4135' (código de cuenta PUC)
 */
class AccountingConcept extends Model
{
    use HasUuids;

    protected $table = 'accounting_concepts';

    protected $fillable = [
        'uuid',
        'internal_code',
        'name',
        'type_concept',
        'accountable_id',
        'accountable_type',
        'account_nature_id',
        'cost_center_id',
        'is_tax_concept',
    ];

    protected $casts = [
        'is_tax_concept' => 'boolean',
    ];

    /**
     * Busca el concepto para una operación y un slug dado.
     * Retorna el código de cuenta PUC configurado.
     */
    public static function getAccountCode(int $operationId, string $slug): ?string
    {
        $concept = static::where('type_concept', "{$operationId}_{$slug}")
            ->whereNotNull('accountable_id')
            ->first();

        return $concept?->accountable_id;
    }

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
