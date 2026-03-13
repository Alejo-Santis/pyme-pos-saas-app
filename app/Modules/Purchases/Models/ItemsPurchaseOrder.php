<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemsPurchaseOrder extends Model
{
    protected $table = 'items_purchase_orders';

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'invoice_quantity',
        'average_cost',
        'tax',
        'line_extension_amount',
    ];

    protected $casts = [
        'invoice_quantity'      => 'decimal:4',
        'average_cost'          => 'decimal:4',
        'line_extension_amount' => 'decimal:4',
        'tax'                   => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
