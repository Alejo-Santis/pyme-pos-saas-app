<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPaymentMethod extends Model
{
    protected $table = 'document_payment_methods';

    protected $fillable = [
        'document_id',
        'payment_method_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
