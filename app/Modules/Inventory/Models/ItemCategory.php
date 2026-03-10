<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
    ];

    protected $casts = [
        'parent_id' => 'string',
    ];

    // Categoría padre (auto-referencia, nullable)
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    // Subcategorías hijas
    public function children(): HasMany
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    // Ítems que pertenecen a esta categoría
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_category_id');
    }
}
