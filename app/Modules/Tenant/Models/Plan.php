<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'max_users',
        'max_products',
        'max_invoices_monthly',
        'features',
        'trial_days',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features'           => 'array',
        'price_monthly'      => 'decimal:2',
        'price_yearly'       => 'decimal:2',
        'is_active'          => 'boolean',
        'trial_days'         => 'integer',
    ];

    /**
     * Subscripciones que usan este plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Tenants activos en este plan.
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Verifica si el plan tiene una feature habilitada.
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }
}
