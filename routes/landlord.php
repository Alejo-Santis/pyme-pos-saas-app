<?php

use App\Modules\Auth\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del Landlord (SaaS / Admin)
|--------------------------------------------------------------------------
| Son accesibles desde el dominio raíz: nextpossaas.test
| Aquí viven: registro de empresas, planes, panel super-admin.
*/

// ─── Onboarding público ────────────────────────────────────────────────────
// Sin middleware 'guest': el dominio central no tiene tabla users en public schema.
// El registro es una página pública para crear nuevas empresas tenant.
Route::get('/register',  [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

// ─── Panel super-admin ─────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin'])->group(function () {
    // Pendiente: Fase 3 — Panel SaaS admin
});
