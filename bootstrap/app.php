<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule): void {
        // Backup diario (BD + archivos) — requiere BACKUP_DISK apuntando a
        // almacenamiento durable (S3/R2) en producción; 'local' solo sirve
        // para probar el comando en desarrollo.
        $schedule->command('backup:run')->daily()->at('02:00')->onOneServer();
        $schedule->command('backup:clean')->daily()->at('01:30')->onOneServer();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Rutas del landlord (dominio central: planes, registro de empresas, super-admin)
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/landlord.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Inertia: maneja partial reloads y comparte props globales con Svelte
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Alias de middleware custom
        $middleware->alias([
            'admin.auth'         => \App\Http\Middleware\AdminAuthenticate::class,
            'tenant.operational' => \App\Http\Middleware\EnsureTenantCanOperate::class,
            'onboarding'         => \App\Http\Middleware\CheckOnboardingCompleted::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // Reporta excepciones no manejadas a Sentry — no hace nada si
        // SENTRY_LARAVEL_DSN no está definido en .env (SDK hace no-op).
        Integration::handles($exceptions);

        // ── Tenant no encontrado por dominio ────────────────────────────────
        $exceptions->render(function (
            \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException $e,
            Request $request
        ) {
            return response()->view('errors.tenant-not-found', [
                'domain' => $request->getHost(),
            ], 404);
        });

        // ── Error de BD en contexto tenant ──────────────────────────────────
        // Ocurre cuando: schema fue eliminado, migración incompleta, tenant no existe.
        // En lugar del stack trace, mostramos una página amigable.
        $exceptions->render(function (
            \Illuminate\Database\QueryException $e,
            Request $request
        ) {
            $prev = $e->getPrevious();
            $sqlState = ($prev instanceof \PDOException && isset($prev->errorInfo[0]))
                ? (string) $prev->errorInfo[0]
                : '';

            // 42P01 = tabla/relación no existe (schema eliminado / migración pendiente)
            // 3D000 = base de datos no existe
            if (in_array($sqlState, ['42P01', '3D000', '42501'])) {
                // Si es un dominio de tenant (no el dominio central), destruir sesión
                // y mostrar página específica — probablemente el schema fue eliminado.
                $centralDomains = config('tenancy.central_domains', []);
                $host           = $request->getHost();
                $isTenantDomain = ! in_array($host, $centralDomains)
                    && ! in_array(explode('.', $host)[0], ['localhost', '127']);

                if ($isTenantDomain) {
                    // Invalidar sesión para forzar re-login
                    try {
                        $request->session()->flush();
                    } catch (\Throwable) {}

                    return response()->view('errors.tenant-schema-error', [
                        'domain' => $host,
                    ], 500);
                }
            }

            // Para otros errores de BD en el dominio central → 500 estándar
            return null; // deja que Laravel maneje normalmente
        });

        // ── Respuestas 404 y 500 con vistas Blade propias ───────────────────
        // Estas se activan cuando NO hay vista Blade en resources/views/errors/
        // o cuando la excepción no tiene handler específico.
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e,
            Request $request
        ) {
            if (! $request->expectsJson()) {
                return response()->view('errors.404', [], 404);
            }
            return null;
        });

        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\HttpException $e,
            Request $request
        ) {
            $code = $e->getStatusCode();
            $view = "errors.{$code}";

            if (! $request->expectsJson() && view()->exists($view)) {
                return response()->view($view, [], $code);
            }
            return null;
        });

    })->create();
