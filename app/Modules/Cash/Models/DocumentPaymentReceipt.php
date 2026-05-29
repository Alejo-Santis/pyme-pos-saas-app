<?php

namespace App\Modules\Cash\Models;

use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentPaymentReceipt extends Model
{
    use HasUuids;

    protected $table = 'document_payment_receipts';

    protected $fillable = [
        'uuid',
        'payment_receipt_id',
        'document_id',
        'payment_receipt_detail_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
