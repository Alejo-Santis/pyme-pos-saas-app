<?php

namespace App\Modules\Cash\Models;

use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentReceiptDetail extends Model
{
    use HasUuids;

    protected $table = 'payment_receipts_details';

    protected $fillable = [
        'uuid',
        'payment_receipt_id',
        'document_id',
        'rate_holding_tax_id',
        'transaction_reference',
        'withholdings_tax',
        'quantity',
        'payment_form_id',
        'currency_id',
        'amount',
        'refunded_amount',
    ];

    protected $casts = [
        'withholdings_tax' => 'decimal:4',
        'quantity' => 'decimal:4',
        'amount' => 'decimal:4',
        'refunded_amount' => 'decimal:4',
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
