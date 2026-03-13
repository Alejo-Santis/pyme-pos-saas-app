<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBox extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'cash_boxes';

    protected $fillable = [
        'internal_code',
        'name',
        'is_main',
        'state',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'state'   => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    // ─── Helpers de balance ───────────────────────────────────────────────────

    /**
     * Balance actual (débitos - créditos).
     */
    public function getCurrentBalance(?string $start = null, ?string $end = null): float
    {
        $q = $this->movements()->where('state', true);

        if ($start) $q->where('issue_date', '>=', $start);
        if ($end)   $q->where('issue_date', '<=', $end);

        $result = $q->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();

        return round(
            ((float) ($result->total_debit ?? 0)) - ((float) ($result->total_credit ?? 0)),
            2
        );
    }

    /**
     * Retorna la caja marcada como principal.
     */
    public static function getMain(): ?self
    {
        return self::where('is_main', true)->where('state', true)->first();
    }

    // ─── Código interno ───────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->internal_code)) {
                $last = self::withTrashed()
                    ->where('internal_code', 'like', 'CB%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = $last ? ((int) substr($last, 2)) + 1 : 1;
                $model->internal_code = 'CB' . str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        });
    }
}
