<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdParty extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'type_document_identification_id',
        'identification_number',
        'dv',
        'type_organization_id',
        'type_regime_id',
        'type_liability_id',
        'type_third_id',
        'name',
        'surname',
        'email',
        'phone',
        'address',
        'country_id',
        'municipality_id',
        'excluded_from_taxes',
        'great_contributor',
        'self_retaining',
        'seller',
        'credit_limit',
        'payment_days',
        'is_active',
    ];

    protected $casts = [
        'excluded_from_taxes' => 'boolean',
        'great_contributor'   => 'boolean',
        'self_retaining'      => 'boolean',
        'seller'              => 'boolean',
        'is_active'           => 'boolean',
        'credit_limit'        => 'decimal:2',
    ];

    // Nombre completo calculado (persona natural o jurídica)
    public function getFullNameAttribute(): string
    {
        if ($this->name && $this->surname) {
            return trim("{$this->name} {$this->surname}");
        }
        return $this->name ?? $this->identification_number;
    }

    // La migración tiene columna uuid además del id — ambas necesitan UUID auto-generado
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function linkage(): HasOne
    {
        return $this->hasOne(PartyLinkage::class);
    }
}
