<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Audit\Services\AuditService;
use App\Modules\Core\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para la API de Nextpyme (proveedor DIAN).
 *
 * Orden de resolución de credenciales:
 *   1. Columnas api_path_fe / api_token_fe de la empresa activa del tenant.
 *   2. Variables de entorno NEXTPYME_FE_SANDBOX_URL / NEXTPYME_FE_TOKEN
 *      cuando DIAN_ENVIRONMENT=habilitacion (desarrollo / sandbox).
 *   3. Variables de entorno NEXTPYME_BASE_URL / NEXTPYME_API_KEY para producción.
 *
 * Cada llamada queda registrada en api_logs vía AuditService::logApi().
 */
class ApiNextpymeService
{
    private string $apiPath   = '';
    private string $apiToken  = '';
    private bool   $hasConfig = false;

    public function __construct()
    {
        $company = Company::first();

        // 1. Credenciales de la empresa (configuradas desde la UI)
        if ($company && ! empty($company->api_token_fe)) {
            $this->apiPath   = rtrim($company->api_path_fe ?? '', '/');
            $this->apiToken  = $company->api_token_fe;
            $this->hasConfig = true;
            return;
        }

        // 2. Fallback a variables de entorno según ambiente
        $environment = config('app.dian_environment', env('DIAN_ENVIRONMENT', 'habilitacion'));

        if ($environment === 'habilitacion') {
            $url   = env('NEXTPYME_FE_SANDBOX_URL', '');
            $token = env('NEXTPYME_FE_TOKEN', '');
        } else {
            $url   = env('NEXTPYME_BASE_URL', '');
            $token = env('NEXTPYME_API_KEY', '');
        }

        if (! empty($url) && ! empty($token)) {
            $this->apiPath   = rtrim($url, '/');
            $this->apiToken  = $token;
            $this->hasConfig = true;
        }
    }

    /**
     * Realiza una petición HTTP a la API de Nextpyme y la registra en api_logs.
     *
     * @param string      $method      Método HTTP (POST, GET, etc.)
     * @param string      $endpoint    Ruta relativa (ej: '/ubl2.1/invoice')
     * @param array       $parameters  Body de la petición
     * @param int         $timeout     Timeout en segundos
     * @param string|null $documentId  UUID del documento para api_logs
     * @param string      $operation   Nombre de la operación para api_logs
     * @param int         $attempt     Número de intento (para reintentos)
     */
    public function makeRequest(
        string  $method,
        string  $endpoint,
        array   $parameters  = [],
        int     $timeout     = 120,
        ?string $documentId  = null,
        string  $operation   = 'request',
        int     $attempt     = 1,
    ): array {
        if (! $this->hasConfig) {
            $result = [
                'statusCode' => 404,
                'data'       => [
                    'success' => false,
                    'message' => 'Integración DIAN no configurada. Configure las credenciales en Ajustes > Empresa.',
                ],
            ];

            AuditService::logApi(
                documentId: $documentId,
                operation:  $operation,
                endpoint:   $endpoint,
                payload:    null,
                response:   $result['data'],
                httpStatus: 404,
                success:    false,
                durationMs: 0,
                errorMsg:   'Sin configuración de API FE',
                attempt:    $attempt,
            );

            return $result;
        }

        // Construir URL final evitando doble /ubl2.1
        $base = $this->apiPath;
        $path = $endpoint;

        if (str_ends_with($base, '/ubl2.1') && str_starts_with($path, '/ubl2.1/')) {
            $base = substr($base, 0, -strlen('/ubl2.1'));
        }

        $url    = $base . $path;
        $method = strtolower($method);

        $startMs = (int) (microtime(true) * 1000);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->timeout($timeout)
            ->{$method}($url, $parameters);

            $durationMs = (int) (microtime(true) * 1000) - $startMs;
            $status     = $response->status();
            $body       = $response->json() ?? [];
            $success    = $status < 400 && ($body['success'] ?? true);

            AuditService::logApi(
                documentId: $documentId,
                operation:  $operation,
                endpoint:   $url,
                payload:    $this->sanitizePayload($parameters),
                response:   $body,
                httpStatus: $status,
                success:    $success,
                durationMs: $durationMs,
                errorMsg:   $success ? null : ($body['message'] ?? $response->body()),
                attempt:    $attempt,
            );

            return [
                'statusCode' => $status,
                'data'       => $body,
                'message'    => $status >= 400 ? ($body['message'] ?? $response->body()) : null,
            ];

        } catch (\Throwable $th) {
            $durationMs = (int) (microtime(true) * 1000) - $startMs;

            Log::error('Error de conexión con Nextpyme', [
                'url'   => $url,
                'error' => $th->getMessage(),
            ]);

            AuditService::logApi(
                documentId: $documentId,
                operation:  $operation,
                endpoint:   $url,
                payload:    $this->sanitizePayload($parameters),
                response:   null,
                httpStatus: 500,
                success:    false,
                durationMs: $durationMs,
                errorMsg:   $th->getMessage(),
                attempt:    $attempt,
            );

            return [
                'statusCode' => 500,
                'data'       => [
                    'success' => false,
                    'message' => 'Error de conexión con Nextpyme: ' . $th->getMessage(),
                ],
            ];
        }
    }

    public function isConfigured(): bool
    {
        return $this->hasConfig;
    }

    /**
     * Elimina campos sensibles del payload antes de guardarlo en api_logs.
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitive = ['password', 'token', 'secret', 'key', 'authorization', 'api_key', 'access_token', 'refresh_token', 'bearer', 'private_key'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitive) {
            if (in_array(strtolower((string) $key), $sensitive)) {
                $value = '[REDACTED]';
            }
        });

        return $payload;
    }
}
