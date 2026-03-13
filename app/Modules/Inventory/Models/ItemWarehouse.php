<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de un artículo en una bodega específica.
 * Tabla: item_warehouse (pivot enriquecido).
 * Se actualiza con cada movimiento de inventario (venta, compra, traslado).
 */
class ItemWarehouse extends Model
{
    use HasUuids;

    protected $table = 'item_warehouse';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'stock',
        'average_cost',
        'state',
    ];

    protected $casts = [
        'stock'        => 'decimal:4',
        'average_cost' => 'decimal:4',
        'state'        => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
