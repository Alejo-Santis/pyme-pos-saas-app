<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Models\ApiLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Servicio central de auditoría.
 *
 * Uso desde cualquier Service o Controller:
 *
 *   AuditService::log('created', 'Creó factura FE-001', $document, module: 'Invoice');
 *   AuditService::logApi($document, 'send_invoice', $payload, $response, 200, true, 312);
 */
class AuditService
{
    // ── Auditoría de acciones del sistema ────────────────────────────────────

    /**
     * Registra una acción en el log de auditoría.
     *
     * @param string      $event       created|updated|deleted|login|logout|export|retry...
     * @param string      $action      Descripción legible: "Creó factura FE-001"
     * @param Model|null  $auditable   El modelo afectado (opcional)
     * @param array|null  $oldValues   Valores anteriores (para updated/deleted)
     * @param array|null  $newValues   Valores nuevos (para created/updated)
     * @param string|null $module      Módulo: Invoice, POS, Inventory, Auth...
     */
    public static function log(
        string  $event,
        string  $action,
        ?Model  $auditable = null,
        ?array  $oldValues = null,
        ?array  $newValues = null,
        ?string $module    = null,
    ): void {
        try {
            $user = Auth::user();

            AuditLog::create([
                'user_id'        => $user?->id,
                'user_name'      => $user?->name,
                'event'          => $event,
                'action'         => $action,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id'   => $auditable?->getKey(),
                'old_values'     => $oldValues,
                'new_values'     => $newValues,
                'ip_address'     => Request::ip(),
                'user_agent'     => substr(Request::userAgent() ?? '', 0, 300),
                'url'            => substr(Request::fullUrl(), 0, 500),
                'method'         => Request::method(),
                'module'         => $module,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            // No interrumpir el flujo principal si la auditoría falla
            \Log::warning('AuditService::log falló: ' . $e->getMessage());
        }
    }

    /**
     * Atajo para loguear creación de un modelo.
     */
    public static function created(Model $model, string $action, string $module): void
    {
        self::log('created', $action, $model, null, $model->toArray(), $module);
    }

    /**
     * Atajo para loguear actualización con diff.
     */
    public static function updated(Model $model, array $original, string $action, string $module): void
    {
        $changed = array_intersect_key($model->toArray(), $model->getChanges());
        $before  = array_intersect_key($original, $model->getChanges());
        self::log('updated', $action, $model, $before, $changed, $module);
    }

    /**
     * Atajo para loguear eliminación.
     */
    public static function deleted(Model $model, string $action, string $module): void
    {
        self::log('deleted', $action, $model, $model->toArray(), null, $module);
    }

    // ── Logs de API DIAN / Nextpyme ──────────────────────────────────────────

    /**
     * Registra una llamada a la API del proveedor DIAN.
     *
     * @param string|null $documentId   UUID del documento
     * @param string      $operation    send_invoice|send_credit_note|get_status...
     * @param string      $endpoint     URL del endpoint llamado
     * @param array|null  $payload      Cuerpo de la petición (sin datos sensibles)
     * @param array|null  $response     Cuerpo de la respuesta
     * @param int|null    $httpStatus   Código HTTP de respuesta
     * @param bool        $success      Si la operación fue exitosa
     * @param int|null    $durationMs   Tiempo de respuesta en milisegundos
     * @param string|null $errorMsg     Mensaje de error si no fue exitoso
     * @param int         $attempt      Número de intento (1, 2, 3...)
     * @param string      $provider     Proveedor: nextpyme, dian_directo...
     */
    public static function logApi(
        ?string $documentId,
        string  $operation,
        string  $endpoint,
        ?array  $payload,
        ?array  $response,
        ?int    $httpStatus,
        bool    $success,
        ?int    $durationMs  = null,
        ?string $errorMsg    = null,
        int     $attempt     = 1,
        string  $provider    = 'nextpyme',
    ): ApiLog {
        try {
            return ApiLog::create([
                'document_id'      => $documentId,
                'provider'         => $provider,
                'operation'        => $operation,
                'endpoint'         => $endpoint,
                'method'           => 'POST',
                'request_payload'  => $payload,
                'http_status'      => $httpStatus,
                'response_body'    => $response,
                'success'          => $success,
                'error_message'    => $errorMsg,
                'attempt'          => $attempt,
                'duration_ms'      => $durationMs,
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AuditService::logApi falló: ' . $e->getMessage());
            return new ApiLog();
        }
    }
}
