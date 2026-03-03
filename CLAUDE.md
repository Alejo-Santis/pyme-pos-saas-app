# CLAUDE.md — ERP SaaS Colombia

> Este archivo es leído automáticamente por Claude Code al abrir el proyecto.
> Contiene las instrucciones, convenciones y contexto necesarios para trabajar
> correctamente en este repositorio.
>
> **INSTRUCCIÓN PARA CLAUDE:** Lee este archivo completo antes de hacer cualquier
> acción. Las reglas aquí definidas tienen prioridad sobre cualquier suposición tuya.

---

## 1. ¿Qué es este proyecto?

Plataforma SaaS de facturación electrónica y gestión empresarial para Colombia.
Construida desde cero tomando como referencia el proyecto `xedoc-laravel-svelte`
(disponible localmente en `c:/laragon/www/xedoc-laravel-svelte/`).

**Módulos objetivo:**
- Facturación electrónica DIAN (UBL 2.1)
- Gestión de ventas y POS
- Inventario multi-bodega
- Contabilidad (PUC Colombia + NIIF PYMES)
- Caja y bancos
- Compras
- Nómina colombiana
- Reportes

---

## 2. Stack Tecnológico

### Backend
| Componente | Tecnología | Notas |
|---|---|---|
| Framework | Laravel 11 | PHP 8.3+ |
| Multi-tenancy | `stancl/tenancy` v3 | Schema por tenant en PostgreSQL |
| Auth | Laravel Sanctum | SPA mode para web. SIN JWT para web |
| Autorización | Spatie Permission v6 | Roles por tenant |
| Queue | Redis + Laravel Horizon | NO usar driver `database` para colas |
| Cache | Redis | |
| Tests | Pest PHP | NO usar PHPUnit directamente |
| API docs | Scramble (`dedoc/scramble`) | OpenAPI automático, sin anotaciones |
| PDF | DomPDF | |
| Excel | Maatwebsite Excel | |
| Storage | S3 / Cloudflare R2 | |
| Observabilidad dev | Laravel Telescope | Solo en desarrollo |
| Observabilidad prod | Sentry | |

### Frontend
| Componente | Tecnología | Notas |
|---|---|---|
| Framework reactivo | Svelte 5 | Con Runes (`$state`, `$derived`, `$props`) |
| Bridge | Inertia.js 2.x | El routing lo maneja Laravel, NO Svelte |
| Build | Vite 6 | Con `@tailwindcss/vite` plugin |
| CSS | Tailwind CSS 4 | Ver `NUEVO-PROYECTO-ESTILO-VISUAL.md` para la paleta |
| Íconos | Material Design Icons (MDI) | `<i class="mdi mdi-{nombre}">` |
| Componentes UI | Propios (ver `resources/js/Components/UI/`) | |
| Charts | Chart.js 4 | |
| Forms | Superforms + Zod | |
| PDF cliente | pdfmake | |
| Print POS | QZ Tray | |

### Base de datos
- **Motor:** PostgreSQL 16
- **Schema `public`:** tablas del SaaS (tenants, plans, subscriptions, billing)
- **Schema `tenant_{slug}`:** tablas de cada empresa cliente (documents, items, etc.)

---

## 3. Arquitectura — LEER OBLIGATORIAMENTE

### 3.1 Monolito Moderno (NO microservicios)

Este proyecto usa el enfoque de **Monolito Moderno con Inertia.js**.

```
CÓMO FUNCIONA:
  Browser → Laravel (maneja routing y lógica)
               ↓ Inertia response (JSON con componente + props)
           Svelte 5 (renderiza en browser como SPA)

NO se construye una API REST entre frontend y backend para la web.
Inertia.js elimina esa necesidad.
```

**NUNCA sugerir ni usar SvelteKit.** SvelteKit es incompatible con Inertia.js.
SvelteKit requeriría que Laravel sea una API separada, lo que rompe el enfoque monolito.

### 3.2 Modular Monolith (organización del código)

El backend está organizado en módulos por dominio de negocio dentro de `app/Modules/`.
Esto NO significa microservicios — todos corren en el mismo proceso Laravel.

```
app/Modules/
├── Invoice/       → Facturación DIAN
├── Accounting/    → Contabilidad
├── Inventory/     → Inventario
├── POS/           → Punto de venta
├── Cash/          → Caja y bancos
├── Purchases/     → Compras
├── Payroll/       → Nómina
├── Reports/       → Reportes
├── Tenant/        → Gestión SaaS (planes, subscripciones)
└── Auth/          → Autenticación
```

Cada módulo contiene: `Models/`, `Controllers/`, `Services/`, `Jobs/`, `Observers/` según necesite.

### 3.3 Multi-tenancy con stancl/tenancy

**REGLA FUNDAMENTAL:** Ninguna tabla del schema tenant debe tener columna `company_id`.
Con schema-per-tenant, la empresa está implícita en el schema actual. `company_id` es redundante.

```
Schema public (landlord):
  tenants, plans, subscriptions, billing_invoices

Schema tenant_{slug} (por cada empresa):
  users, documents, items, chart_of_accounts, ...
  → SIN company_id en ninguna tabla
```

**Identificación del tenant:**
- Web: por subdominio (`empresa.tuapp.com`)
- API externa: header `X-Tenant-ID`
- POS offline: token Sanctum del tenant

---

## 4. Convenciones de Código

### PHP / Laravel
- **Idioma del código:** inglés (clases, métodos, variables, columnas de BD)
- **Idioma de comentarios y mensajes de usuario:** español
- **Modelos:** singular PascalCase (`Document`, `InvoiceLine`, `ChartAccount`)
- **Tablas:** plural snake_case (`documents`, `invoice_lines`, `chart_accounts`)
- **Columnas:** snake_case (`created_at`, `due_date`, `cufe_code`)
- **Primary keys:** UUID (`$table->uuid('id')->primary()`) — NO bigIncrements
- **Foreign keys:** UUID también (`$table->foreignUuid('document_id')`)
- **Timestamps:** siempre incluir (`$table->timestamps()`)
- **Soft deletes:** solo en modelos que lo justifiquen (documentos, items, terceros)
- **Enums PHP:** usar enums nativos de PHP 8.1+ para estados
- **DTOs:** usar para transferencia entre capas (evitar arrays asociativos anónimos)
- **Responsabilidad de Controllers:** solo recibe request, llama Service, retorna response
- **Services:** contienen la lógica de negocio
- **Jobs:** procesos asincrónicos (envío DIAN, emails, reportes pesados)

### Svelte 5
- **Sintaxis:** usar Runes obligatoriamente (`$state`, `$derived`, `$props`, `$effect`)
- **NO usar** la API de Svelte 4 (`export let`, `$:`, stores cuando Runes sea suficiente)
- **Props:** destructurar con `let { prop1, prop2 } = $props()`
- **Componentes:** PascalCase (`InvoiceForm.svelte`, `DataTable.svelte`)
- **Un componente por archivo**
- **Imports limpios:** usar alias `@/` para `resources/js/`

### CSS / Tailwind
- Usar clases Tailwind en los componentes Svelte
- NO escribir CSS inline salvo casos excepcionales
- Para estilos recurrentes custom: definirlos en `resources/css/app.css`
- Paleta de colores: ver `docs/ESTILO-VISUAL.md`
- Íconos MDI: `<i class="mdi mdi-{nombre}"></i>`

---

## 5. Estructura de Directorios

```
/
├── app/
│   ├── Modules/
│   │   ├── Invoice/
│   │   │   ├── Models/
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Jobs/
│   │   │   ├── Builders/       ← generadores UBL 2.1
│   │   │   └── Providers/      ← adaptadores DIAN (Nextpyme, etc.)
│   │   ├── Accounting/
│   │   ├── Inventory/
│   │   ├── POS/
│   │   ├── Cash/
│   │   ├── Purchases/
│   │   ├── Payroll/
│   │   ├── Reports/
│   │   ├── Tenant/
│   │   └── Auth/
│   ├── Shared/
│   │   ├── Traits/
│   │   ├── DTOs/
│   │   ├── Helpers/
│   │   └── Enums/
│   └── Http/
│       ├── Controllers/        ← controllers que no pertenecen a un módulo
│       └── Middleware/
│
├── database/
│   ├── migrations/
│   │   ├── landlord/           ← se ejecutan en schema public (SaaS)
│   │   └── tenant/             ← se ejecutan en cada schema de empresa
│   └── seeders/
│       ├── LandlordSeeder.php  ← planes, configuración base SaaS
│       └── TenantSeeder.php    ← PUC, datos DIAN, maestros colombianos
│
├── resources/
│   ├── css/
│   │   ├── app.css             ← Tailwind + variables + utilitarios
│   │   └── icons.min.css       ← MDI icons
│   └── js/
│       ├── app.js
│       ├── Pages/
│       │   ├── Auth/
│       │   ├── Dashboard/
│       │   ├── Invoice/
│       │   ├── POS/
│       │   ├── Inventory/
│       │   ├── Accounting/
│       │   ├── Reports/
│       │   ├── Configurations/
│       │   └── Admin/          ← panel super-admin SaaS
│       ├── Components/
│       │   ├── UI/             ← Button, Card, Modal, Input, DataTable, Badge...
│       │   └── Shared/         ← Layout, SideNav, TopNavbar, Footer
│       └── Stores/
│
├── routes/
│   ├── web.php                 ← rutas Inertia (tenant)
│   ├── api.php                 ← rutas API REST pública
│   ├── tenant.php              ← rutas dentro del contexto tenant
│   └── landlord.php            ← rutas del SaaS (super-admin)
│
├── docs/
│   ├── ANALISIS.md             ← análisis del proyecto y hoja de ruta
│   └── ESTILO-VISUAL.md        ← sistema de diseño, componentes, paleta
│
└── tests/
    ├── Unit/
    └── Feature/
```

---

## 6. Migraciones — Instrucciones Específicas

### Proyecto de referencia para el schema

El schema de este proyecto está basado en `xedoc-laravel-svelte`.
Cuando necesites saber qué columnas tiene una tabla, consulta las migraciones de referencia en:
```
c:/laragon/www/xedoc-laravel-svelte/database/migrations/
```

### Reglas de adaptación OBLIGATORIAS al crear migraciones

Al consultar las migraciones del proyecto de referencia y crearlas aquí:

1. **ELIMINAR** la columna `company_id` de todas las tablas — no existe en el nuevo schema
2. **ELIMINAR** las foreign keys hacia `companies` — no aplica
3. **ELIMINAR** la tabla `company_user` — no aplica con multi-tenancy
4. **CAMBIAR** `$table->id()` por `$table->uuid('id')->primary()` en todas las tablas
5. **CAMBIAR** `unsignedBigInteger` por `foreignUuid` en todas las FK
6. **INCORPORAR** los cambios de migraciones "Fix..." al schema inicial limpio
   (ejemplo: si hay `FixDocumentTotalTaxFromJson`, esa columna ya debe estar en la migración original)
7. **COLOCAR** en `database/migrations/landlord/` las tablas: `tenants`, `plans`, `subscriptions`, `billing_invoices`
8. **COLOCAR** en `database/migrations/tenant/` todo lo demás (documentos, items, contabilidad, etc.)

### Tabla de referencia rápida: columnas a eliminar siempre

```php
// NUNCA incluir estas columnas en tablas del schema tenant:
$table->unsignedBigInteger('company_id');           // ← ELIMINAR
$table->foreign('company_id')->...;                  // ← ELIMINAR
$table->index(['company_id', 'created_at']);         // ← ELIMINAR si incluye company_id
```

### Ejemplo de conversión

```php
// REFERENCIA (xedoc) — NO copiar así:
Schema::create('documents', function (Blueprint $table) {
    $table->id();                              // ← cambiar a uuid
    $table->unsignedBigInteger('company_id'); // ← ELIMINAR
    $table->unsignedBigInteger('third_party_id');
    $table->string('prefix', 10)->nullable();
    $table->integer('number');
    $table->date('date');
    $table->timestamps();
    $table->foreign('company_id')->references('id')->on('companies'); // ← ELIMINAR
});

// NUEVO PROYECTO — así debe quedar:
Schema::create('documents', function (Blueprint $table) {
    $table->uuid('id')->primary();            // ← UUID
    $table->foreignUuid('third_party_id')->constrained('third_parties');
    $table->string('prefix', 10)->nullable();
    $table->integer('number');
    $table->date('date');
    $table->timestamps();
    // SIN company_id, SIN foreign a companies
});
```

---

## 7. Modelos — Instrucciones Específicas

Al reutilizar modelos del proyecto de referencia (`xedoc-laravel-svelte/app/Models/`):

```php
// ELIMINAR del $fillable:
'company_id',

// ELIMINAR relaciones:
public function company() { ... }

// ELIMINAR scopes de empresa:
public function scopeByCompany($q) { ... }
public function scopeOfCompany($q, $id) { ... }

// CAMBIAR el tipo de primary key:
protected $keyType = 'string';      // ← agregar
public $incrementing = false;       // ← agregar

// MANTENER todo lo demás:
// - Relaciones de negocio (hasMany, belongsTo con otros modelos)
// - Scopes de negocio (scopeActive, scopePending, etc.)
// - Accessors y Mutators
// - Casts
// - Observers
// - Traits de negocio (AccountingEngineTrait, ElectronicDocumentTrait, etc.)
```

---

## 8. Autenticación

- **Web (Inertia):** Sanctum en modo SPA (cookies de sesión, CSRF)
  - Login identifica el tenant por subdominio
  - NO usar JWT para sesiones web
- **API pública / POS / móvil:** Sanctum tokens de API
  - Cada tenant puede generar sus propios tokens de API
  - Los tokens incluyen el `tenant_id` en el payload o se resuelven por middleware

---

## 9. Integración DIAN

### Proveedor de referencia: Nextpyme
El proyecto de referencia usa Nextpyme como proveedor DIAN.
La lógica de construcción UBL 2.1 está en:
```
c:/laragon/www/xedoc-laravel-svelte/app/Services/Nextpyme/Builders/
  InvoiceJsonBuilder.php
  CreditNoteJsonBuilder.php
  EventJsonBuilder.php
  SupportDocumentBuilder.php
```

Estos builders son la parte más valiosa del proyecto de referencia.
Reutilizarlos adaptando el namespace al nuevo proyecto.

### Patrón de abstracción obligatorio
```php
// NUNCA acoplar directamente a Nextpyme. Usar interfaz:
interface DianProviderInterface {
    public function sendInvoice(array $ublData): DianResponse;
    public function sendCreditNote(array $ublData): DianResponse;
    public function getInvoiceStatus(string $cufe): DianResponse;
}

class NextpymeProvider implements DianProviderInterface { ... }
```

### Jobs de facturación electrónica
Mantener el patrón del proyecto de referencia:
- `ProcessElectronicInvoiceJob` — reintento con backoff exponencial (0s, 5s, 30s)
- `ProcessElectronicCreditNoteJob`
- `ProcessElectronicSupportDocumentJob`
- `SendRadianEventJob`

---

## 10. Contabilidad

### Plan Único de Cuentas (PUC Colombia)
El PUC completo debe cargarse como seed al crear cada tenant.
Estructura:
```
Clase (1 dígito) → Grupo (2) → Cuenta (4) → Subcuenta (6) → Auxiliar (8+)
Solo las cuentas auxiliares (último nivel) permiten movimientos contables.
```

### Motor contable
Patrón del proyecto de referencia: `AccountingEngineTrait`
Genera asientos automáticos (journal entries) al crear documentos.
Regla de oro contable: **Débito siempre debe igualar Crédito** en cada asiento.

---

## 11. Comunicación con el Usuario

- **Idioma:** español siempre
- **Mensajes de error y validación:** español
- **Comentarios en código:** español
- **Nombres de variables, clases, métodos:** inglés

---

## 12. Lo que NUNCA hacer en este proyecto

```
✗ Agregar company_id a tablas del schema tenant
✗ Usar SvelteKit (incompatible con Inertia.js)
✗ Usar JWT para autenticación web (solo Sanctum SPA)
✗ Usar driver database para colas (usar Redis)
✗ Crear API REST entre Laravel y Svelte para páginas web (Inertia lo maneja)
✗ Crear migraciones de "Fix..." — incorporar los fixes al schema original
✗ Usar $table->id() — siempre usar $table->uuid('id')->primary()
✗ Poner lógica de negocio en Controllers — va en Services
✗ Hardcodear el nombre del proveedor DIAN — usar DianProviderInterface
✗ Copiar migraciones del proyecto de referencia sin adaptar (ver Sección 6)
```

---

## 13. Documentos de referencia en este repositorio

| Documento | Contenido |
|---|---|
| `docs/ANALISIS.md` | Análisis completo, arquitectura, hoja de ruta por fases |
| `docs/ESTILO-VISUAL.md` | Sistema de diseño, paleta, componentes Svelte UI |

## 14. Proyecto de referencia externo

```
Ruta local: c:/laragon/www/xedoc-laravel-svelte/

Útil para consultar:
  - database/migrations/           → schema de referencia (adaptar, no copiar)
  - app/Models/                    → modelos (adaptar, ver Sección 7)
  - app/Services/                  → services de negocio (reutilizar)
  - app/Services/Nextpyme/Builders/→ builders UBL 2.1 DIAN (reutilizar)
  - app/Jobs/                      → jobs de facturación (reutilizar)
  - app/Traits/                    → traits contables y de negocio (reutilizar)
  - resources/js/Pages/            → componentes Svelte de referencia visual
```
