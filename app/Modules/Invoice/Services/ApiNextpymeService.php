<?php

namespace App\Modules\Invoice\Services;

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
 * Mientras el proyecto esté en desarrollo siempre se usará la URL del sandbox.
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
            // Sandbox / habilitación — usar hasta finalizar el proyecto
            $url   = env('NEXTPYME_FE_SANDBOX_URL', '');
            $token = env('NEXTPYME_FE_TOKEN', '');
        } else {
            // Producción
            $url   = env('NEXTPYME_BASE_URL', '');
            $token = env('NEXTPYME_API_KEY', '');
        }

        if (! empty($url) && ! empty($token)) {
            $this->apiPath   = rtrim($url, '/');
            $this->apiToken  = $token;
            $this->hasConfig = true;

            Log::info('ApiNextpymeService: usando credenciales del .env', [
                'environment' => $environment,
                'url'         => $this->apiPath,
            ]);
        }
    }

    /**
     * Realiza una petición HTTP a la API de Nextpyme.
     *
     * @param string $method    Método HTTP (POST, GET, etc.)
     * @param string $endpoint  Ruta relativa (ej: '/ubl2.1/invoice')
     * @param array  $parameters Body de la petición
     * @param int    $timeout   Timeout en segundos
     */
    public function makeRequest(string $method, string $endpoint, array $parameters = [], int $timeout = 120): array
    {
        if (! $this->hasConfig) {
            Log::warning('ApiNextpymeService: sin configuración de API FE', [
                'endpoint' => $endpoint,
                'hint'     => 'Configure api_path_fe y api_token_fe en la empresa, o defina NEXTPYME_FE_SANDBOX_URL y NEXTPYME_FE_TOKEN en .env',
            ]);

            return [
                'statusCode' => 404,
                'data'       => [
                    'success' => false,
                    'message' => 'Integración DIAN no configurada. Configure las credenciales en Ajustes > Empresa.',
                ],
            ];
        }

        // El endpoint ya incluye /ubl2.1/... — si la base también lo tiene, evitamos duplicado
        $base = $this->apiPath;
        $path = $endpoint;

        // Si la base termina en /ubl2.1 y el path empieza en /ubl2.1, recortamos el base
        if (str_ends_with($base, '/ubl2.1') && str_starts_with($path, '/ubl2.1/')) {
            $base = substr($base, 0, -strlen('/ubl2.1'));
        }

        $url    = $base . $path;
        $method = strtolower($method);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->timeout($timeout)
            ->{$method}($url, $parameters);

            return [
                'statusCode' => $response->status(),
                'data'       => $response->json() ?? [],
                'message'    => $response->status() >= 400 ? $response->body() : null,
            ];
        } catch (\Throwable $th) {
            Log::error('Error de conexión con Nextpyme', [
                'url'   => $url,
                'error' => $th->getMessage(),
            ]);

            return [
                'statusCode' => 500,
                'data'       => [
                    'success' => false,
                    'message' => 'Error de conexión con Nextpyme: ' . $th->getMessage(),
                ],
            ];
        }
    }

    /**
     * Indica si el servicio tiene credenciales válidas para operar.
     */
    public function isConfigured(): bool
    {
        return $this->hasConfig;
    }
}
