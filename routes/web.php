<?php

use App\Modules\Tenant\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

// Si el host no corresponde a ningún tenant (dominio central), continuar sin
// inicializar tenancy en vez de lanzar TenantCouldNotBeIdentifiedException —
// esta única ruta "/" atiende tanto el dominio central como los de tenant.
InitializeTenancyByDomain::$onFail = fn ($e, $request, $next) => $next($request);

// Única ruta "/" de toda la app (routes/tenant.php NO define la suya — ver nota
// allá). InitializeTenancyByDomain va ANTES del grupo 'web' porque stancl/tenancy
// la registra con prioridad alta (debe ejecutar antes de StartSession, para que
// la sesión se lea/escriba en el schema correcto del tenant, no en public).
Route::middleware([InitializeTenancyByDomain::class])->get('/', function () {
    if (tenancy()->initialized) {
        return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
    }

    $plans = Plan::where('is_active', true)
        ->orderBy('sort_order')
        ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'features', 'trial_days']);

    return Inertia::render('Landing', [
        'plans' => $plans,
        'tenantDomainMode' => env('TENANT_DOMAIN_MODE', 'subdomain'),
        'tenantDomainSuffix' => env('TENANT_DOMAIN_MODE', 'subdomain') === 'suffix'
            ? env('TENANT_DOMAIN_SUFFIX', env('CENTRAL_DOMAIN', 'pymepossaas-app.test'))
            : env('CENTRAL_DOMAIN', 'pymepossaas-app.test'),
    ]);
})->name('home');
