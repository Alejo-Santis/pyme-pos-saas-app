<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impuesto asignado a un artículo.
 * Tabla: item_taxes — id es bigIncrements, NO uuid.
 */
class ItemTax extends Model
{
    protected $table = 'item_taxes';

    protected $fillable = [
        'item_id',
        'tax_id',
        'application',
        'percent',
        'amount',
    ];

    protected $casts = [
        'percent' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
