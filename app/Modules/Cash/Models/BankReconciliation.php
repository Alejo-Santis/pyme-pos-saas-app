<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasUuids;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'bank_account_id', 'period', 'statement_date',
        'statement_balance', 'book_balance', 'difference',
        'status', 'reconciled_by', 'reconciled_at', 'notes',
    ];

    protected $casts = [
        'statement_date'    => 'date',
        'statement_balance' => 'decimal:4',
        'book_balance'      => 'decimal:4',
        'difference'        => 'decimal:4',
        'reconciled_at'     => 'datetime',
    ];

    public const STATUS_OPEN        = 'open';
    public const STATUS_RECONCILED  = 'reconciled';

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class, 'reconciliation_id');
    }

    public function isReconciled(): bool
    {
        return $this->status === self::STATUS_RECONCILED;
    }
}
