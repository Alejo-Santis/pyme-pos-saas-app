# PymePOS SaaS — ERP Colombia

Plataforma SaaS de facturación electrónica y gestión empresarial para Colombia.
Stack: **Laravel 12 · Svelte 5 · Inertia.js 2 · PostgreSQL 16 · Tailwind CSS 4**

---

## Requisitos

| Herramienta | Versión mínima |
|---|---|
| PHP | 8.2+ |
| PostgreSQL | 16 |
| Node.js | 20+ |
| Composer | 2 |
| Apache | 2.4 (con `mod_rewrite`) |

**Extensiones PHP requeridas:** `pdo_pgsql`, `pgsql`, `mbstring`, `xml`, `curl`, `zip`, `gd`, `bcmath`, `intl`

---

## Instalación local (Apache)

### 1. Clonar y dependencias

```bash
cd /var/www
git clone <repo-url> pyme-pos-saas-app
cd pyme-pos-saas-app

composer install
npm install
```

### 2. Variables de entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con tus valores locales:

```env
APP_NAME="PymePOS SaaS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://pymepossaas-app.test

# Dominio central — SIN protocolo, SIN barra final
CENTRAL_DOMAIN=pymepossaas-app.test

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pymepossaas_db
DB_USERNAME=postgres
DB_PASSWORD=tu_password

# Sin Redis en desarrollo local → usar database
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

> **Con Redis disponible** cambia `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`

### 3. Crear la base de datos en PostgreSQL

```sql
-- Desde psql o pgAdmin
CREATE DATABASE pymepossaas_db OWNER postgres;
```

### 4. Migraciones y seeders

#### 4a. Migraciones del Landlord (schema `public`)

Estas crean las tablas del SaaS: tenants, planes, dominios, catálogos DIAN, usuarios admin.

```bash
php artisan migrate
```

> Las migraciones en `database/migrations/` (raíz) son del landlord.
> Las de `database/migrations/tenant/` se ejecutan automáticamente al crear cada empresa.

#### 4b. Seeders del Landlord

Carga planes de suscripción, catálogos DIAN y el super-admin:

```bash
php artisan db:seed
```

Seeders incluidos:
- `PlansSeeder` — crea los 3 planes: Básico, Profesional, Empresarial
- `GlobalCatalogsSeeder` — catálogos DIAN (países, departamentos, municipios, tipos de persona, etc.)
- `SuperAdminSeeder` — usuario administrador del panel landlord

#### 4c. Migraciones del Tenant (por empresa)

Las migraciones tenant **se ejecutan automáticamente** cuando una empresa se registra en `/register`.
Si necesitas ejecutarlas manualmente en todos los tenants existentes:

```bash
# Migrar todos los tenants existentes
php artisan tenants:migrate

# Migrar un tenant específico
php artisan tenants:migrate --tenants=slug-de-la-empresa

# Con rollback
php artisan tenants:migrate:rollback

# Fresh (¡elimina todos los datos del tenant!)
php artisan tenants:migrate:fresh
```

#### 4d. Seeders del Tenant

```bash
# Ejecutar en todos los tenants
php artisan tenants:seed --class=TenantDatabaseSeeder

# Seeders tenant individuales, si necesitas repetir solo una parte
php artisan tenants:seed --class=PucSeeder
php artisan tenants:seed --class=AccountingConceptSeeder
php artisan tenants:seed --class=PayrollLegalParametersSeeder
php artisan tenants:seed --class=TenantRolesSeeder
```

### 5. Compilar assets frontend

```bash
# Producción (una sola vez)
npm run build

# Desarrollo con HMR (correr en terminal separada)
npm run dev
```

---

## Configuración Apache para multi-tenancy

El sistema usa **subdominios** para identificar cada empresa:
```
pymepossaas-app.test          → Landlord (landing, registro, admin)
empresa1.pymepossaas-app.test → Tenant "empresa1"
empresa2.pymepossaas-app.test → Tenant "empresa2"
```

### VirtualHost con wildcard de subdominio

Crear `/etc/apache2/sites-available/pymepossaas.conf`:

```apache
<VirtualHost *:80>
    ServerName pymepossaas-app.test
    ServerAlias *.pymepossaas-app.test

    DocumentRoot /var/www/pyme-pos-saas-app/public

    <Directory /var/www/pyme-pos-saas-app/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/pymepossaas-error.log
    CustomLog ${APACHE_LOG_DIR}/pymepossaas-access.log combined
</VirtualHost>
```

Habilitar y recargar:

```bash
sudo a2ensite pymepossaas.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

### Permisos de carpetas

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Agregar dominios para pruebas locales

Cada empresa registrada (ej: slug `santinet`) queda en `santinet.pymepossaas-app.test`.
Apache ya atiende el wildcard, pero el sistema operativo necesita resolver ese dominio.

### Opción A — `/etc/hosts` (manual, una línea por empresa)

```bash
sudo nano /etc/hosts
```

```
127.0.0.1  pymepossaas-app.test
127.0.0.1  santinet.pymepossaas-app.test
127.0.0.1  miempresa.pymepossaas-app.test
127.0.0.1  demo.pymepossaas-app.test
```

> Agrega una línea cada vez que registres una empresa nueva.

### Opción B — dnsmasq con wildcard automático (recomendado)

Con `dnsmasq` **todos los subdominios** `*.test` resuelven a `127.0.0.1` sin tocar `/etc/hosts`.

**Ubuntu/Debian:**

```bash
sudo apt install dnsmasq

# Regla wildcard para *.test
echo "address=/.test/127.0.0.1" | sudo tee /etc/dnsmasq.d/test-domains.conf

sudo systemctl enable dnsmasq
sudo systemctl restart dnsmasq
```

Configurar el resolver del sistema para usar dnsmasq:

```bash
# Con NetworkManager (Ubuntu moderno)
sudo mkdir -p /etc/NetworkManager/conf.d
echo -e "[main]\ndns=dnsmasq" | sudo tee /etc/NetworkManager/conf.d/dnsmasq.conf
sudo systemctl restart NetworkManager
```

Verificar:
```bash
ping santinet.pymepossaas-app.test
# Debe responder desde 127.0.0.1
```

**macOS (Homebrew):**

```bash
brew install dnsmasq
echo "address=/.test/127.0.0.1" >> $(brew --prefix)/etc/dnsmasq.conf
sudo brew services restart dnsmasq

sudo mkdir -p /etc/resolver
echo "nameserver 127.0.0.1" | sudo tee /etc/resolver/test
```

**Windows (Laragon):**

Laragon gestiona dominios `.test` automáticamente. Configura el dominio base en las preferencias de Laragon y los subdominios quedan disponibles sin configuración adicional.

---

## Crear el primer super-admin

```bash
php artisan tinker
```

```php
use App\Modules\Admin\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

AdminUser::create([
    'name'     => 'Super Admin',
    'email'    => 'admin@pymepossaas-app.test',
    'password' => Hash::make('password'),
]);
```

Acceder en: `http://pymepossaas-app.test/admin/login`

---

## Registrar una empresa de prueba

1. Ir a `http://pymepossaas-app.test/register`
2. Completar el formulario (nombre empresa, NIT, admin, contraseña, plan)
3. El sistema crea automáticamente:
   - Schema `tenant_{slug}` en PostgreSQL
   - Todas las migraciones del tenant
   - Roles y permisos (Spatie)
   - Plan Único de Cuentas — PUC Colombia
   - Usuario administrador dentro del schema
4. Redirige al login del subdominio: `http://{slug}.pymepossaas-app.test/login`

> Si usas `/etc/hosts`, recuerda agregar `127.0.0.1 {slug}.pymepossaas-app.test` antes de abrir el login.

---

## Comandos útiles

```bash
# Levantar servidor + queue + Vite en paralelo
composer run dev

# Limpiar todas las caches
php artisan optimize:clear

# Ver logs en tiempo real
php artisan pail

# Tests
./vendor/bin/pest

# Listar tenants registrados
php artisan tenants:list

# Ejecutar código dentro de un tenant
php artisan tinker
>>> tenancy()->initialize('slug-del-tenant');
>>> // código aquí
>>> tenancy()->end();

# Resetear schema de un tenant (¡borra todos sus datos!)
php artisan tenants:migrate:fresh --tenants=slug-del-tenant
```

---

## Estructura de migraciones

```
database/
├── migrations/               ← Landlord (schema public — ejecutar con: php artisan migrate)
│   ├── create_tenants_table.php
│   ├── create_domains_table.php
│   ├── create_plans_table.php
│   ├── create_subscriptions_table.php
│   ├── create_global_catalogs_table.php
│   ├── create_dian_catalogs_table.php
│   ├── create_landlord_users_table.php
│   └── ...
│
└── migrations/tenant/        ← Tenant (schema tenant_{slug} — auto al registrar empresa)
    ├── create_users_table.php
    ├── create_permission_tables.php
    ├── create_companies_table.php
    ├── create_resolutions_table.php
    ├── create_third_parties_table.php
    ├── create_items_table.php
    ├── create_documents_table.php
    ├── create_accounting_table.php
    ├── create_pos_table.php
    ├── create_chart_of_accounts_table.php
    ├── create_payroll_tables.php
    └── ...
```

**Regla fundamental:** Ninguna tabla del schema tenant tiene `company_id`.
La empresa está implícita en el `search_path` de PostgreSQL.

---

## Mapa de URLs

| URL | Descripción |
|---|---|
| `pymepossaas-app.test/` | Landing page pública |
| `pymepossaas-app.test/register` | Registro de nueva empresa |
| `pymepossaas-app.test/admin/login` | Login super-admin |
| `pymepossaas-app.test/admin/dashboard` | Panel super-admin |
| `{slug}.pymepossaas-app.test/login` | Login del ERP de la empresa |
| `{slug}.pymepossaas-app.test/dashboard` | Dashboard del ERP |
| `{slug}.pymepossaas-app.test/invoices` | Facturación electrónica |
| `{slug}.pymepossaas-app.test/pos` | Punto de venta |
| `{slug}.pymepossaas-app.test/inventory` | Inventario |
| `{slug}.pymepossaas-app.test/accounting` | Contabilidad |

---

## Despliegue en producción

Ver [docs/PRODUCCION.md](docs/PRODUCCION.md) para la guía completa con Nginx, SSL wildcard, Supervisor y Redis.
