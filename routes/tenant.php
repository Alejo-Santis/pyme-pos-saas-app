<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas del Tenant (por empresa)
|--------------------------------------------------------------------------
| Estas rutas corren dentro del schema del tenant (empresa cliente).
| Son accesibles desde el subdominio: {empresa}.colsaas.co
|
| InitializeTenancyBySubdomain identifica el tenant por subdominio
| y cambia la conexión de base de datos al schema de esa empresa.
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ─── Dashboard ────────────────────────────────────────────────────────
    Route::middleware(['auth'])->group(function () {

        Route::get('/dashboard', fn () => inertia('Dashboard'))->name('dashboard');

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
