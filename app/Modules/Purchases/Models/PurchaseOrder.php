<?php

namespace App\Modules\Purchases\Models;

use App\Modules\Core\Models\ThirdParty;
use App\Modules\Invoice\Models\Document;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'third_party_id',
        'user_id',
        'approver_user_id',
        'document_id',
        'internal_code',
        'reference',
        'amount',
        'issue_date',
        'notes',
        'status',
        'approved',
        'annulled',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'amount'      => 'decimal:4',
        'approved'    => 'boolean',
        'annulled'    => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemsPurchaseOrder::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PurchaseOrderHistory::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('annulled', false);
    }

    public function scopePending($q)
    {
        return $q->whereIn('status', ['draft', 'pending', 'approved', 'partial']);
    }

    // ─── Código interno ───────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->internal_code)) {
                $last = self::withTrashed()
                    ->where('internal_code', 'like', 'OC%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = $last ? ((int) substr($last, 2)) + 1 : 1;
                $model->internal_code = 'OC' . str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
