<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'items';

    protected $fillable = [
        'internal_code',
        'name',
        'short_name',
        'reference',
        'note',
        'type',
        'item_category_id',
        'clasification_id',
        'tax_category_id',
        'unit_measure_id',
        'group_id',
        'line_id',
        'last_purchase_price',
        'average_cost',
        'default_sale_price',
        'consumption_tax',
        'icui',
        'ibua',
        'double_taxes',
        'barcode_one',
        'barcode_two',
        'minimum_existence',
        'maximum_existence',
        'minor_account_id',
        'is_active',
        'manages_stock',
        'is_service',
    ];

    protected $casts = [
        'last_purchase_price' => 'decimal:2',
        'average_cost'        => 'decimal:2',
        'default_sale_price'  => 'decimal:2',
        'consumption_tax'     => 'decimal:4',
        'icui'                => 'decimal:4',
        'ibua'                => 'decimal:4',
        'minimum_existence'   => 'decimal:2',
        'maximum_existence'   => 'decimal:2',
        'double_taxes'        => 'boolean',
        'is_active'           => 'boolean',
        'manages_stock'       => 'boolean',
        'is_service'          => 'boolean',
    ];

    /**
     * Genera código interno automático si no se proporciona (ART-0001, ART-0002, …)
     */
    protected static function booted(): void
    {
        static::creating(function (Item $item) {
            if (empty($item->internal_code)) {
                $latest = static::withTrashed()
                    ->where('internal_code', 'like', 'ART-%')
                    ->orderByDesc('internal_code')
                    ->value('internal_code');

                $next = 1;
                if ($latest && preg_match('/ART-(\d+)/', $latest, $matches)) {
                    $next = (int) $matches[1] + 1;
                }

                $item->internal_code = 'ART-' . str_pad($next, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    // FK a catálogos en schema public — se usa DB::table(), no Eloquent model
    // unit_measure_id → public.unit_measures
    // clasification_id → public.item_clasification
    // tax_category_id → public.item_tax_categories (o public.taxes según config)

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeManagesStock(Builder $query): Builder
    {
        return $query->where('manages_stock', true);
    }
}
