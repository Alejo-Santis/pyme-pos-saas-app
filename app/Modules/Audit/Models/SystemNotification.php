<?php

namespace App\Modules\Audit\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificaciones transaccionales del sistema.
 *
 * Tipos disponibles:
 *   low_stock       → stock por debajo del mínimo
 *   dian_rejection  → factura rechazada por DIAN
 *   receivable_due  → factura de cliente vencida
 *   payable_due     → cuenta por pagar vencida
 *   nes_failed      → fallo en envío de nómina electrónica
 *   period_closing  → recordatorio de cierre de período contable
 */
class SystemNotification extends Model
{
    use HasUuids;

    protected $table = 'system_notifications';

    protected $fillable = [
        'user_id', 'type', 'title', 'body',
        'icon', 'color', 'data', 'read', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read'    => 'boolean',
        'read_at' => 'datetime',
    ];

    public const TYPE_LOW_STOCK      = 'low_stock';
    public const TYPE_DIAN_REJECTION = 'dian_rejection';
    public const TYPE_RECEIVABLE_DUE = 'receivable_due';
    public const TYPE_PAYABLE_DUE    = 'payable_due';
    public const TYPE_NES_FAILED     = 'nes_failed';
    public const TYPE_PERIOD_CLOSING = 'period_closing';

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeForUser($query, ?string $userId)
    {
        return $query->where(fn ($q) =>
            $q->whereNull('user_id')->orWhere('user_id', $userId)
        );
    }

    public function markAsRead(): void
    {
        $this->update(['read' => true, 'read_at' => now()]);
    }
}
