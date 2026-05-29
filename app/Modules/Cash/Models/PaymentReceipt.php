<?php

namespace App\Modules\Cash\Models;

use App\Modules\Core\Models\ThirdParty;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentReceipt extends Model
{
    use HasUuids;

    protected $table = 'payment_receipts';

    protected $fillable = [
        'uuid',
        'internal_code',
        'type_document_operation_id',
        'user_id',
        'third_party_id',
        'income_and_expense_id',
        'resolution_id',
        'prefix',
        'number',
        'total_amount',
        'amount_received',
        'is_paid_credit',
        'payment_forms',
        'issue_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'amount_received' => 'decimal:4',
        'is_paid_credit' => 'boolean',
        'payment_forms' => 'array',
        'issue_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->internal_code)) {
                $next = (int) self::query()->where('internal_code', 'like', 'PG-%')->count() + 1;
                $model->internal_code = 'PG-' . str_pad((string) $next, 8, '0', STR_PAD_LEFT);
            }
        });
    }

    public function details(): HasMany
    {
        return $this->hasMany(PaymentReceiptDetail::class, 'payment_receipt_id');
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
