<?php

namespace App\Modules\POS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Turno de caja: asignación de un usuario a una terminal POS.
 * active_shift = true significa que el turno está abierto.
 */
class PosTerminalUser extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'pos_terminal_users';

    protected $fillable = [
        'pos_terminal_id',
        'user_id',
        'initial_balance',
        'current_balance',
        'active_shift',
        'cashier_session_key',
        'state',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'active_shift'    => 'boolean',
        'state'           => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Genera una llave única de sesión para identificar el turno.
     */
    public static function generateSessionKey(): string
    {
        return Str::upper(Str::random(3)) . '-' . now()->format('YmdHis');
    }
}
