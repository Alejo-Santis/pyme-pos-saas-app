<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolución de numeración DIAN por empresa.
 * Cada empresa puede tener varias resoluciones (FEV, NC, POS...).
 */
class Resolution extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid',
        'type_document_id',
        'type_document_operation_id',
        'resolution',
        'resolution_date',
        'technical_key',
        'prefix',
        'from',
        'to',
        'current_number',
        'date_from',
        'date_to',
        'is_active',
    ];

    protected $casts = [
        'resolution_date' => 'date',
        'date_from'       => 'date',
        'date_to'         => 'date',
        'is_active'       => 'boolean',
    ];

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
