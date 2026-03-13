<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Services\DefaultsService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Siembra resoluciones y terceros por defecto en todos los tenants
 * (o en uno específico si se pasa --tenant=<id>).
 *
 * Uso:
 *   php artisan tenants:seed-defaults
 *   php artisan tenants:seed-defaults --tenant=e2714e19-6d5b-47df-9a93-18bc5f99f3fd
 */
class SeedTenantDefaults extends Command
{
    protected $signature   = 'tenants:seed-defaults {--tenant= : ID del tenant específico}';
    protected $description = 'Siembra resoluciones y terceros por defecto en tenant(s)';

    public function __construct(
        private readonly DefaultsService $defaults
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id}");

            $tenant->run(function () use ($tenant) {
                $company = Company::first();

                if (! $company) {
                    $this->warn("  ⚠ Sin empresa configurada — se omite.");
                    return;
                }

                $this->defaults->seedResolutions($company);
                $this->line("  ✓ Resoluciones sembradas ({$company->business_name})");

                $this->defaults->seedThirds($company);
                $this->line("  ✓ Terceros sembrados");
            });
        }

        $this->info('¡Listo!');
        return self::SUCCESS;
    }
}
