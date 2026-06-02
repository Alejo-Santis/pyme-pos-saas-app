<?php

namespace App\Modules\Cash\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    use HasUuids;

    protected $table = 'bank_reconciliation_lines';

    protected $fillable = [
        'reconciliation_id', 'bank_account_movement_id',
        'movement_date', 'description', 'amount', 'source', 'matched',
    ];

    protected $casts = [
        'movement_date' => 'date',
        'amount'        => 'decimal:4',
        'matched'       => 'boolean',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function bankAccountMovement(): BelongsTo
    {
        return $this->belongsTo(BankAccountMovement::class);
    }
}
