<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tasas de retención configuradas por tercero (cliente).
 *
 * Tipos de retención:
 *   retefuente → Retención en la Fuente (Art. 375 ET)
 *   reteiva    → Retención de IVA (Art. 437-1 ET), calculada sobre el IVA
 *   reteica    → Retención de ICA (municipal), calculada sobre el subtotal
 */
class ThirdPartyRetentionConfig extends Model
{
    use HasUuids;

    protected $table = 'third_party_retention_configs';

    protected $fillable = [
        'third_party_id',
        'retention_type',
        'label',
        'percent',
        'base',
        'is_active',
    ];

    protected $casts = [
        'percent'   => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public const TYPES = ['retefuente', 'reteiva', 'reteica'];

    public const TYPE_LABELS = [
        'retefuente' => 'Retención en la Fuente',
        'reteiva'    => 'Retención de IVA',
        'reteica'    => 'Retención de ICA',
    ];

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    /**
     * Calcula el valor de la retención sobre los montos dados.
     */
    public function calculate(float $subtotal, float $tax): float
    {
        $base = match ($this->base) {
            'tax'   => $tax,
            'total' => $subtotal + $tax,
            default => $subtotal,  // 'subtotal'
        };

        return round($base * ((float) $this->percent / 100), 4);
    }
}
