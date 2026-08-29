<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Establishment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'business_name',
        'address',
        'municipality_id',
        'is_main',
        'sync_items_full',
    ];

    protected $casts = [
        'is_main'         => 'boolean',
        'sync_items_full' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
