<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    use HasUuids;

    protected $table = 'budgets';

    protected $fillable = ['name', 'year', 'status', 'notes'];

    protected $casts = ['year' => 'integer'];

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CLOSED   = 'closed';

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
