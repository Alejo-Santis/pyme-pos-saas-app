<?php

namespace App\Modules\Cash\Models;

use App\Modules\Core\Models\ThirdParty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'banks';

    protected $fillable = [
        'internal_code',
        'name',
        'third_party_id',
        'default_bank_account_id',
        'state',
    ];

    protected $casts = [
        'state' => 'boolean',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'default_bank_account_id');
    }

    // ─── Código interno ───────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->internal_code)) {
                $last = self::withTrashed()
                    ->where('internal_code', 'like', 'BNK-%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = $last ? ((int) substr($last, 4)) + 1 : 1;
                $model->internal_code = 'BNK-' . str_pad($next, 6, '0', STR_PAD_LEFT);
            }
        });
    }
}
