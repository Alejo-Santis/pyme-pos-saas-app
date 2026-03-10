<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Core\Controllers\EstablishmentController;
use App\Modules\Core\Controllers\OnboardingController;
use App\Modules\Core\Controllers\ThirdPartyController;
use App\Modules\Core\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\ItemCategoryController;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Invoice\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Rutas del Tenant (por empresa)
|--------------------------------------------------------------------------
| Estas rutas corren dentro del schema del tenant (empresa cliente).
| Son accesibles desde el subdominio: {empresa}.nextpossaas-app.test
|
| InitializeTenancyByDomain identifica el tenant por dominio completo
| (ej: santinet.nextpossaas-app.test) y cambia la conexión al schema.
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
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

        // ─── Onboarding wizard (configuración inicial) ────────────────────
        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('/',                 [OnboardingController::class, 'show'])->name('show');
            Route::post('/company',         [OnboardingController::class, 'saveCompany'])->name('company');
            Route::post('/resolution',      [OnboardingController::class, 'saveResolution'])->name('resolution');
            Route::post('/complete',        [OnboardingController::class, 'complete'])->name('complete');
            Route::get('/municipalities/{departmentId}', [OnboardingController::class, 'municipalities'])->name('municipalities');
        });

        // ─── Rutas que requieren onboarding completado ────────────────────
        Route::middleware(['onboarding'])->group(function () {

            Route::get('/dashboard', \App\Modules\Core\Controllers\DashboardController::class)->name('dashboard');

            // ─── Configuración de empresa (Fase 5) ───────────────────────
            Route::prefix('config')->name('config.')->group(function () {
                // Establecimientos
                Route::get('/establishments',                   [EstablishmentController::class, 'index'])->name('establishments');
                Route::post('/establishments',                  [EstablishmentController::class, 'store'])->name('establishments.store');
                Route::put('/establishments/{establishment}',   [EstablishmentController::class, 'update'])->name('establishments.update');
                Route::delete('/establishments/{establishment}',[EstablishmentController::class, 'destroy'])->name('establishments.destroy');

                // Bodegas
                Route::get('/warehouses',              [WarehouseController::class, 'index'])->name('warehouses');
                Route::post('/warehouses',             [WarehouseController::class, 'store'])->name('warehouses.store');
                Route::put('/warehouses/{warehouse}',  [WarehouseController::class, 'update'])->name('warehouses.update');
                Route::delete('/warehouses/{warehouse}',[WarehouseController::class, 'destroy'])->name('warehouses.destroy');
            });

            // ─── Terceros (Fase 6) ────────────────────────────────────────
            Route::prefix('third-parties')->name('third-parties.')->group(function () {
                Route::get('/',                            [ThirdPartyController::class, 'index'])->name('index');
                Route::get('/create',                      [ThirdPartyController::class, 'create'])->name('create');
                Route::post('/',                           [ThirdPartyController::class, 'store'])->name('store');
                Route::get('/{thirdParty}/edit',           [ThirdPartyController::class, 'edit'])->name('edit');
                Route::put('/{thirdParty}',                [ThirdPartyController::class, 'update'])->name('update');
                Route::delete('/{thirdParty}',             [ThirdPartyController::class, 'destroy'])->name('destroy');
                Route::patch('/{thirdParty}/toggle',       [ThirdPartyController::class, 'toggleStatus'])->name('toggle');
            });

            // ─── Inventario (Fase 7) ──────────────────────────────────────
            Route::prefix('inventory')->name('inventory.')->group(function () {

                // Ítems / productos
                Route::get('/',                    [ItemController::class, 'index'])->name('index');
                Route::get('/create',              [ItemController::class, 'create'])->name('create');
                Route::post('/',                   [ItemController::class, 'store'])->name('store');
                Route::get('/{item}/edit',         [ItemController::class, 'edit'])->name('edit');
                Route::put('/{item}',              [ItemController::class, 'update'])->name('update');
                Route::delete('/{item}',           [ItemController::class, 'destroy'])->name('destroy');
                Route::patch('/{item}/toggle',     [ItemController::class, 'toggleStatus'])->name('toggle');

                // Categorías de ítems
                Route::prefix('categories')->name('categories.')->group(function () {
                    Route::get('/',                         [ItemCategoryController::class, 'index'])->name('index');
                    Route::post('/',                        [ItemCategoryController::class, 'store'])->name('store');
                    Route::put('/{itemCategory}',           [ItemCategoryController::class, 'update'])->name('update');
                    Route::delete('/{itemCategory}',        [ItemCategoryController::class, 'destroy'])->name('destroy');
                });
            });

            // ─── Facturación electrónica (Fase 8) ────────────────────────
            Route::prefix('invoices')->name('invoices.')->group(function () {
                Route::get('/',                    [InvoiceController::class, 'index'])->name('index');
                Route::get('/create',              [InvoiceController::class, 'create'])->name('create');
                Route::post('/',                   [InvoiceController::class, 'store'])->name('store');
                Route::get('/{document}',          [InvoiceController::class, 'show'])->name('show');
                Route::get('/{document}/edit',     [InvoiceController::class, 'edit'])->name('edit');
                Route::put('/{document}',          [InvoiceController::class, 'update'])->name('update');
                Route::delete('/{document}',       [InvoiceController::class, 'destroy'])->name('destroy');
            });

            // ─── Caja y bancos ────────────────────────────────────────────
            Route::prefix('cash')->name('cash.')->group(function () {
                // Pendiente: Fase 9
            });

            // ─── POS ──────────────────────────────────────────────────────
            Route::prefix('pos')->name('pos.')->group(function () {
                // Pendiente: Fase 10
            });

            // ─── Contabilidad ─────────────────────────────────────────────
            Route::prefix('accounting')->name('accounting.')->group(function () {
                // Pendiente: Fase 11
            });

            // ─── Compras ──────────────────────────────────────────────────
            Route::prefix('purchases')->name('purchases.')->group(function () {
                // Pendiente: Fase 12
            });

            // ─── Reportes ─────────────────────────────────────────────────
            Route::prefix('reports')->name('reports.')->group(function () {
                // Pendiente: Fase 13
            });

        }); // fin middleware onboarding

    });

});
