<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'billing_period',
        'price',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'cancelled_at',
        'grace_ends_at',
        'gateway',
        'gateway_subscription_id',
        'gateway_data',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'starts_at'     => 'datetime',
        'ends_at'       => 'datetime',
        'cancelled_at'  => 'datetime',
        'grace_ends_at' => 'datetime',
        'gateway_data'  => 'array',
    ];

    /**
     * Tenant dueño de esta suscripción.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Plan asociado.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Verifica si la suscripción está vigente.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active']);
    }
}
