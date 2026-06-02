<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLotMovement extends Model
{
    use HasUuids;

    protected $table = 'item_lot_movements';

    protected $fillable = [
        'item_lot_id', 'document_id', 'movement_type', 'quantity',
    ];

    protected $casts = ['quantity' => 'decimal:4'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ItemLot::class, 'item_lot_id');
    }
}
