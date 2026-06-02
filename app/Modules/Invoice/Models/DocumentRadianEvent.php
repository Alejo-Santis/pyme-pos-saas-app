<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de eventos RADIAN enviados a DIAN por documento.
 * Tabla: document_radian_events
 *
 * Un documento puede tener hasta 4 eventos RADIAN:
 *   030 → Acuse de recibo
 *   031 → Reclamo (rechazo)
 *   032 → Recibo del bien/servicio
 *   033 → Aceptación expresa
 */
class DocumentRadianEvent extends Model
{
    use HasUuids;

    protected $table = 'document_radian_events';

    protected $fillable = [
        'document_id',
        'event_key',
        'event_code',
        'event_name',
        'cude',
        'status',
        'attempts',
        'error_message',
        'response_api',
        'sent_at',
    ];

    protected $casts = [
        'error_message' => 'array',
        'response_api'  => 'array',
        'sent_at'       => 'datetime',
        'attempts'      => 'integer',
    ];

    // ── Mapeo interno de eventos RADIAN ──────────────────────────────────

    public const EVENTS = [
        'accuse'     => ['code' => '030', 'name' => 'Acuse de recibo de Factura Electrónica de Venta'],
        'claim'      => ['code' => '031', 'name' => 'Reclamo de la Factura Electrónica de Venta'],
        'received'   => ['code' => '032', 'name' => 'Recibo del bien y/o prestación del servicio'],
        'acceptance' => ['code' => '033', 'name' => 'Aceptación expresa de la Factura Electrónica de Venta'],
    ];

    // ── Estados ───────────────────────────────────────────────────────────

    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_FAILED   = 'failed';

    // ── Relaciones ────────────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Devuelve los datos del evento para una clave interna (accuse, claim, etc.).
     */
    public static function eventData(string $key): ?array
    {
        return self::EVENTS[$key] ?? null;
    }
}
