<?php

namespace App\Modules\POS\Models;

use App\Modules\Core\Models\Resolution;
use App\Modules\Core\Models\Warehouse;
use App\Modules\Core\Models\Establishment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Terminal punto de venta.
 * Cada terminal tiene una resolución POS asignada, bodega y establecimiento.
 */
class PosTerminal extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pos_terminals';

    protected $fillable = [
        'name',
        'resolution_id',
        'warehouse_id',
        'establishment_id',
        'serial_number',
        'location',
        'printer_name',
        'printer_ip',
        'printer_port',
        'printer_type',
        'printer_start_with',
        'is_usb',
        'state',
    ];

    protected $casts = [
        'is_usb' => 'boolean',
        'state'  => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function resolution(): BelongsTo
    {
        return $this->belongsTo(Resolution::class, 'resolution_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class, 'establishment_id');
    }

    public function terminalUsers(): HasMany
    {
        return $this->hasMany(PosTerminalUser::class, 'pos_terminal_id');
    }

    /**
     * Usuario con turno activo actualmente en esta terminal.
     */
    public function activeTerminalUser(): HasOne
    {
        return $this->hasOne(PosTerminalUser::class, 'pos_terminal_id')
            ->where('active_shift', true);
    }
}
