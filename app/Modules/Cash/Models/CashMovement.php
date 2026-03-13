<?php

namespace App\Modules\Cash\Models;

use App\Modules\Core\Models\ThirdParty;
use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'cash_movements';

    protected $fillable = [
        'internal_code',
        'cash_box_id',
        'user_id',
        'third_party_id',
        'document_id',
        'movementable_id',
        'movementable_type',
        'debit',
        'credit',
        'issue_date',
        'description',
        'reference',
        'state',
    ];

    protected $casts = [
        'debit'      => 'decimal:2',
        'credit'     => 'decimal:2',
        'issue_date' => 'date',
        'state'      => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function cashBox(): BelongsTo
    {
        return $this->belongsTo(CashBox::class);
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function movementable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getMovementTypeAttribute(): string
    {
        if ($this->debit > 0 && $this->credit == 0) return 'entrada';
        if ($this->credit > 0 && $this->debit == 0) return 'salida';
        return 'neutro';
    }

    public function getTotalAmountAttribute(): float
    {
        return round((float) $this->debit - (float) $this->credit, 2);
    }

    // ─── Código interno ───────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->internal_code)) {
                $last = self::withTrashed()
                    ->where('internal_code', 'like', 'CM%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = $last ? ((int) substr($last, 2)) + 1 : 1;
                $model->internal_code = 'CM' . str_pad($next, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}
