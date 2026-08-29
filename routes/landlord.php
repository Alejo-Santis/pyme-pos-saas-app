<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Modules\Admin\Controllers\AdminAuthController;
use App\Modules\Admin\Controllers\AdminSecurityController;
use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Admin\Controllers\AdminPlanController;
use App\Modules\Admin\Controllers\AdminTenantController;
use App\Modules\Auth\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del Landlord (SaaS / Admin)
|--------------------------------------------------------------------------
| Son accesibles desde el dominio raíz: nextpossaas-app.test
| Aquí viven: registro de empresas, planes, panel super-admin.
*/

// ─── Onboarding público ────────────────────────────────────────────────────
// Sin middleware 'guest': el dominio central no tiene tabla users en public schema.
Route::get('/register',  [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store')->middleware('throttle:5,1');

// ─── Panel super-admin — Auth ──────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Login (sin auth para poder acceder)
    Route::get('/login',  [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store')->middleware('throttle:5,1');

    // Segundo factor (sin guard admin todavía: el usuario está "a medias" en sesión)
    Route::get('/two-factor',  [AdminAuthController::class, 'showTwoFactor'])->name('two-factor');
    Route::post('/two-factor', [AdminAuthController::class, 'verifyTwoFactor'])->name('two-factor.verify')->middleware('throttle:5,1');

    // Rutas protegidas con guard admin
    Route::middleware(AdminAuthenticate::class)->group(function () {

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Seguridad — activar/desactivar 2FA
        Route::prefix('security')->name('security.')->group(function () {
            Route::get('/',          [AdminSecurityController::class, 'show'])->name('show');
            Route::post('/enable',   [AdminSecurityController::class, 'enable'])->name('enable');
            Route::post('/confirm',  [AdminSecurityController::class, 'confirm'])->name('confirm');
            Route::post('/disable',  [AdminSecurityController::class, 'disable'])->name('disable');
        });

        // Dashboard general
        Route::get('/',          AdminDashboardController::class)->name('dashboard');
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard.alt');

        // Gestión de tenants (empresas)
        Route::prefix('tenants')->name('tenants.')->group(function () {
            Route::get('/',                        [AdminTenantController::class, 'index'])->name('index');
            Route::get('/{id}',                    [AdminTenantController::class, 'show'])->name('show');
            Route::patch('/{id}/status',           [AdminTenantController::class, 'updateStatus'])->name('update-status');
            Route::patch('/{id}/plan',             [AdminTenantController::class, 'updatePlan'])->name('update-plan');
        });

        // Gestión de planes
        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/',              [AdminPlanController::class, 'index'])->name('index');
            Route::post('/',             [AdminPlanController::class, 'store'])->name('store');
            Route::put('/{id}',          [AdminPlanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [AdminPlanController::class, 'destroy'])->name('destroy');
            Route::patch('/{id}/toggle', [AdminPlanController::class, 'toggleActive'])->name('toggle');
        });
    });
});
