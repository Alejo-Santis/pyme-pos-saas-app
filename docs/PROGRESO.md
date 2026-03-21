# PymePOS SaaS — Estado del Proyecto

> Última actualización: 2026-03-09
> Stack: Laravel 12 + Svelte 5 (Runes) + Inertia.js v2 + PostgreSQL 16 + Tailwind CSS 4

---

## ✅ COMPLETADO

### Infraestructura base
- [x] Proyecto Laravel 12 con PostgreSQL 16
- [x] Multi-tenancy `stancl/tenancy` v3 — schema por tenant
- [x] Separación landlord (`public`) / tenant (`tenant_{slug}`)
- [x] Vite 6 + Svelte 5 + Tailwind CSS 4 + MDI icons
- [x] Inertia.js v2 configurado (SPA mode, progress bar)
- [x] Navegación SPA con `use:inertia` action (sin recargas)
- [x] Spatie Permission v6 (RBAC por tenant)
- [x] Laravel Sanctum (SPA + API tokens)
- [x] Redis (colas + cache)
- [x] Laravel Telescope (dev) / Sentry (prod)

### Migraciones — Landlord (schema public)
- [x] `tenants` + `domains`
- [x] `plans` (Básico, Profesional, Empresarial)
- [x] `subscriptions`
- [x] `landlord_users` (admin panel)
- [x] Catálogos globales DIAN: `countries`, `departments`, `municipalities`
- [x] Catálogos DIAN: `type_organizations`, `type_regimes`, `type_liabilities`
- [x] Catálogos DIAN: `type_document_identifications`, `type_documents`, `type_document_operations`
- [x] Catálogos navegación

### Migraciones — Tenant (por empresa)
- [x] `cache`, `jobs` (tenant)
- [x] `users` (UUID, `is_active`, `onboarding_completed`)
- [x] `permissions`, `roles` (Spatie)
- [x] `personal_access_tokens` (Sanctum)
- [x] `activity_log` (Spatie)
- [x] `companies` (datos tributarios DIAN)
- [x] `resolutions` (resoluciones DIAN por empresa)
- [x] `establishments` + `warehouses` (establecimientos y bodegas)
- [x] `third_parties` (clientes/proveedores)
- [x] `items` (artículos/productos)
- [x] `documents` + `document_lines` (FEV, NC, ND, soporte)
- [x] `journal_entries` + `journal_lines` (contabilidad)
- [x] `pos_sessions` + `pos_transactions` (POS)
- [x] `purchase_orders` + `purchase_order_lines` (compras)
- [x] `stock_transfers` + `stock_transfer_lines` (traslados)
- [x] `bank_accounts` + `bank_transactions` (caja/bancos)
- [x] `chart_of_accounts` (PUC Colombia)

### Módulo Auth (tenant)
- [x] **Registro de empresa** (`/register` en dominio central)
  - Crea tenant + schema + migra DB + crea admin user + suscripción trial
- [x] **Login** por subdominio (`empresa.pymepossaas-app.test/login`)
  - Guard `web`, Sanctum SPA, identificación por dominio
- [x] **Logout**
- [x] Middleware `CheckOnboardingCompleted` — redirige a `/onboarding` si no completó

### Módulo Onboarding (tenant)
- [x] **Wizard 3 pasos**: Datos empresa → Resolución DIAN → Completado
- [x] Paso 1: Formulario empresa (NIT + DV auto, razón social, tipo org/régimen/responsabilidad, departamento/municipio, email, teléfono, dirección)
- [x] DV calculado automáticamente con algoritmo DIAN (módulo 11)
- [x] Municipios filtrados en cliente desde prop (sin fetch extra)
- [x] Paso 2: Resolución DIAN (N° resolución, fechas, prefijo, rango, vigencia)
- [x] Opción "Configurar después" (skip)
- [x] Paso 3: Pantalla de bienvenida con link al dashboard

### Panel Super-Admin (landlord)
- [x] **Login admin** (`/admin/login`)
- [x] **Dashboard admin** — métricas: total tenants, trials activos, ingresos, crecimiento mensual
- [x] **Lista empresas** (`/admin/tenants`) — búsqueda, filtro por estado/plan, paginación
- [x] **Detalle empresa** (`/admin/tenants/{id}`) — info, suscripciones, cambiar plan/estado
- [x] **Lista planes** (`/admin/plans`) — visualización y toggle activo/inactivo
- [x] AdminLayout con sidebar claro

### Dashboard Tenant
- [x] Dashboard con 4 tarjetas gradiente (ventas, productos, terceros, contabilidad)
- [x] Gráficas Chart.js (ventas por mes, compras por mes)
- [x] Selector de año y período
- [x] AppLayout con sidebar azul (modo vertical + horizontal toggle)

### Layouts y UI
- [x] `AuthLayout.svelte` — fondo con SVG + tarjeta blanca
- [x] `AppLayout.svelte` — sidebar vertical y topnav horizontal (toggle)
- [x] `AdminLayout.svelte` — sidebar claro para super-admin
- [x] Paleta de colores Tailwind custom (`primary`, `body`, `primary-dark`, etc.)
- [x] MDI Icons integrados

---

## 🔄 EN PROGRESO / BUGS CONOCIDOS

### Navegación SPA
- [ ] **Verificar** que `use:inertia` realmente elimina los recargas de página
  _(se cambió de `<Link>` componente Svelte 4 a `use:inertia` action nativa — pendiente test)_
- [ ] `optimize:clear` + limpiar caché al probar

### Panel Admin — Planes
- [ ] **CRUD completo de planes**: crear, editar, eliminar
  _(actualmente solo toggle activo/inactivo y listado)_

---

## ⏳ PENDIENTE (por módulo / fase)

### Fase 5 — Configuración de empresa
- [ ] Editar datos de empresa (post-onboarding)
- [ ] Gestión de resoluciones DIAN (CRUD)
- [ ] Gestión de establecimientos y bodegas
- [ ] Gestión de usuarios del tenant (invite, roles, permisos)
- [ ] Configuración de impuestos (IVA, IC, etc.)
- [ ] Configuración de numeración

### Fase 6 — Terceros
- [ ] CRUD terceros (clientes, proveedores, empleados)
- [ ] Importación masiva desde Excel
- [ ] Validación DIAN (RUT, NIT)
- [ ] Búsqueda y filtros avanzados

### Fase 7 — Inventario
- [ ] CRUD artículos/productos
- [ ] Categorías y familias
- [ ] Unidades de medida
- [ ] Precios y listas de precios
- [ ] Stock por bodega
- [ ] Kardex / movimientos de inventario
- [ ] Importación masiva
- [ ] Alertas de stock mínimo

### Fase 8 — Facturación Electrónica DIAN
- [ ] Crear factura de venta (FEV)
- [ ] Notas crédito (NC) y débito (ND)
- [ ] Documento soporte (DS)
- [ ] Integración Nextpyme (DianProviderInterface)
- [ ] Builder UBL 2.1 (adaptar de xedoc-laravel-svelte)
- [ ] Jobs de envío asíncrono con retry exponencial
- [ ] CUFE / CUDE generación y validación
- [ ] Descarga PDF factura (DomPDF)
- [ ] Envío por email al cliente
- [ ] Dashboard de estado facturas (enviada, rechazada, etc.)
- [ ] Ambiente habilitación DIAN (test → producción)

### Fase 9 — Caja y Bancos
- [ ] Cuentas bancarias
- [ ] Ingresos y egresos
- [ ] Conciliación bancaria
- [ ] Cajas menores
- [ ] Flujo de caja

### Fase 10 — POS (Punto de Venta)
- [ ] Sesión de caja (apertura/cierre)
- [ ] Interfaz POS (selección artículos, cantidades, descuentos)
- [ ] Formas de pago (efectivo, tarjeta, transferencia)
- [ ] Documentos equivalentes electrónicos (POS DIAN)
- [ ] Impresión tickets (QZ Tray)
- [ ] Modo offline con sincronización

### Fase 11 — Contabilidad
- [ ] Plan Único de Cuentas (PUC Colombia) — seed completo
- [ ] Asientos contables manuales
- [ ] Motor contable automático (AccountingEngineTrait)
- [ ] Libro diario y mayor
- [ ] Balance de prueba
- [ ] Estado de resultados
- [ ] Balance general
- [ ] Cierre de período

### Fase 12 — Compras
- [ ] Órdenes de compra
- [ ] Recepción de mercancía
- [ ] Facturación de proveedor
- [ ] Devoluciones a proveedor

### Fase 13 — Reportes
- [ ] Reporte de ventas por período
- [ ] Reporte de inventario
- [ ] Reporte de cartera
- [ ] Reporte contable
- [ ] Exportación Excel / PDF

### Fase 14 — Nómina (largo plazo)
- [ ] Empleados y contratos
- [ ] Liquidación nómina colombiana
- [ ] Parafiscales (SENA, ICBF, Caja)
- [ ] Seguridad social (Salud, Pensión, ARL)
- [ ] Cesantías, primas, vacaciones
- [ ] PILA / colilla de pago

### Mejoras técnicas pendientes
- [ ] Tests (Pest PHP) — mínimo rutas críticas
- [ ] Rate limiting en API
- [ ] Colas para envío DIAN (Redis + Horizon)
- [ ] Logs de actividad por tenant
- [ ] Backup automático schemas
- [ ] Scramble API docs (`/docs/api`)
- [ ] Multi-idioma (es/en)
- [ ] 2FA para admins

---

## 📁 Estructura de archivos clave

```
app/Modules/
├── Auth/           Controllers: LoginController, RegisterController
├── Admin/          Controllers: AdminAuth, AdminDashboard, AdminTenant, AdminPlan
├── Core/           Controllers: DashboardController, OnboardingController
│                   Models: Company, Resolution
└── Tenant/         Models: Tenant, Plan, Subscription

resources/js/
├── Layouts/        AppLayout, AdminLayout, AuthLayout
├── Pages/
│   ├── Auth/       Login, Register
│   ├── Admin/      Dashboard, Login | Tenants/Index, Show | Plans/Index
│   ├── Dashboard
│   └── Onboarding

database/migrations/
├── landlord/       tenants, plans, subscriptions, global catalogs
└── tenant/         users, companies, resolutions, inventory, documents...
```

---

## 🔧 Comandos útiles

```bash
# Limpiar cache completo
php artisan optimize:clear

# Migrar todos los tenants
php artisan tenants:migrate

# Migrar migración específica en tenants
php artisan tenants:migrate --path=database/migrations/tenant/archivo.php

# Sembrar catálogos globales
php artisan db:seed --class=GlobalCatalogsSeeder

# Compilar frontend
npm run dev      # desarrollo
npm run build    # producción
```
