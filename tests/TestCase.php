<?php

namespace Tests;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected static ?Tenant $testTenant = null;
    protected static string  $testSlug   = 'testempresa';

    /**
     * Inicializa el tenant de prueba antes de cada test Feature, y abre una
     * transacción SOLO sobre la conexión 'tenant' (no la landlord/pgsql):
     * el tenant y su schema se crean una única vez por proceso (crear el
     * schema completo — ~40 migraciones — es caro), pero los datos de negocio
     * que cada test inserta (usuarios, facturas, ítems...) se revierten al
     * terminar el test para que no contaminen el siguiente.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initTenant();
        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback ANTES de terminar tenancy: end() puede reconfigurar/
        // desconectar la conexión 'tenant', y ya no habría nada que revertir.
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    // ── Helpers de tenancy ────────────────────────────────────────────────

    /**
     * Crea (si no existe) e inicializa el tenant de prueba.
     */
    protected function initTenant(): void
    {
        // Asegurarse de tener un plan
        Plan::firstOrCreate(
            ['slug' => 'test-plan'],
            [
                'name'          => 'Plan Test',
                'price_monthly' => 0,
                'price_yearly'  => 0,
                'trial_days'    => 30,
                'is_active'     => true,
                'features'      => ['dian_fe' => true, 'pos' => true, 'accounting' => true, 'inventory' => true, 'payroll' => true],
                'sort_order'    => 99,
            ]
        );

        // Crear tenant si no existe (dispara CreateDatabase + MigrateDatabase
        // la primera vez — costoso, por eso solo pasa una vez por proceso).
        $tenant = Tenant::firstOrCreate(
            ['id' => static::$testSlug],
            [
                'name'   => 'Empresa Test S.A.S',
                'email'  => 'test@empresa.co',
                'status' => 'active',
            ]
        );

        if ($tenant->domains()->count() === 0) {
            $tenant->domains()->create(['domain' => static::$testSlug]);
        }

        tenancy()->initialize($tenant);

        // Siempre corre (firstOrCreate/syncPermissions es idempotente y barato):
        // así un permiso nuevo agregado al seeder queda disponible de inmediato,
        // sin depender de borrar a mano el schema del tenant de pruebas.
        (new TenantRolesSeeder())->run();

        // PUC Colombia — lo necesita cualquier test de contabilidad/motor
        // contable. Como corre fuera de la transacción por-test, solo se
        // siembra una vez por proceso (idempotente vía upsert en el seeder).
        if (DB::table('chart_of_accounts')->count() === 0) {
            (new \Database\Seeders\PucSeeder())->run();
        }

        static::$testTenant = $tenant;
    }

    /**
     * Simula una petición HTTP en el contexto del tenant.
     *
     * IMPORTANTE: el host debe ir en la URL misma, no en un header 'HOST' vía
     * withHeaders() — Symfony\Request::create() reconstruye SERVER_NAME/
     * HTTP_HOST a partir del host de la URI y PISA cualquier header enviado,
     * así que un header por sí solo nunca cambia el dominio que ve la app.
     */
    protected function tenantUrl(string $uri): string
    {
        return 'http://'.static::$testSlug.$uri;
    }

    protected function tenantGet(string $uri, array $headers = [])
    {
        return $this->withHeaders($headers)->get($this->tenantUrl($uri));
    }

    protected function tenantPost(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders($headers)->post($this->tenantUrl($uri), $data);
    }

    protected function tenantPut(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders($headers)->put($this->tenantUrl($uri), $data);
    }

    protected function tenantDelete(string $uri, array $headers = [])
    {
        return $this->withHeaders($headers)->delete($this->tenantUrl($uri));
    }
}
