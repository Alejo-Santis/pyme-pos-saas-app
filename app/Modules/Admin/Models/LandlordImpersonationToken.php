<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LandlordImpersonationToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'token_hash',
        'tenant_id',
        'tenant_domain',
        'admin_user_id',
        'admin_name',
        'admin_email',
        'tenant_user_id',
        'tenant_user_name',
        'tenant_user_email',
        'expires_at',
        'consumed_at',
        'created_ip',
        'consumed_ip',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
