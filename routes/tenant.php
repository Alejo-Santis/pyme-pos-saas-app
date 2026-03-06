<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\LoginController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas del Tenant (por empresa)
|--------------------------------------------------------------------------
| Estas rutas corren dentro del schema del tenant (empresa cliente).
| Son accesibles desde el subdominio: {empresa}.nextpossaas.test
|
| InitializeTenancyBySubdomain identifica el tenant por subdominio
| y cambia la conexión de base de datos al schema de esa empresa.
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ─── Auth (públicas dentro del tenant) ────────────────────────────────
    Route::middleware('guest')->group(function () {
        Route::get('/login',  [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::post('/logout', [LoginController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    // ─── Rutas protegidas ─────────────────────────────────────────────────
    Route::middleware(['auth'])->group(function () {

        Route::get('/dashboard', \App\Modules\Core\Controllers\DashboardController::class)->name('dashboard');

        // ─── Configuración de empresa ─────────────────────────────────────
        Route::prefix('config')->name('config.')->group(function () {
            // Pendiente: Fase 5
        });

        // ─── Terceros ─────────────────────────────────────────────────────
        Route::prefix('third-parties')->name('third-parties.')->group(function () {
            // Pendiente: Fase 6
        });

        // ─── Inventario ───────────────────────────────────────────────────
        Route::prefix('inventory')->name('inventory.')->group(function () {
            // Pendiente: Fase 7
        });

        // ─── Facturación electrónica ──────────────────────────────────────
        Route::prefix('invoices')->name('invoices.')->group(function () {
            // Pendiente: Fase 8
        });

        // ─── Caja y bancos ────────────────────────────────────────────────
        Route::prefix('cash')->name('cash.')->group(function () {
            // Pendiente: Fase 9
        });

        // ─── POS ──────────────────────────────────────────────────────────
        Route::prefix('pos')->name('pos.')->group(function () {
            // Pendiente: Fase 10
        });

        // ─── Contabilidad ─────────────────────────────────────────────────
        Route::prefix('accounting')->name('accounting.')->group(function () {
            // Pendiente: Fase 11
        });

        // ─── Compras ──────────────────────────────────────────────────────
        Route::prefix('purchases')->name('purchases.')->group(function () {
            // Pendiente: Fase 12
        });

        // ─── Reportes ─────────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            // Pendiente: Fase 13
        });

    });

});
