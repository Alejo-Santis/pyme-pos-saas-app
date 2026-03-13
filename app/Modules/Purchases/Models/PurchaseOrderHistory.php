<?php

namespace App\Modules\Purchases\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderHistory extends Model
{
    protected $table = 'purchase_order_histories';

    protected $fillable = [
        'purchase_order_id',
        'user_id',
        'history_issue_date',
        'notes',
        'history',
    ];

    protected $casts = [
        'history_issue_date' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
