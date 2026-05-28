<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LandlordAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_user_id',
        'admin_name',
        'admin_email',
        'event',
        'module',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];
}
