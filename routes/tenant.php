<?php

declare(strict_types=1);

use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Core\Controllers\CompanyController;
use App\Modules\Core\Controllers\EstablishmentController;
use App\Modules\Core\Controllers\OnboardingController;
use App\Modules\Core\Controllers\ResolutionController;
use App\Modules\Core\Controllers\ThirdPartyController;
use App\Modules\Core\Controllers\WarehouseController;
use App\Modules\Inventory\Controllers\ItemCategoryController;
use App\Modules\Inventory\Controllers\ItemController;
use App\Modules\Invoice\Controllers\InvoiceController;
use App\Modules\Cash\Controllers\BankController;
use App\Modules\Cash\Controllers\CashBoxController;
use App\Modules\POS\Controllers\PosController;
use App\Modules\Purchases\Controllers\PurchaseController;
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

                // Configuración de empresa
                Route::get('/company',  [CompanyController::class, 'show'])->name('company');
                Route::put('/company',  [CompanyController::class, 'update'])->name('company.update');

                // Resoluciones DIAN
                Route::get('/resolutions',                      [ResolutionController::class, 'index'])->name('resolutions');
                Route::post('/resolutions',                     [ResolutionController::class, 'store'])->name('resolutions.store');
                Route::put('/resolutions/{resolution}',         [ResolutionController::class, 'update'])->name('resolutions.update');
                Route::delete('/resolutions/{resolution}',      [ResolutionController::class, 'destroy'])->name('resolutions.destroy');
                Route::patch('/resolutions/{resolution}/toggle',[ResolutionController::class, 'toggle'])->name('resolutions.toggle');
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
                // Cajas de efectivo
                Route::get('/',                                          [CashBoxController::class, 'index'])->name('index');
                Route::post('/boxes',                                    [CashBoxController::class, 'store'])->name('boxes.store');
                Route::put('/boxes/{cashBox}',                           [CashBoxController::class, 'update'])->name('boxes.update');
                Route::delete('/boxes/{cashBox}',                        [CashBoxController::class, 'destroy'])->name('boxes.destroy');
                Route::get('/boxes/{cashBox}',                           [CashBoxController::class, 'show'])->name('boxes.show');
                Route::post('/boxes/{cashBox}/movements',                [CashBoxController::class, 'storeMovement'])->name('boxes.movements.store');
                Route::post('/boxes/transfer',                           [CashBoxController::class, 'transfer'])->name('boxes.transfer');

                // Bancos y cuentas bancarias
                Route::get('/banks',                                     [BankController::class, 'index'])->name('banks.index');
                Route::post('/banks',                                    [BankController::class, 'store'])->name('banks.store');
                Route::put('/banks/{bank}',                              [BankController::class, 'update'])->name('banks.update');
                Route::delete('/banks/{bank}',                           [BankController::class, 'destroy'])->name('banks.destroy');
                Route::post('/banks/{bank}/accounts',                    [BankController::class, 'storeAccount'])->name('banks.accounts.store');
                Route::put('/banks/accounts/{account}',                  [BankController::class, 'updateAccount'])->name('banks.accounts.update');
                Route::delete('/banks/accounts/{account}',               [BankController::class, 'destroyAccount'])->name('banks.accounts.destroy');
                Route::post('/banks/accounts/{account}/movements',       [BankController::class, 'storeMovement'])->name('banks.accounts.movements.store');
            });

            // ─── POS ──────────────────────────────────────────────────────
            Route::prefix('pos')->name('pos.')->group(function () {
                // Selección de terminales
                Route::get('/',                              [PosController::class, 'index'])->name('index');

                // Gestión de terminales (admin)
                Route::post('/terminals',                    [PosController::class, 'storeTerminal'])->name('terminals.store');
                Route::put('/terminals/{terminal}',          [PosController::class, 'updateTerminal'])->name('terminals.update');
                Route::delete('/terminals/{terminal}',       [PosController::class, 'destroyTerminal'])->name('terminals.destroy');

                // Turnos de caja
                Route::post('/{terminal}/open',              [PosController::class, 'openShift'])->name('open');
                Route::post('/{terminal}/close',             [PosController::class, 'closeShift'])->name('close');

                // Pantalla de venta
                Route::get('/{terminal}',                    [PosController::class, 'terminal'])->name('terminal');
                Route::post('/{terminal}/sale',              [PosController::class, 'store'])->name('sale');
            });

            // ─── Contabilidad ─────────────────────────────────────────────
            Route::prefix('accounting')->name('accounting.')->group(function () {
                // Pendiente: Fase 11
            });

            // ─── Compras ──────────────────────────────────────────────────
            Route::prefix('purchases')->name('purchases.')->group(function () {
                Route::get('/',                         [PurchaseController::class, 'index'])->name('index');
                Route::get('/create',                   [PurchaseController::class, 'create'])->name('create');
                Route::post('/',                        [PurchaseController::class, 'store'])->name('store');
                Route::get('/{purchase}',               [PurchaseController::class, 'show'])->name('show');
                Route::post('/{purchase}/approve',      [PurchaseController::class, 'approve'])->name('approve');
                Route::post('/{purchase}/receive',      [PurchaseController::class, 'receive'])->name('receive');
                Route::post('/{purchase}/annul',        [PurchaseController::class, 'annul'])->name('annul');
            });

            // ─── Reportes ─────────────────────────────────────────────────
            Route::prefix('reports')->name('reports.')->group(function () {
                // Pendiente: Fase 13
            });

        }); // fin middleware onboarding

    });

});
