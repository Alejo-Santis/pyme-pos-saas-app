<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\LandlordAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LandlordAuditService
{
    public function record(
        string $event,
        string $module,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): void {
        try {
            $admin = Auth::guard('admin')->user();

            LandlordAuditLog::create([
                'admin_user_id' => $admin?->id,
                'admin_name' => $admin?->name,
                'admin_email' => $admin?->email,
                'event' => $event,
                'module' => $module,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'metadata' => $metadata ?: null,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar auditoria landlord: ' . $e->getMessage());
        }
    }
}
