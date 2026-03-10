<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyLinkage extends Model
{
    protected $fillable = [
        'third_party_id',
        'customer',
        'provider',
        'other',
    ];

    protected $casts = [
        'customer' => 'boolean',
        'provider' => 'boolean',
        'other'    => 'boolean',
    ];

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }
}
