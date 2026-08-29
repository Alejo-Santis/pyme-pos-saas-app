<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasUuids;

    protected $table = 'transfers';

    protected $fillable = [
        'uuid',
        'warehouse_origin_id',
        'warehouse_destination_id',
        'user_id',
        'status',
        'transfer_date',
        'notes',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'subtotal'      => 'decimal:4',
        'total'         => 'decimal:4',
    ];

    // Estados posibles
    const STATUS_DRAFT     = 'draft';
    const STATUS_TRANSIT   = 'in_transit';
    const STATUS_RECEIVED  = 'received';
    const STATUS_CANCELLED = 'cancelled';

    public function warehouseOrigin(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_origin_id');
    }

    public function warehouseDestination(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_destination_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class, 'transfer_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TransferHistory::class, 'transfer_id')->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'Borrador',
            self::STATUS_TRANSIT   => 'En tránsito',
            self::STATUS_RECEIVED  => 'Recibido',
            self::STATUS_CANCELLED => 'Cancelado',
            default                => $this->status,
        };
    }

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
