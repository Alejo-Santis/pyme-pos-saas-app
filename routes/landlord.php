<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Modules\Admin\Controllers\AdminAuthController;
use App\Modules\Admin\Controllers\AdminBillingController;
use App\Modules\Admin\Controllers\AdminDashboardController;
use App\Modules\Admin\Controllers\AdminPlanController;
use App\Modules\Admin\Controllers\AdminTenantController;
use App\Modules\Admin\Controllers\AdminUserController;
use App\Modules\Admin\Controllers\LandlordAuditController;
use App\Modules\Auth\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del Landlord (SaaS / Admin)
|--------------------------------------------------------------------------
| Son accesibles desde el dominio raíz: pymepossaas-app.test
| Aquí viven: registro de empresas, planes, panel super-admin.
*/

// ─── Onboarding público ────────────────────────────────────────────────────
// Sin middleware 'guest': el dominio central no tiene tabla users en public schema.
Route::get('/register',  [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

// ─── Panel super-admin — Auth ──────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    // Login (sin auth para poder acceder)
    Route::get('/login',  [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store')->middleware('throttle:admin-login');

    // Rutas protegidas con guard admin
    Route::middleware(AdminAuthenticate::class)->group(function () {

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // Dashboard general
        Route::get('/',          AdminDashboardController::class)->name('dashboard');
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard.alt');

        // Gestión de tenants (empresas)
        Route::prefix('tenants')->name('tenants.')->group(function () {
            Route::get('/',                        [AdminTenantController::class, 'index'])->name('index');
            Route::get('/{id}',                    [AdminTenantController::class, 'show'])->name('show');
            Route::patch('/{id}/status',           [AdminTenantController::class, 'updateStatus'])->name('update-status');
            Route::patch('/{id}/plan',             [AdminTenantController::class, 'updatePlan'])->name('update-plan');
            Route::patch('/{id}/domain',           [AdminTenantController::class, 'updateDomain'])->name('update-domain');
            Route::patch('/{id}/subscription',     [AdminTenantController::class, 'updateSubscription'])->name('update-subscription');
            Route::post('/{id}/extend-trial',      [AdminTenantController::class, 'extendTrial'])->name('extend-trial');
            Route::post('/{id}/notification',      [AdminTenantController::class, 'sendNotification'])->name('send-notification');
            Route::post('/{id}/technical-action',  [AdminTenantController::class, 'runTechnicalAction'])->name('technical-action');
            Route::post('/{id}/impersonate',       [AdminTenantController::class, 'impersonate'])->name('impersonate');
        });

        // Gestión de planes
        Route::prefix('plans')->name('plans.')->group(function () {
            Route::get('/',              [AdminPlanController::class, 'index'])->name('index');
            Route::post('/',             [AdminPlanController::class, 'store'])->name('store');
            Route::put('/{id}',          [AdminPlanController::class, 'update'])->name('update');
            Route::delete('/{id}',       [AdminPlanController::class, 'destroy'])->name('destroy');
            Route::patch('/{id}/toggle', [AdminPlanController::class, 'toggleActive'])->name('toggle');
        });

        Route::get('/billing', [AdminBillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/payments', [AdminBillingController::class, 'storePayment'])->name('billing.payments.store');

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',                    [AdminUserController::class, 'index'])->name('index');
            Route::post('/',                   [AdminUserController::class, 'store'])->name('store');
            Route::put('/{id}',                [AdminUserController::class, 'update'])->name('update');
            Route::patch('/{id}/password',     [AdminUserController::class, 'updatePassword'])->name('password');
            Route::patch('/{id}/toggle',       [AdminUserController::class, 'toggle'])->name('toggle');
        });

        Route::get('/audit', [LandlordAuditController::class, 'index'])->name('audit.index');
    });
});
