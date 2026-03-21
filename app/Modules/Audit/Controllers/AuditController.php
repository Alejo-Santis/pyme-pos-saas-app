<?php

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\ApiLog;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Log de actividad del sistema (auditoría de acciones).
     */
    public function activity(Request $request): Response
    {
        $query = AuditLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'ilike', "%{$search}%")
                  ->orWhere('user_name', 'ilike', "%{$search}%")
                  ->orWhere('module', 'ilike', "%{$search}%");
            });
        }

        if ($event = $request->input('event')) {
            $query->where('event', $event);
        }

        if ($module = $request->input('module')) {
            $query->where('module', $module);
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Audit/Activity', [
            'logs'    => $logs,
            'filters' => $request->only('search', 'event', 'module'),
        ]);
    }

    /**
     * Log de llamadas a la API DIAN / Nextpyme.
     */
    public function apiLogs(Request $request): Response
    {
        $query = ApiLog::query()
            ->with('document:id,prefix,number,type_document_operation_id')
            ->orderByDesc('created_at');

        if ($operation = $request->input('operation')) {
            $query->where('operation', $operation);
        }

        if ($request->boolean('only_errors')) {
            $query->where('success', false);
        }

        if ($documentId = $request->input('document_id')) {
            $query->where('document_id', $documentId);
        }

        $logs = $query->paginate(50)->withQueryString();

        // Resumen rápido
        $summary = [
            'total'   => ApiLog::count(),
            'success' => ApiLog::where('success', true)->count(),
            'failed'  => ApiLog::where('success', false)->count(),
            'avg_ms'  => (int) ApiLog::whereNotNull('duration_ms')->avg('duration_ms'),
        ];

        return Inertia::render('Audit/ApiLogs', [
            'logs'    => $logs,
            'summary' => $summary,
            'filters' => $request->only('operation', 'only_errors', 'document_id'),
        ]);
    }
}
