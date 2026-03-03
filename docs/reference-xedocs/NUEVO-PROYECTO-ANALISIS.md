# Análisis del Proyecto Xedoc y Hoja de Ruta para Nuevo SaaS ERP Colombiano

> Documento generado el 2026-03-02
> Base de referencia: `xedoc-laravel-svelte`

---

## 1. Resumen del Proyecto Actual (Xedoc)

### Stack Tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | Laravel | 11.x |
| Runtime | PHP | 8.2+ |
| Frontend | Svelte | 5.x |
| Bridge UI | Inertia.js | 1.1 |
| Build tool | Vite | 6.2 |
| Base de datos | PostgreSQL | 14 |
| Autenticación | Sanctum + JWT | 4.0 / 2.1 |
| Autorización | Spatie Permission | 6.15 |
| Colas | Laravel Queue (DB) | — |
| Almacenamiento | AWS S3 / Local | — |
| PDF backend | DomPDF | 3.1 |
| PDF frontend | pdfmake | 0.2 |
| POS printing | QZ Tray + escpos-php | — |
| Firma digital | jsrsasign | 11.1 |
| Estilos | Bootstrap | 5.0 |
| Charts | Chart.js | 4.4 |
| Facturación DIAN | Nextpyme API | — |

### Módulos del Sistema Actual

1. **Facturación Electrónica DIAN** — UBL 2.1, CUFE, notas crédito/débito, documentos soporte
2. **Contabilidad** — Plan de cuentas PUC Colombia, asientos contables, balances
3. **Inventario** — Multi-bodega, kardex, tomas físicas, traslados, listas de precios
4. **POS** — Terminales, usuarios por terminal, impresión térmica, cierre de caja
5. **Gestión de Caja y Bancos** — Movimientos, recibos, extractos, conciliación
6. **Compras** — Órdenes de compra, notas crédito proveedor, buzón de correo
7. **Nómina** — Contratos, períodos, retenciones, ARL, EPS, pensión
8. **Terceros** — Clientes, proveedores, empleados, datos DIAN
9. **Reportes** — Ventas, compras, impuestos, retenciones, contables
10. **RADIAN** — Eventos de facturas de compra electrónica

### Patrones de Arquitectura Existentes

| Patrón | Implementación |
|---|---|
| Multi-empresa | Columna `company_id` en todas las tablas |
| Multi-bodega | Columna `warehouse_id` por item/documento |
| RBAC | Spatie + menús personalizados por usuario |
| Jobs async | ProcessElectronicInvoiceJob, ProcessElectronicCreditNoteJob |
| Observer | DocumentCreateObserver → contabilidad e inventario |
| Trait | AccountingEngineTrait, ElectronicDocumentTrait, SignMessageTrait |
| Service | ElectronicInvoiceProcessorService, CashOrPaymentReceipt, InventoryService |
| Builder | InvoiceJsonBuilder, CreditNoteJsonBuilder → generan XML UBL 2.1 |
| Artisan commands | RetryFailedElectronicInvoices, FixNegativeCashBalances, etc. |

### Puntos Débiles Identificados para el Nuevo Proyecto

- **Sin multi-tenancy real**: usa `company_id` en lugar de bases de datos/schemas aislados por tenant.
- **Sin sistema de subscripción/billing SaaS**: no hay módulo de planes, facturación de la plataforma ni onboarding automatizado.
- **Monolito único**: todo en un solo repositorio, dificulta escalar módulos de forma independiente.
- **Sin API pública documentada**: el API no está versionada ni documentada formalmente (sin OpenAPI/Swagger).
- **Sin webhooks ni eventos externos**: dificulta integración con terceros.
- **Autenticación duplicada**: Sanctum + JWT conviven sin una separación clara.
- **Sin sistema de auditoría centralizado**: los logs están dispersos en múltiples modelos.
- **Bootstrap como CSS**: opción válida pero pesada; considerar Tailwind para mayor flexibilidad.

---

## 2. Visión del Nuevo Proyecto

### Nombre Propuesto

**ColSaaS ERP** *(o el que definas)*

### Propuesta de Valor

Plataforma SaaS colombiana para PYMES con:

- Facturación electrónica DIAN UBL 2.1 incluida desde el día 1
- Contabilidad bajo PUC Colombia y NIIF para PYMES
- Multi-tenant con aislamiento real por empresa
- POS integrado, gestión de inventario y caja
- Modelo de subscripción con planes y billing automatizado
- API pública REST documentada para integraciones
- Onboarding guiado sin fricción

---

## 3. Arquitectura Propuesta para el Nuevo Proyecto

### Enfoque Arquitectónico: Monolito Moderno (NO microservicios, NO SvelteKit)

> **Este punto es importante leerlo antes de cualquier decisión técnica.**

El equipo ya trabaja con **Laravel + Inertia.js + Svelte 5**. Esta combinación ES el enfoque de **Monolito Moderno** y debe mantenerse en el nuevo proyecto. A continuación la aclaración de por qué:

#### ¿Qué es el Monolito Moderno con Inertia.js?

```
MONOLITO MODERNO (nuestro enfoque):
┌─────────────────────────────────────────────┐
│              UN SOLO REPOSITORIO             │
│                                             │
│  Laravel maneja TODO el routing y la lógica │
│       ↓ responde con "Inertia response"     │
│  Svelte 5 renderiza como SPA en el browser  │
│                                             │
│  → Sin API REST explícita entre frontend    │
│    y backend (Inertia lo hace transparente) │
│  → Sin duplicar routing en el frontend      │
│  → Sin JWT para la web (solo Sanctum SPA)   │
│  → El equipo solo mantiene UN codebase      │
└─────────────────────────────────────────────┘
```

#### ¿Por qué NO usar SvelteKit en este proyecto?

**SvelteKit** es un framework fullstack para Svelte (equivalente a Next.js para React). Si se usara SvelteKit, el proyecto cambiaría radicalmente:

```
CON SVELTEKIT (NO es nuestro enfoque):
┌─────────────────┐       ┌──────────────────┐
│   SvelteKit     │ fetch │   Laravel         │
│  (frontend app  │ ────► │  (API pura, solo  │
│   separada con  │  REST │   JSON, endpoints │
│   su propio     │ ◄──── │   explícitos)     │
│   routing/SSR)  │       │                  │
└─────────────────┘       └──────────────────┘
   Repo frontend              Repo backend
```

Esto implicaría:
- Dos repositorios o un monorepo complejo
- Construir una API REST completa con autenticación JWT
- Duplicar validaciones (frontend y backend)
- Mayor overhead de desarrollo y mantenimiento
- El equipo tendría que dominar dos runtimes distintos

**Conclusión: SvelteKit + Inertia.js son mutuamente excluyentes.** Inertia.js reemplaza lo que SvelteKit haría como router. Usamos Svelte 5 (solo el framework reactivo) + Inertia.js (el puente con Laravel).

#### ¿Qué significa "Modular" en Monolito Moderno?

"Modular" se refiere a **cómo organizamos el código dentro del monolito**, no a separar servicios:

```
app/Modules/Invoice/     ← Todo lo de facturación junto
app/Modules/Accounting/  ← Todo lo de contabilidad junto
app/Modules/Inventory/   ← Todo lo de inventario junto
```

Cada módulo tiene sus propios Models, Controllers, Services, Jobs. Pero todos corren en **el mismo proceso PHP**, comparten la misma base de datos y se despliegan juntos. La ventaja es que si en el futuro se quisiera extraer un módulo como microservicio, ya está aislado.

---

### Modelo Multi-Tenant

Usar **multi-tenant por schema de PostgreSQL** (un schema por empresa):

```
postgres
├── public          ← tablas del SaaS: tenants, plans, subscriptions, billing
├── tenant_abc123   ← datos de empresa ABC
├── tenant_xyz456   ← datos de empresa XYZ
└── ...
```

**Librerías recomendadas:**
- [`stancl/tenancy`](https://tenancyforlaravel.com/) — el estándar para Laravel multi-tenant
- Soporta multi-database y multi-schema
- Middleware automático de identificación por dominio/subdominio o header

**Identificación del tenant:**
- Subdominio: `abc.tuapp.com`
- Header API: `X-Tenant-ID: abc123`
- Para POS local: token de empresa en JWT

### Capas de la Arquitectura

```
┌───────────────────────────────────────────────────────────────┐
│                     CAPA DE PRESENTACIÓN                       │
│                                                               │
│  Svelte 5 + Inertia.js          │  API REST pura             │
│  (Monolito Moderno - web app)   │  (POS offline / móvil /    │
│  → routing gestionado por       │   integraciones externas)  │
│    Laravel, Svelte solo renderiza│                            │
└──────────────────┬──────────────────────────┬────────────────┘
                   │  Inertia response         │  JSON / REST
┌──────────────────▼──────────────────────────▼────────────────┐
│                    LARAVEL 11 (Monolito)                       │
│                                                               │
│  Routes (web.php + api.php + tenant.php + landlord.php)       │
│  Middleware: TenancyBySubdomain │ Sanctum Auth │ Permission   │
│  Controllers → Services → Jobs → Events                       │
└─────────────────────────────┬─────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                  CAPA DE DOMINIO (Módulos)                     │
│                                                               │
│  Invoice │ Accounting │ Inventory │ POS │ Cash │ Purchases    │
│  Payroll │ Reports    │ Tenant (SaaS) │ Auth │ Integrations   │
│                                                               │
│  Todos los módulos corren en el MISMO proceso Laravel         │
│  Comparten la misma BD (schema por tenant en PostgreSQL)      │
└─────────────────────────────┬─────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                     INFRAESTRUCTURA                            │
│                                                               │
│  PostgreSQL 16 (multi-schema) │ Redis (cache + queues)        │
│  S3 / Cloudflare R2 (storage) │ Queue Workers + Horizon       │
│  DIAN Provider (Nextpyme API) │ Email │ SMS │ Webhooks        │
└───────────────────────────────────────────────────────────────┘
```

### Estructura de Módulos (Modular Monolith)

Organizar el backend en módulos para poder escalar o extraer como microservicios después:

```
app/
├── Modules/
│   ├── Tenant/           ← SaaS: empresas, planes, subscripciones
│   ├── Auth/             ← login, registro, 2FA
│   ├── Invoice/          ← facturación DIAN
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   └── Builders/     ← UBL 2.1
│   ├── Accounting/       ← plan de cuentas, asientos, balances
│   ├── Inventory/        ← productos, bodegas, movimientos
│   ├── POS/              ← terminales, turnos, ventas rápidas
│   ├── Cash/             ← cajas, bancos, pagos
│   ├── Purchases/        ← OC, proveedores
│   ├── Payroll/          ← nómina colombiana
│   ├── Reports/          ← reportes cruzados
│   └── Integrations/     ← webhooks, API pública, DIAN
├── Shared/
│   ├── Traits/
│   ├── Helpers/
│   └── DTOs/
└── Http/
    ├── Controllers/
    └── Middleware/
```

---

## 4. Stack Tecnológico Recomendado para el Nuevo Proyecto

### Backend

| Decisión | Elección | Justificación |
|---|---|---|
| Framework | Laravel 11 | Madurez, ecosistema, equipo conoce |
| PHP | 8.3+ | Tipado mejorado, performance |
| Multi-tenant | `stancl/tenancy` v3 | Schema isolation real |
| Auth | Sanctum únicamente | Eliminar JWT duplicado |
| Autorización | Spatie Permission v6 | Ya conocido, funciona bien |
| Queue | Redis (no DB) | Performance, Horizon UI |
| Cache | Redis | Sesiones, caché de consultas |
| PDF | DomPDF + Gotenberg | DomPDF para tickets, Gotenberg para PDFs complejos |
| Excel | Maatwebsite Excel | Import/export |
| Email | Laravel Mail + Mailgun/SES | Confiabilidad |
| Storage | S3-compatible (Cloudflare R2) | Más barato que AWS S3 |
| Observabilidad | Telescope (dev) + Sentry (prod) | Debug y monitoreo |
| API docs | Scramble (auto-gen OpenAPI) | Sin anotaciones manuales |
| Tests | Pest PHP | Más conciso que PHPUnit |

### Frontend

> El frontend NO es un proyecto separado. Svelte 5 vive dentro del mismo repositorio Laravel,
> compilado por Vite, y se comunica con el backend exclusivamente a través de Inertia.js.
> **No se usa SvelteKit** porque Inertia.js ya provee el routing y el bridge con Laravel.

| Decisión | Elección | Justificación |
|---|---|---|
| Framework reactivo | Svelte 5 | Mismo stack que el equipo ya domina. Runes = mejor DX que Svelte 4 |
| Bridge Laravel↔Svelte | Inertia.js 2.x | Elimina la necesidad de una API REST para la web. El routing lo maneja Laravel |
| Build tool | Vite 6 | Compilación ultra rápida, HMR, ya configurado en el proyecto actual |
| CSS | Tailwind CSS 4.x | Más flexible y liviano que Bootstrap. Mejor para componentes reutilizables |
| UI Components | shadcn-svelte | Componentes accesibles, no opinionados en estilos, fácil de personalizar |
| Charts | Chart.js 4 | Ya conocido del proyecto actual |
| Forms | Superforms + Zod | Validación tipada end-to-end, integra con Inertia |
| State | Svelte stores + Runes | Reactivo nativo de Svelte 5, sin librerías externas |
| Tables | TanStack Table (Svelte) | Tablas avanzadas con sort, filter, pagination |
| PDF client | pdfmake | Mantener por compatibilidad con el proyecto actual |
| Print POS | QZ Tray | Mantener, funciona con impresoras térmicas USB/red |

### Infraestructura

| Componente | Opción | Notas |
|---|---|---|
| Base de datos | PostgreSQL 16 | Multi-schema tenancy |
| Cache/Queue | Redis 7 | Horizon para monitoreo de colas |
| Contenedores | Docker + Compose | Dev local |
| CI/CD | GitLab CI o GitHub Actions | Pipeline test → build → deploy |
| Hosting inicial | DigitalOcean App Platform / VPS | Escalable a K8s después |
| DNS / CDN | Cloudflare | Wildcard DNS para subdominios |
| Certificados SSL | Cloudflare / Let's Encrypt | Wildcard para `*.tuapp.com` |
| Backups | Spatie Backup + S3 | Por tenant |

---

## 5. Modelo de Datos Central (Público / SaaS)

```sql
-- Schema: public (datos del SaaS, no del tenant)

tenants
  id (uuid)
  name
  domain (subdominio único)
  plan_id
  trial_ends_at
  status (active, suspended, cancelled)
  created_at

plans
  id
  name (Básico, Profesional, Empresarial)
  price_monthly
  price_yearly
  features (jsonb)  ← qué módulos incluye
  max_users
  max_documents_per_month

subscriptions
  id
  tenant_id
  plan_id
  status
  current_period_start
  current_period_end
  payment_gateway_ref

billing_invoices
  id
  tenant_id
  amount
  status
  due_date
  paid_at
```

---

## 6. Módulo DIAN / Facturación Electrónica

### Conceptos clave a implementar (UBL 2.1)

| Concepto | Descripción |
|---|---|
| CUFE | Hash único de factura (SHA-384) |
| Resolución DIAN | Rango y vigencia de numeración |
| Tipos de documento | 01=Factura, 91=Nota Crédito, 92=Nota Débito, DS=Doc Soporte |
| Software de facturación | ID y PIN registrado ante DIAN |
| Ambiente | Habilitación (pruebas) / Producción |
| Evento RADIAN | Acuse, recibo, pago, cesión |

### Proveedores DIAN disponibles en Colombia

| Proveedor | Notas |
|---|---|
| **Nextpyme** | El que usa el proyecto actual |
| **Siigo API** | Popular, robusto, tiene SDK |
| **Alegra** | Más orientado a pequeñas empresas |
| **API2.co** | API directa, más control |
| **AFIP / cuentas propias** | Para quien quiere habilitación directa |

> **Recomendación**: mantener la abstracción con un `DianProvider` intercambiable (Strategy Pattern) para no depender de un solo proveedor.

### Flujo de Emisión de Factura

```
Usuario → Crea documento → Validación local
  → DocumentService::create()
  → Genera CUFE (hash SHA-384)
  → ProcessElectronicInvoiceJob::dispatch()
    → InvoiceJsonBuilder::build() → JSON UBL 2.1
    → DianProvider::send(json)
    → Guarda respuesta: CUFE validado, PDF DIAN, XML
  → Notifica usuario (email + UI en tiempo real)
```

### Impuestos colombianos a cubrir

| Impuesto | Código DIAN | Tarifas comunes |
|---|---|---|
| IVA | 01 | 0%, 5%, 19% |
| INC (bolsas) | 22 | $66 por bolsa |
| ICA | 07 | Variable por municipio |
| Retefuente | — | Variable por concepto |
| ReteIVA | — | 15% del IVA |
| ReteICA | — | Variable |

---

## 7. Contabilidad Colombiana (PUC + NIIF PYMES)

### Plan Único de Cuentas (PUC)

Estructura requerida:

```
Clase (1 dígito)  → Activo, Pasivo, Patrimonio, Ingresos, Costos, Gastos
  Grupo (2 dígitos)
    Cuenta (4 dígitos)
      Subcuenta (6 dígitos)
        Auxiliar (hasta 8+ dígitos)
```

### Tablas de contabilidad necesarias

```
chart_of_accounts
  code (varchar, indexado)
  name
  type (debit_nature | credit_nature)
  class (1-6)
  allows_movements (bool)  ← solo auxiliares permiten asientos
  tax_account (bool)
  parent_code

journal_entries
  id (uuid)
  entry_date
  description
  document_id (nullable FK)
  created_by
  posted (bool)

journal_entry_lines
  id
  journal_entry_id
  account_code
  description
  debit
  credit
  third_party_id (nullable)
```

### Automatización contable

Definir reglas de asiento automático por tipo de operación:

| Operación | Debe | Haber |
|---|---|---|
| Venta (contado) | Caja 1105 | Ventas 4135 + IVA 2408 |
| Venta (crédito) | Clientes 1305 | Ventas 4135 + IVA 2408 |
| Costo de venta | Costo 6135 | Inventario 1435 |
| Pago proveedor | Proveedores 2205 | Banco 1110 |
| Nómina | Salarios 5105 | Oblig. laborales 2610 |

---

## 8. Pasos para Iniciar el Nuevo Proyecto desde Cero

### Fase 0: Preparación (Semana 1)

- [ ] Definir nombre comercial, dominio base (`*.tuapp.com`)
- [ ] Crear repositorio privado (GitLab o GitHub)
- [ ] Definir estructura de branches: `main`, `develop`, `feature/*`, `hotfix/*`
- [ ] Configurar entorno local: Docker Compose con PHP 8.3, PostgreSQL 16, Redis 7, Nginx
- [ ] Decidir proveedor DIAN (Nextpyme o alternativa)
- [ ] Registrar software ante DIAN (ID de software + PIN)
- [ ] Contratar wildcard SSL (`*.tuapp.com`)
- [ ] Configurar Cloudflare con wildcard DNS

### Fase 1: Scaffolding del Proyecto (Semana 1-2)

```bash
# 1. Crear proyecto Laravel
composer create-project laravel/laravel erp-saas
cd erp-saas

# 2. Instalar stancl/tenancy
composer require stancl/tenancy

# 3. Instalar dependencias core
composer require \
  spatie/laravel-permission \
  laravel/sanctum \
  barryvdh/laravel-dompdf \
  maatwebsite/excel \
  spatie/laravel-backup \
  spatie/laravel-activitylog \
  dedoc/scramble

# 4. Instalar Pest para testing
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev

# 5. Instalar Svelte 5 + Inertia.js + Vite (Monolito Moderno)
# NOTA: NO se usa SvelteKit. Svelte vive dentro del mismo proyecto Laravel.
npm install svelte@^5 @sveltejs/vite-plugin-svelte @inertiajs/svelte

# 6. Instalar Tailwind CSS 4
npm install -D tailwindcss @tailwindcss/vite
# Agregar el plugin en vite.config.js y @import "tailwindcss" en app.css

# 7. Publicar configuraciones
php artisan tenancy:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### Fase 2: Multi-Tenancy Base (Semana 2-3)

- [ ] Configurar `stancl/tenancy` con schema PostgreSQL
- [ ] Crear modelo `Tenant` con campos: `id`, `name`, `domain`, `plan_id`, `status`
- [ ] Configurar `TenancyBootstrappers` (Database, Cache, Queue, Storage)
- [ ] Crear middleware `InitializeTenancyByDomain` y `InitializeTenancyBySubdomain`
- [ ] Crear `TenantController` para onboarding: crear empresa, admin user, schema
- [ ] Seed de datos maestros por tenant: plan de cuentas PUC, tipos de documento, tarifas IVA
- [ ] Configurar identificación por subdominio en DNS wildcard

**Estructura de schemas:**
```
public        → tenants, plans, subscriptions, billing_invoices
tenant_{slug} → toda la data del cliente (documents, items, accounts, etc.)
```

### Fase 3: Autenticación y Autorización (Semana 3)

- [ ] Auth con Sanctum (SPA tokens para web, API tokens para POS/móvil)
- [ ] Registro de usuario inicial al crear tenant
- [ ] Login por subdominio (identifica tenant automáticamente)
- [ ] Spatie Permission por tenant
- [ ] Roles predefinidos: `super_admin`, `admin`, `accountant`, `cashier`, `warehouse`
- [ ] Menú dinámico por rol (tabla `menus` por tenant)
- [ ] 2FA opcional (TOTP con `pragmarx/google2fa`)

### Fase 4: Módulo de Administración SaaS (Semana 4)

- [ ] Panel super-admin (schema `public`): listar tenants, estado, planes
- [ ] CRUD de planes con features como JSON
- [ ] Onboarding wizard para nuevos clientes:
  1. Registro empresa (NIT, razón social, régimen)
  2. Datos DIAN (resolución, prefijo, numeración)
  3. Crear usuario administrador
  4. Elegir plan / trial 30 días
- [ ] Billing: integrar Wompi, PayU o Stripe (tarjeta, PSE, Nequi)
- [ ] Webhooks de pago → actualizar subscripción
- [ ] Emails transaccionales: bienvenida, factura plataforma, aviso vencimiento

### Fase 5: Módulo de Configuración de Empresa (Semana 5)

- [ ] Datos de empresa: NIT con DV, razón social, actividad económica CIIU
- [ ] Tipo de organización (Persona natural / jurídica)
- [ ] Régimen tributario (Responsable IVA / No responsable)
- [ ] Establecimientos y puntos de venta
- [ ] Configuración de resoluciones DIAN (número, fecha, prefijo, rango, vigencia)
- [ ] Carga de logo (S3)
- [ ] Configuración de email SMTP propio
- [ ] Configuración de bodegas

### Fase 6: Módulo de Terceros (Semana 5-6)

- [ ] Tabla `third_parties` (clientes, proveedores, empleados)
- [ ] Campos DIAN: tipo identificación, NIT, DV, tipo persona, régimen
- [ ] Datos contacto: email, teléfono, dirección, municipio (tabla DANE)
- [ ] Importar desde Excel
- [ ] Búsqueda en RUES (Registro Único Empresarial) via API pública
- [ ] Validación DV NIT automática

### Fase 7: Catálogo de Productos e Inventario (Semana 6-7)

- [ ] Tabla `items`: código, nombre, tipo (servicio/producto)
- [ ] Clasificación: `item_groups`, `item_lines`, `item_categories`
- [ ] Código estándar UNSPSC / EAN
- [ ] Unidades de medida (DIAN UBL)
- [ ] Precios por bodega y lista de precios
- [ ] Impuestos por ítem (IVA, INC, etc.)
- [ ] Inventario: `item_inventories` con saldo por bodega
- [ ] Kardex: `inventory_movements` (entry, output, transfer, adjustment)
- [ ] Control stock: alertas de mínimo, máximo
- [ ] Importar productos desde Excel

### Fase 8: Facturación Electrónica DIAN (Semana 7-10)

**Este es el módulo más crítico. Tomará más tiempo.**

#### 8a. Estructura base de documentos

- [ ] Tabla `documents`: tipo, número, fecha, estado, CUFE, tercero, totales
- [ ] Tabla `document_lines`: ítem, cantidad, precio, descuento, impuesto, subtotal
- [ ] Tabla `document_taxes`: resumen de impuestos por documento
- [ ] Tabla `dian_submissions`: historial de envíos, respuesta, CUFE, XML, PDF
- [ ] Estados: `draft`, `pending`, `sent`, `approved`, `rejected`, `cancelled`

#### 8b. Motor de cálculo

- [ ] Cálculo de líneas: precio unitario, descuento, subtotal sin IVA
- [ ] Cálculo IVA por tarifa (agrupar por porcentaje)
- [ ] Cálculo retenciones (sobre venta: retefuente, reteIVA, reteICA)
- [ ] Total documento = subtotal + IVA - retenciones
- [ ] Manejo de precios con IVA incluido vs excluido

#### 8c. Generación UBL 2.1

- [ ] `InvoiceUBLBuilder`: genera JSON/XML según especificación DIAN
- [ ] Campos obligatorios: versión UBL, tipo doc, moneda, CUFE, QR
- [ ] Sección emisor: NIT, DV, nombre, régimen, resolución
- [ ] Sección receptor: NIT/CC, nombre, régimen, dirección
- [ ] Líneas: ítem UNSPSC, descripción, cantidad, precio, impuestos
- [ ] Firma digital (si se integra directamente con DIAN sin intermediario)

#### 8d. Integración con proveedor DIAN

- [ ] Clase abstracta `DianProviderInterface`
- [ ] Implementación `NextpymeProvider` (migrar lógica del proyecto actual)
- [ ] Implementación alternativa (API2.co u otro como backup)
- [ ] `ProcessInvoiceJob`: reintento automático con backoff exponencial
- [ ] Monitor de facturas fallidas: `MonitorFailedInvoicesCommand`

#### 8e. Tipos de documentos a implementar

| Documento | Prioridad |
|---|---|
| Factura de venta electrónica | P0 (primero) |
| Nota crédito (devolución, descuento, anulación) | P0 |
| Nota débito | P1 |
| Documento soporte de proveedor | P1 |
| Factura de compra (recepción RADIAN) | P2 |

### Fase 9: Gestión de Caja y Pagos (Semana 10-11)

- [ ] Tabla `cash_boxes`: principal y POS por establecimiento
- [ ] `CashMovements`: entradas y salidas con tipo (venta, gasto, traslado)
- [ ] Cierre de caja: resumen por forma de pago
- [ ] Medios de pago: efectivo, tarjeta, transferencia, Nequi, Daviplata
- [ ] Recibos de caja (cobro a clientes)
- [ ] Comprobantes de egreso (pago a proveedores)
- [ ] Cuentas bancarias y movimientos
- [ ] Conciliación bancaria básica

### Fase 10: POS (Point of Sale) (Semana 11-12)

- [ ] Interfaz POS optimizada para pantalla táctil
- [ ] Búsqueda rápida de productos (código de barras / nombre)
- [ ] Carrito de ventas con descuentos
- [ ] Selección de medios de pago (efectivo, tarjeta, mixto)
- [ ] Apertura y cierre de turno de cajero
- [ ] Impresión de tiquete (QZ Tray + escpos-php o red/USB)
- [ ] Sincronización offline (para red inestable en el punto de venta)
- [ ] Reimpresión de último tiquete

### Fase 11: Contabilidad (Semana 12-14)

- [ ] Cargar PUC Colombia completo (seed migration)
- [ ] CRUD plan de cuentas personalizable por empresa
- [ ] Configuración de reglas de asiento automático por operación
- [ ] Motor contable: `AccountingEngine` genera asientos al crear documentos
- [ ] Libro diario (journal entries) con cuadre obligatorio (debe = haber)
- [ ] Libro mayor por cuenta
- [ ] Balance de prueba (trial balance)
- [ ] Estado de resultados P&G
- [ ] Balance general
- [ ] Exportar a Excel
- [ ] Ajustes manuales de asientos
- [ ] Cierre de período contable

### Fase 12: Compras (Semana 14-15)

- [ ] Órdenes de compra
- [ ] Recepción de mercancía (entrada al inventario)
- [ ] Factura de compra (sin DIAN para no obligados / con RADIAN para obligados)
- [ ] Nota crédito proveedor
- [ ] Informe de cuentas por pagar

### Fase 13: Reportes y Dashboard (Semana 15-16)

- [ ] Dashboard: ventas del día, semana, mes — gráfica Chart.js
- [ ] Reporte de ventas por período, producto, cliente, vendedor
- [ ] Reporte de compras
- [ ] Reporte de inventario (kárdex, valorizado, rotación)
- [ ] Reporte de IVA (generado, descontable, a pagar)
- [ ] Reporte de retenciones (retefuente, reteIVA)
- [ ] Reporte de cuentas por cobrar
- [ ] Reporte de cuentas por pagar
- [ ] Exportar PDF y Excel en todos los reportes

### Fase 14: API Pública y Webhooks (Semana 16-17)

- [ ] Versionar API: `/api/v1/`
- [ ] Documentación automática con Scramble (OpenAPI 3.1)
- [ ] Tokens de API por tenant para integraciones
- [ ] Webhooks: invoice.created, invoice.approved, payment.received
- [ ] Rate limiting por plan
- [ ] Sandbox para pruebas DIAN desde la API

### Fase 15: Calidad y Lanzamiento (Semana 17-20)

- [ ] Tests unitarios de cálculo de impuestos (Pest)
- [ ] Tests de integración del flujo completo de factura
- [ ] Tests de la generación UBL 2.1 (comparar XML con validadores DIAN)
- [ ] Setup pipeline CI/CD completo
- [ ] Monitoreo con Sentry (errores) y Laravel Telescope (dev)
- [ ] Horizon para monitoreo de colas en producción
- [ ] Documentación interna de onboarding para nuevos clientes
- [ ] Landing page del producto
- [ ] Beta cerrada con 3-5 empresas piloto

---

## 9. Estructura de Directorios del Nuevo Proyecto

```
erp-saas/
├── app/
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── Controllers/
│   │   │   └── Services/
│   │   ├── Tenant/                   ← SaaS management
│   │   │   ├── Models/ (Tenant, Plan, Subscription)
│   │   │   ├── Controllers/
│   │   │   └── Services/
│   │   ├── Invoice/                  ← Facturación electrónica
│   │   │   ├── Models/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Jobs/
│   │   │   ├── Builders/             ← UBL 2.1
│   │   │   └── Providers/            ← DIAN adapters
│   │   ├── Accounting/
│   │   ├── Inventory/
│   │   ├── POS/
│   │   ├── Cash/
│   │   ├── Purchases/
│   │   ├── Payroll/
│   │   └── Reports/
│   ├── Shared/
│   │   ├── Traits/
│   │   ├── DTOs/
│   │   ├── Helpers/
│   │   └── Enums/
│   └── Http/
│       ├── Controllers/
│       └── Middleware/
├── database/
│   ├── migrations/
│   │   ├── tenant/                   ← corren en cada schema de tenant
│   │   └── landlord/                 ← corren en schema public (SaaS)
│   └── seeders/
│       ├── TenantSeeder.php          ← PUC, datos DIAN, maestros
│       └── LandlordSeeder.php        ← planes, configuración base
├── resources/
│   └── js/
│       ├── Pages/
│       │   ├── Auth/
│       │   ├── Dashboard/
│       │   ├── Invoice/
│       │   ├── POS/
│       │   ├── Inventory/
│       │   ├── Accounting/
│       │   ├── Reports/
│       │   └── Admin/                ← panel super-admin SaaS
│       ├── Components/
│       │   ├── UI/                   ← botones, modales, inputs
│       │   ├── Invoice/
│       │   └── POS/
│       └── Stores/
├── routes/
│   ├── web.php                       ← rutas web (Inertia)
│   ├── api.php                       ← rutas API
│   ├── tenant.php                    ← rutas dentro del tenant
│   └── landlord.php                  ← rutas del SaaS (admin)
├── config/
├── tests/
│   ├── Unit/
│   │   ├── Invoice/
│   │   └── Accounting/
│   └── Feature/
│       ├── InvoiceFlowTest.php
│       └── TenantOnboardingTest.php
└── docker/
    ├── php/
    ├── nginx/
    └── docker-compose.yml
```

---

## 10. Docker Compose de Desarrollo

```yaml
# docker-compose.yml
services:
  app:
    build: ./docker/php
    volumes:
      - .:/var/www/html
    environment:
      - APP_ENV=local
    depends_on:
      - postgres
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf

  postgres:
    image: postgres:16
    environment:
      POSTGRES_DB: erp_saas
      POSTGRES_USER: erp
      POSTGRES_PASSWORD: secret
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  horizon:
    build: ./docker/php
    command: php artisan horizon
    volumes:
      - .:/var/www/html
    depends_on:
      - redis
      - postgres

  mailpit:
    image: axllent/mailpit
    ports:
      - "1025:1025"   # SMTP
      - "8025:8025"   # Web UI

volumes:
  pgdata:
```

---

## 11. Riesgos y Consideraciones

### Riesgos Técnicos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Cambios en API DIAN | Alto | Abstracción `DianProviderInterface`, monitorear boletines DIAN |
| Tiempo de respuesta DIAN (SLA) | Medio | Reintentos automáticos, dashboard de estado |
| Complejidad multi-tenant | Alto | Usar `stancl/tenancy` bien documentada, evitar migrations manuales |
| Performance con múltiples schemas | Medio | Índices correctos, pool de conexiones, Redis cache |
| Cortes de luz/internet en POS | Alto | Modo offline con IndexedDB + sync al reconectar |

### Consideraciones Regulatorias Colombia

| Requisito | Acción |
|---|---|
| Habilitación DIAN | Completar proceso de habilitación antes de go-live |
| Numeración autorizada | Gestionar resoluciones por empresa (prefijo + rango) |
| Contingencia DIAN | Implementar modo contingencia (facturas en papel con posterior envío) |
| Protección de datos (Ley 1581) | Política de privacidad, encriptar datos sensibles, logs de acceso |
| Factura de la plataforma | La plataforma misma debe emitir facturas electrónicas a sus clientes |

### Consideraciones de Negocio

- **Plan freemium**: considera ofrecer 5 facturas/mes gratis para tracción
- **Soporte**: define canales (chat, email, WhatsApp Business)
- **Documentación**: invierte en docs desde el día 1, reduce soporte
- **Precios**: cobrar por documentos emitidos y/o usuarios activos
- **Integraciones**: Siigo, Alegra, WooCommerce, Shopify como integraciones futuras

---

## 12. Recursos Técnicos de Referencia

### DIAN / Facturación Electrónica

- [Especificación técnica DIAN UBL 2.1](https://www.dian.gov.co/impuestos/Paginas/Factura_Electronica.aspx)
- [Conjunto de pruebas DIAN (Habilitación)](https://catalogo-vpfe.dian.gov.co/User/Login)
- [Validador DIAN de XML UBL](https://catalogo-vpfe-hab.dian.gov.co)
- [Codificación UNSPSC Colombia](https://www.ungm.org/Public/UNSPSC)
- [Tabla de monedas ISO 4217](https://www.currency-iso.org/en/home/tables/table-a1.html)
- [Municipios DANE Colombia](https://www.dane.gov.co/index.php/estadisticas-por-tema/demografia-y-poblacion/sistema-de-consulta-de-la-division-politico-administrativa-de-colombia-divipola)

### Librerías y Frameworks

- [stancl/tenancy docs](https://tenancyforlaravel.com/docs/v3/introduction/)
- [Scramble (OpenAPI Laravel)](https://scramble.dedoc.co/)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6/)
- [Laravel Horizon](https://laravel.com/docs/11.x/horizon)
- [shadcn-svelte](https://www.shadcn-svelte.com/)
- [TanStack Table Svelte](https://tanstack.com/table/v8/docs/framework/svelte/svelte-table)
- [Superforms + Svelte](https://superforms.rocks/)

### PUC Colombia

- [Resolución 8145 de 2015 - PUC Colombia](https://www.supersociedades.gov.co/delegatura_aec/normatividad/NormDocumentos/PUC.pdf)
- Puedes encontrar el PUC completo en Excel en múltiples fuentes gubernamentales

---

## 13. Cronograma Estimado

| Fase | Semanas | Entregable |
|---|---|---|
| 0. Preparación | 1 | Repo, entorno, contratos DIAN |
| 1. Scaffolding | 1-2 | Laravel + Svelte + Docker funcional |
| 2. Multi-tenancy | 2-3 | Schemas por empresa, identificación por subdominio |
| 3. Auth | 3 | Login, registro, roles |
| 4. Admin SaaS | 4 | Panel super-admin, planes, onboarding |
| 5-6. Config + Terceros | 5-6 | Empresa, clientes, proveedores |
| 7. Inventario | 6-7 | Productos, bodegas, kardex |
| 8. Facturación DIAN | 7-10 | Factura electrónica completa + NC |
| 9-10. Caja + POS | 10-12 | POS táctil, cierres, medios de pago |
| 11. Contabilidad | 12-14 | PUC, asientos automáticos, balances |
| 12. Compras | 14-15 | OC, recepciones, pagos |
| 13. Reportes | 15-16 | Dashboard + reportes clave |
| 14. API pública | 16-17 | API v1 documentada |
| 15. QA + Lanzamiento | 17-20 | Beta, piloto, go-live |

**Total estimado: 20 semanas (5 meses)** para un MVP robusto con 1 equipo de 2-3 desarrolladores.

---

## 14. Checklist de Inicio Rápido (Primeras 2 semanas)

```bash
# Semana 1: Fundamentos
[ ] Crear repositorio + configurar branches (main, develop)
[ ] Docker Compose con PHP 8.3, PostgreSQL 16, Redis, Nginx, Mailpit
[ ] composer create-project laravel/laravel erp-saas
[ ] Instalar stancl/tenancy + configurar schemas
[ ] Instalar Svelte + Inertia + Vite
[ ] Instalar Tailwind CSS 4 + shadcn-svelte
[ ] Configurar Sanctum + auth básica
[ ] Primera migración: tenants, plans, subscriptions (schema public)
[ ] Primera migración tenant: users, companies, resolutions
[ ] Seed: PUC Colombia, tipos de documento DIAN, municipios DANE

# Semana 2: Esqueleto funcional
[ ] Login funcional con identificación de tenant por subdominio
[ ] Panel de super-admin: listar tenants
[ ] Onboarding: crear empresa → crear usuario admin → elegir plan (trial)
[ ] CRUD básico de terceros (clientes/proveedores)
[ ] CRUD básico de productos
[ ] Primera factura de prueba generada (aunque sea PDF sin DIAN)
[ ] Tests básicos: login, crear empresa, crear factura
[ ] CI pipeline: lint + tests en cada push
```

---

---

## 15. Glosario de Términos Clave (para el equipo)

Este glosario existe para evitar confusiones cuando alguien nuevo llegue al proyecto o cuando se retome después de tiempo.

| Término | Qué es | Qué NO es en este proyecto |
|---|---|---|
| **Monolito Moderno** | Un solo codebase Laravel donde el backend maneja el routing y el frontend (Svelte) solo renderiza. Todo se despliega junto. | No es un monolito "antiguo" con Blade puro. No es microservicios. |
| **Inertia.js** | El puente entre Laravel y Svelte. Laravel retorna "Inertia responses" (JSON con componente + props) y Svelte los renderiza como SPA. | No es una API REST. No es un framework frontend. |
| **Svelte 5** | El framework reactivo que usamos para el frontend. Vive en `resources/js/`. Se compila con Vite. | No es SvelteKit. No tiene su propio servidor ni routing. |
| **SvelteKit** | Framework fullstack para Svelte (como Next.js). Tiene routing propio, SSR propio y necesita que Laravel sea una API separada. | **NO lo usamos.** Sería incompatible con Inertia.js y cambiaría el enfoque a microservicios. |
| **Modular Monolith** | Forma de organizar el código dentro del monolito en módulos por dominio (`app/Modules/Invoice/`, `app/Modules/Accounting/`...). | No significa que los módulos sean servicios separados. Todos corren en el mismo proceso PHP. |
| **Multi-tenant** | Cada empresa cliente tiene sus datos aislados en un schema propio de PostgreSQL (`tenant_abc`). El schema `public` es del SaaS (planes, subscripciones). | No es multi-empresa con `company_id` (que es lo que tiene el proyecto actual Xedoc). |
| **stancl/tenancy** | Librería Laravel que gestiona la creación de schemas, migraciones por tenant e identificación automática por subdominio. | No es una librería de autenticación. |
| **Landlord** | El schema `public` de PostgreSQL. Contiene las tablas del SaaS: tenants, planes, subscripciones, facturación de la plataforma. | No es un "usuario administrador". Es la capa de la plataforma que existe por encima de los tenants. |
| **Tenant** | Una empresa cliente del SaaS. Tiene su propio schema, subdominio (`empresa.tuapp.com`) y datos completamente aislados. | No es un usuario. Un tenant puede tener múltiples usuarios. |
| **CUFE** | Código Único de Factura Electrónica. Hash SHA-384 que identifica unívocamente cada factura ante la DIAN. | No es el número de factura. El número de factura es el consecutivo de la resolución DIAN. |
| **UBL 2.1** | Universal Business Language versión 2.1. Es el estándar XML/JSON que define el formato de las facturas electrónicas exigido por la DIAN. | No es propio de Colombia. Es un estándar internacional adaptado por la DIAN. |
| **RADIAN** | Sistema de la DIAN para gestionar eventos sobre facturas electrónicas de compra (acuse de recibo, pago, cesión de derechos). | No es el sistema de emisión de facturas. Es para eventos sobre facturas ya emitidas. |
| **Resolución DIAN** | Autorización que emite la DIAN a cada empresa para facturar electrónicamente. Define el prefijo, el rango de numeración y la vigencia. | No es el NIT ni el RUT. Cada empresa puede tener múltiples resoluciones (una por punto de venta, por ejemplo). |
| **Superforms** | Librería de Svelte para manejo de formularios con validación tipada (Zod). Integra bien con Inertia.js. | No reemplaza la validación del backend. La validación en Laravel sigue siendo obligatoria. |
| **Horizon** | Panel de monitoreo de colas de Laravel, usa Redis. Muestra jobs pendientes, fallidos, throughput. | No es el servidor web. No reemplaza Nginx. |
| **Sanctum (SPA mode)** | Autenticación para la web app. Usa cookies de sesión. No necesita tokens JWT para el monolito. | En este proyecto NO usamos JWT para la web. JWT solo aplica si hay clientes externos (API pública, app móvil). |

---

*Este documento fue generado con base en el análisis del proyecto xedoc-laravel-svelte.*
*Última actualización: 2026-03-02.*
*Actualizar a medida que el nuevo proyecto evolucione.*
