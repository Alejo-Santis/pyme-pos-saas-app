<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'internal_code',
        'name',
        'type',
        'account_bank_number',
        'bank_id',
        'currency_id',
        'balance',
        'has_gmf',
        'state',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'has_gmf' => 'boolean',
        'state'   => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BankAccountMovement::class);
    }

    // ─── Balance calculado ────────────────────────────────────────────────────

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

    // ─── Código interno ───────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->internal_code)) {
                $last = self::withTrashed()
                    ->where('internal_code', 'like', 'ACC-%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = $last ? ((int) substr($last, 4)) + 1 : 1;
                $model->internal_code = 'ACC-' . str_pad($next, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}
