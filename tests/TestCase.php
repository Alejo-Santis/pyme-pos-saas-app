<?php

namespace Tests;

use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Facades\Tenancy;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

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
        $this->cleanupSqliteTenantDatabase();

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
            $tenant->createDomain(['domain' => $this->tenantHost()]);
        }

        // Inicializar tenancy → cambia al schema del tenant
        tenancy()->initialize($tenant);
        $this->seedSqliteTenantCatalogs();

        // Correr seeders de roles si no hay roles aún
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            (new TenantDatabaseSeeder())->run();
        }

        static::$testTenant = $tenant;
    }

    protected function cleanupSqliteTenantDatabase(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        if (Tenant::where('id', static::$testSlug)->exists()) {
            return;
        }

        $path = database_path('tenant_' . static::$testSlug);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected function seedSqliteTenantCatalogs(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('unit_measures')) {
            Schema::create('unit_measures', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->timestamps();
            });

            DB::table('unit_measures')->insert([
                ['id' => 70, 'name' => 'Unidad', 'code' => '94', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (! Schema::hasTable('taxes')) {
            Schema::create('taxes', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('code')->nullable();
                $table->decimal('percent', 8, 4)->default(0);
                $table->string('type')->nullable();
                $table->timestamps();
            });

            DB::table('taxes')->insert([
                ['id' => 1, 'name' => 'IVA 19%', 'code' => '01', 'percent' => 19, 'type' => 'iva', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /**
     * Simula una petición HTTP en el contexto del tenant.
     * Agrega el header de dominio para que el middleware lo resuelva.
     */
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

    protected function tenantHost(): string
    {
        $centralDomain = env('CENTRAL_DOMAIN', 'pymepossaas-app.test');

        return static::$testSlug . '.' . $centralDomain;
    }

    protected function tenantUrl(string $uri): string
    {
        return 'http://' . $this->tenantHost() . '/' . ltrim($uri, '/');
    }
}
