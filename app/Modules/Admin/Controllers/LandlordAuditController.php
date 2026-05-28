<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\LandlordAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandlordAuditController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = LandlordAuditLog::query()
            ->when($request->search, fn ($q, $search) =>
                $q->where('admin_name', 'ilike', "%{$search}%")
                    ->orWhere('admin_email', 'ilike', "%{$search}%")
                    ->orWhere('event', 'ilike', "%{$search}%")
                    ->orWhere('auditable_id', 'ilike', "%{$search}%")
            )
            ->when($request->module, fn ($q, $module) => $q->where('module', $module))
            ->when($request->event, fn ($q, $event) => $q->where('event', $event))
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (LandlordAuditLog $log) => [
                'id' => $log->id,
                'admin_name' => $log->admin_name ?? 'Sistema',
                'admin_email' => $log->admin_email,
                'event' => $log->event,
                'module' => $log->module,
                'auditable_type' => class_basename($log->auditable_type ?? ''),
                'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'module', 'event']),
            'modules' => LandlordAuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'events' => LandlordAuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
