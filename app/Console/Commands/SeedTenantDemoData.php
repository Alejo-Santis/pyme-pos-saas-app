<?php

namespace App\Console\Commands;

use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\TenantCeramicsDemoSeeder;
use Illuminate\Console\Command;

class SeedTenantDemoData extends Command
{
    protected $signature = 'tenants:seed-demo {--tenant= : ID del tenant especifico}';

    protected $description = 'Puebla tenant(s) con datos demo de una empresa de ceramicas y acabados';

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

            $tenant->run(function () {
                app(TenantCeramicsDemoSeeder::class)->run();
            });

            $this->line('  Datos demo sembrados.');
        }

        $this->info('Listo.');
        return self::SUCCESS;
    }
}
