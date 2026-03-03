<?php

/*
|--------------------------------------------------------------------------
| Rutas del Landlord (SaaS / Admin)
|--------------------------------------------------------------------------
| Estas rutas corren en el schema PUBLIC de PostgreSQL.
| Son accesibles desde el dominio raíz (sin subdominio de tenant).
| Aquí viven: registro de empresas, planes, subscripciones, panel super-admin.
|
| Middleware disponible:
|   - 'web'       → sesión, CSRF, cookies
|   - 'auth'      → usuario autenticado
|   - 'role:super_admin' → solo super administradores de la plataforma
*/

use Illuminate\Support\Facades\Route;

// ─── Onboarding público ───────────────────────────────────────────────────
Route::prefix('register')->name('register.')->group(function () {
    // GET  /register           → Formulario de registro de nueva empresa
    // POST /register           → Crear tenant + admin + schema
    // GET  /register/plans     → Selección de plan
    // POST /register/billing   → Configurar pago
});

// ─── Panel super-admin ────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:super_admin'])->group(function () {
    // GET  /admin/dashboard    → Métricas globales de la plataforma
    // GET  /admin/tenants      → Listar empresas registradas
    // GET  /admin/plans        → CRUD de planes
    // GET  /admin/billing      → Facturación de la plataforma
});
