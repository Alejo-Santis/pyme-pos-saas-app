<?php

namespace Tests;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Facades\Tenancy;

abstract class TestCase extends BaseTestCase
{
    protected static ?Tenant $testTenant = null;
    protected static string  $testSlug   = 'testempresa';

    /**
     * Antes de todos los tests del archivo: crea tenant y corre seeders.
     * Se reutiliza el mismo tenant entre tests para mayor velocidad.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    /**
     * Inicializa el tenant de prueba antes de cada test Feature.
     * Si el tenant no existe, lo crea con su schema.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initTenant();
    }

    protected function tearDown(): void
    {
        // Terminar tenancy si está activa
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
        $plan = Plan::firstOrCreate(
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

        // Crear tenant si no existe
        $tenant = Tenant::firstOrCreate(
            ['id' => static::$testSlug],
            [
                'name'  => 'Empresa Test S.A.S',
                'email' => 'test@empresa.co',
                'nit'   => '900000000-1',
            ]
        );

        // Crear dominio si no existe
        if ($tenant->domains()->count() === 0) {
            $tenant->createDomain(['domain' => static::$testSlug]);
        }

        // Inicializar tenancy → cambia al schema del tenant
        tenancy()->initialize($tenant);

        // Correr seeders de roles si no hay roles aún
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            (new TenantRolesSeeder())->run();
        }

        static::$testTenant = $tenant;
    }

    /**
     * Simula una petición HTTP en el contexto del tenant.
     * Agrega el header de dominio para que el middleware lo resuelva.
     */
    protected function tenantGet(string $uri, array $headers = [])
    {
        return $this->withHeaders(array_merge([
            'HOST' => static::$testSlug . '.' . config('app.central_domain', 'pymepossaas-app.test'),
        ], $headers))->get($uri);
    }

    protected function tenantPost(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders(array_merge([
            'HOST' => static::$testSlug . '.' . config('app.central_domain', 'pymepossaas-app.test'),
        ], $headers))->post($uri, $data);
    }

    protected function tenantPut(string $uri, array $data = [], array $headers = [])
    {
        return $this->withHeaders(array_merge([
            'HOST' => static::$testSlug . '.' . config('app.central_domain', 'pymepossaas-app.test'),
        ], $headers))->put($uri, $data);
    }

    protected function tenantDelete(string $uri, array $headers = [])
    {
        return $this->withHeaders(array_merge([
            'HOST' => static::$testSlug . '.' . config('app.central_domain', 'pymepossaas-app.test'),
        ], $headers))->delete($uri);
    }
}
