<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    use HasUuids;

    protected $table = 'budget_lines';

    protected $fillable = [
        'budget_id', 'account_code', 'account_name', 'month', 'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'month'  => 'integer',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
