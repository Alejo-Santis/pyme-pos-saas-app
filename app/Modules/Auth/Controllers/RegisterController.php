<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\RegisterTenantRequest;
use App\Modules\Tenant\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterController extends Controller
{
    /**
     * Muestra el formulario de registro de nueva empresa.
     * Incluye los planes disponibles para que el usuario elija.
     */
    public function show(): Response
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'features', 'trial_days']);

        return Inertia::render('Auth/Register', [
            'plans' => $plans,
        ]);
    }

    /**
     * Crea el tenant, el schema, las migraciones y el primer usuario administrador.
     *
     * Flujo:
     * 1. Valida los datos del formulario
     * 2. Crea el Tenant (dispara TenantCreated → CreateDatabase + MigrateDatabase)
     * 3. Crea el dominio del tenant (slug.pymepossaas.test)
     * 4. Inicializa la tenancy y crea el admin dentro del schema del tenant
     * 5. Crea la suscripción trial en el schema public
     * 6. Redirige al login del subdomain del tenant
     */
    public function store(RegisterTenantRequest $request): SymfonyResponse
    {
        $plan = Plan::where('slug', $request->plan_slug)->firstOrFail();

        $tenant = null;
        $centralDomain = env('CENTRAL_DOMAIN', 'pymepossaas-app.test');
        $subdomain = $request->company_slug;                        // ej: "santinet"
        $domain    = $subdomain . '.' . $centralDomain;             // ej: "santinet.pymepossaas-app.test"

        try {
            // ── Fase 1: Crear el tenant SIN transacción activa ────────────────
            // Tenant::create() dispara TenantCreated → CreateDatabase (crea el
            // schema PostgreSQL) → MigrateDatabase (usa conexión tenant separada).
            // Si hay una transacción abierta en la conexión landlord, el schema
            // creado no es visible para la conexión tenant hasta que se haga
            // COMMIT (PostgreSQL aísla cambios no-commiteados entre sesiones).
            // Por eso Tenant::create() debe ejecutarse SIN transacción activa.
            $tenant = Tenant::create([
                'name'           => $request->company_name,
                'email'          => $request->admin_email,
                'status'         => 'trial',
                'plan_id'        => $plan->id,
                'trial_ends_at'  => now()->addDays($plan->trial_days),
            ]);

            // ── Fase 1b: Dominio y suscripción (transaccional en landlord) ────
            DB::beginTransaction();

            // InitializeTenancyByDomain busca el dominio completo (ej: "santinet.pymepossaas-app.test").
            $tenant->domains()->create(['domain' => $domain]);

            $tenant->subscriptions()->create([
                'id'             => (string) Str::uuid(),
                'plan_id'        => $plan->id,
                'billing_period' => 'monthly',
                'price'          => $plan->price_monthly,
                'status'         => 'trial',
                'trial_ends_at'  => now()->addDays($plan->trial_days),
                'starts_at'      => now(),
                'ends_at'        => now()->addDays($plan->trial_days),
            ]);

            DB::commit();

            // ── Fase 2: Operaciones dentro del schema del tenant ──────────────
            tenancy()->initialize($tenant);

            (new \Database\Seeders\TenantDatabaseSeeder())->run();

            $admin = \App\Models\User::create([
                'name'     => $request->admin_name,
                'email'    => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'is_active' => true,
            ]);

            $admin->assignRole('admin');

            tenancy()->end();

        } catch (Throwable $e) {
            DB::rollBack();
            tenancy()->end();

            // Limpiar tenant huérfano si fue creado antes del error
            if ($tenant?->id) {
                try {
                    DB::statement("DROP SCHEMA IF EXISTS \"tenant_{$tenant->id}\" CASCADE");
                    $tenant->domains()->delete();
                    $tenant->subscriptions()->delete();
                    $tenant->delete();
                } catch (Throwable) { /* ignorar errores de limpieza */ }
            }

            Log::error('Error al registrar tenant: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $mensajeError = config('app.debug')
                ? 'Error: ' . $e->getMessage()
                : 'Ocurrió un error al crear la empresa. Por favor intenta de nuevo.';

            return back()->withErrors(['general' => $mensajeError])->withInput();
        }

        // 5. Redirigir al login del subdominio del tenant.
        // ?registered=1 le indica al LoginController que muestre el mensaje de bienvenida,
        // ya que el flash de sesión no cruza entre dominios distintos (central → tenant).
        $loginUrl = 'http://' . $domain . '/login?registered=1';

        // Inertia::location() fuerza una redirección real del browser (no via Axios),
        // lo que evita el error CORS al cruzar del dominio central al subdominio del tenant.
        return Inertia::location($loginUrl);
    }
}
