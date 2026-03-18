<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferItem extends Model
{
    protected $table = 'items_transfer';

    protected $fillable = [
        'transfer_id',
        'item_id',
        'quantity',
        'cost',
        'line_total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'cost'       => 'decimal:4',
        'line_total' => 'decimal:4',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class, 'transfer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
