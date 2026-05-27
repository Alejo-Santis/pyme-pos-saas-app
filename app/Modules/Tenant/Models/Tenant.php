<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Columnas que stancl/tenancy no debe serializar en el campo JSON 'data'.
     * Todo lo que esté aquí se guarda como columna real en la tabla tenants.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'status',
            'plan_id',
            'trial_ends_at',
        ];
    }

    // -------------------------------------------------------------------------
    // Relaciones landlord (schema public)
    // -------------------------------------------------------------------------

    /**
     * Plan activo del tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Todas las suscripciones históricas.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    /**
     * Suscripción activa actual.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id')
            ->whereIn('status', ['trial', 'active'])
            ->latest();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verifica si el tenant está en periodo trial.
     */
    public function isOnTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Verifica si el tenant puede operar (activo o trial vigente).
     */
    public function isOperational(): bool
    {
        return $this->status === 'active' || $this->isOnTrial();
    }

    /**
     * Alias usado por controladores/vistas del tenant.
     */
    public function canOperate(): bool
    {
        return $this->isOperational();
    }
}
