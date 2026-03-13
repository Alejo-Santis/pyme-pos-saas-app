<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Core\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP para la API de Nextpyme (proveedor DIAN).
 * Lee la configuración de la empresa activa del tenant.
 *
 * Adaptado de xedoc-laravel-svelte/app/Services/Nextpyme/ApiNextpymeService.php
 */
class ApiNextpymeService
{
    private string $apiPath    = '';
    private string $apiToken   = '';
    private bool   $hasConfig  = false;

    public function __construct()
    {
        $company = Company::first();

        if ($company && ! empty($company->api_token_fe)) {
            $this->hasConfig = true;
            $this->apiPath   = rtrim($company->api_path_fe ?? '', '/');
            $this->apiToken  = $company->api_token_fe;
        }
    }

    /**
     * Realiza una petición HTTP a la API de Nextpyme.
     *
     * @param string $method     Método HTTP (POST, GET, etc.)
     * @param string $endpoint   Ruta relativa (ej: '/ubl2.1/invoice')
     * @param array  $parameters Body de la petición
     * @param int    $timeout    Timeout en segundos
     */
    public function makeRequest(string $method, string $endpoint, array $parameters = [], int $timeout = 120): array
    {
        if (! $this->hasConfig) {
            Log::warning('ApiNextpymeService: empresa sin configuración de API FE', [
                'endpoint' => $endpoint,
            ]);

            return [
                'statusCode' => 404,
                'data'       => [
                    'success' => false,
                    'message' => 'Integración DIAN no configurada. Configure api_path_fe y api_token_fe en la empresa.',
                ],
            ];
        }

        $url    = $this->apiPath . $endpoint;
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
}
