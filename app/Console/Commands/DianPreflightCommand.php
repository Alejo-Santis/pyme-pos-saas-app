<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Resolution;
use App\Modules\Invoice\Models\Document;
use App\Modules\Invoice\Services\ApiNextpymeService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;

class DianPreflightCommand extends Command
{
    protected $signature = 'dian:preflight
        {--tenant= : ID del tenant específico}
        {--connection : Ejecuta prueba HTTP segura contra Nextpyme}
        {--json : Imprime el resultado en JSON}';

    protected $description = 'Valida configuración mínima DIAN/Nextpyme por tenant antes de emitir documentos electrónicos';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId ? Tenant::where('id', $tenantId)->get() : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No se encontraron tenants para validar.');

            return self::FAILURE;
        }

        $results = [];

        foreach ($tenants as $tenant) {
            $results[] = [
                'tenant_id' => $tenant->id,
                'checks' => $tenant->run(fn () => $this->checkTenant()),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderResults($results);
        }

        return $this->hasBlockingFailures($results) ? self::FAILURE : self::SUCCESS;
    }

    private function checkTenant(): array
    {
        $company = Company::query()
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();

        $checks = [
            'company' => [
                'ok' => (bool) $company,
                'level' => $company ? 'ok' : 'error',
                'message' => $company ? "Empresa activa: {$company->business_name}" : 'No hay empresa activa configurada.',
            ],
        ];

        if (! $company) {
            return $checks;
        }

        $checks['electronic_documents'] = $this->check(
            (bool) $company->electronic_documents,
            'Facturación electrónica activa.',
            'Facturación electrónica desactivada en la empresa.',
            'warning',
        );

        $checks['api_path_fe'] = $this->check(
            filled($company->api_path_fe),
            "URL proveedor: {$company->api_path_fe}",
            'Falta api_path_fe del proveedor electrónico.',
        );

        $checks['api_token_fe'] = $this->check(
            filled($company->api_token_fe),
            'Token proveedor configurado.',
            'Falta api_token_fe del proveedor electrónico.',
        );

        $checks['dian_software_id'] = $this->check(
            filled($company->dian_software_id),
            'Software ID DIAN configurado.',
            'Falta Software ID DIAN. Puede ser requerido para emisión real.',
            'warning',
        );

        $checks['dian_software_security_code'] = $this->check(
            filled($company->dian_software_security_code),
            'Código de seguridad DIAN configurado.',
            'Falta código de seguridad DIAN. Puede ser requerido para emisión real.',
            'warning',
        );

        $invoiceResolutions = Resolution::query()
            ->where('type_document_operation_id', 1)
            ->where('is_active', true)
            ->count();

        $checks['invoice_resolution'] = $this->check(
            $invoiceResolutions > 0,
            "Resoluciones FEV activas: {$invoiceResolutions}.",
            'No hay resolución activa para factura electrónica de venta.',
            'warning',
        );

        $pendingDocuments = Document::query()
            ->where('annulled', false)
            ->whereIn('dian_status', ['pending', 'processing'])
            ->count();

        $failedDocuments = Document::query()
            ->where('annulled', false)
            ->whereIn('dian_status', ['failed', 'rejected'])
            ->count();

        $checks['document_queue'] = [
            'ok' => true,
            'level' => $failedDocuments > 0 ? 'warning' : 'ok',
            'message' => "Cola DIAN: {$pendingDocuments} pendientes/en proceso, {$failedDocuments} fallidos/rechazados.",
        ];

        if ($this->option('connection')) {
            $connection = app(ApiNextpymeService::class)->testConnection();
            $checks['nextpyme_connection'] = [
                'ok' => ($connection['reachable'] ?? false) && ($connection['authorized'] ?? false),
                'level' => (($connection['reachable'] ?? false) && ($connection['authorized'] ?? false)) ? 'ok' : 'error',
                'message' => $connection['message'] ?? 'Sin respuesta de prueba.',
                'status_code' => $connection['statusCode'] ?? null,
                'url' => $connection['url'] ?? null,
            ];
        }

        return $checks;
    }

    private function check(bool $condition, string $okMessage, string $failMessage, string $failLevel = 'error'): array
    {
        return [
            'ok' => $condition,
            'level' => $condition ? 'ok' : $failLevel,
            'message' => $condition ? $okMessage : $failMessage,
        ];
    }

    private function renderResults(array $results): void
    {
        foreach ($results as $tenantResult) {
            $this->newLine();
            $this->info("Tenant: {$tenantResult['tenant_id']}");

            $rows = collect($tenantResult['checks'])
                ->map(fn (array $check, string $key) => [
                    $key,
                    strtoupper($check['level'] ?? 'unknown'),
                    $check['message'] ?? '',
                ])
                ->values()
                ->all();

            $this->table(['Chequeo', 'Estado', 'Mensaje'], $rows);
        }
    }

    private function hasBlockingFailures(array $results): bool
    {
        foreach ($results as $tenantResult) {
            foreach ($tenantResult['checks'] as $check) {
                if (($check['level'] ?? null) === 'error') {
                    return true;
                }
            }
        }

        return false;
    }
}
