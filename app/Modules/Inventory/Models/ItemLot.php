<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Core\Models\Warehouse;

/**
 * Lote o número de serie de un producto.
 *
 * tracking_type = 'lot'    → agrupa unidades por lote (farmacéutica, alimentos)
 * tracking_type = 'serial' → una unidad por registro (equipos, electrónicos)
 */
class ItemLot extends Model
{
    use HasUuids;

    protected $table = 'item_lots';

    protected $fillable = [
        'item_id', 'warehouse_id', 'lot_number', 'tracking_type',
        'quantity', 'expiry_date', 'manufacture_date', 'status',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'expiry_date'      => 'date',
        'manufacture_date' => 'date',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_EXPIRED  = 'expired';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ItemLotMovement::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->where('expiry_date', '>=', now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }
}
