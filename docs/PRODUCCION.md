# Guía de Producción — ERP SaaS Colombia

> Esta guía explica cómo funciona el enrutamiento multi-tenant en producción,
> qué herramientas necesitas y cómo desplegar el sistema correctamente.

---

## 1. Cómo funcionan las URLs en producción

### El modelo de subdominio

El sistema usa subdominios para identificar cada empresa (tenant):

```
tudominio.com            → Landlord (registro, login admin, panel SaaS)
empresa1.tudominio.com   → Tenant "empresa1" (ERP de esa empresa)
empresa2.tudominio.com   → Tenant "empresa2" (ERP de otra empresa)
```

Cuando una empresa se registra con el slug `santinet`, su ERP queda en:
`santinet.tudominio.com`

### El flujo de una petición tenant

```
1. Usuario visita: santinet.tudominio.com/invoices
2. DNS resuelve *.tudominio.com → IP de tu servidor
3. Nginx recibe la petición y la pasa a PHP/Laravel
4. Middleware InitializeTenancyBySubdomain extrae "santinet" del host
5. Busca en tabla `domains` → encuentra tenant_id
6. Cambia search_path de PostgreSQL a schema tenant_santinet
7. Laravel sirve la página con datos de esa empresa
```

---

## 2. DNS — Configuración del dominio

Necesitas configurar **un solo registro wildcard** en tu proveedor de DNS:

```
Tipo    Nombre    Valor           TTL
A       @         IP_DEL_SERVIDOR 300
A       *         IP_DEL_SERVIDOR 300   ← wildcard para subdominos
AAAA    @         IPV6 (opcional) 300
AAAA    *         IPV6 (opcional) 300
```

El `*` cubre automáticamente: `empresa1.tudominio.com`, `empresa2.tudominio.com`, etc.
**No necesitas agregar registros DNS por cada empresa nueva que se registre.**

Proveedores DNS donde configurar esto: Cloudflare (recomendado), GoDaddy, Namecheap.

---

## 3. Nginx — Configuración del servidor web

Un solo `server block` maneja tanto el dominio central como todos los subdominios:

```nginx
# /etc/nginx/sites-available/pymepossaas
server {
    listen 80;
    listen [::]:80;
    server_name tudominio.com *.tudominio.com;

    # Redirigir todo HTTP → HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name tudominio.com *.tudominio.com;

    root /var/www/pymepossaas/public;
    index index.php;

    # SSL Wildcard (ver Sección 5)
    ssl_certificate     /etc/letsencrypt/live/tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tudominio.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    # Laravel — pasar todo a index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param SERVER_NAME $host;  # ← crítico para leer el subdominio
    }

    # Archivos estáticos — sin pasar por PHP
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Ocultar archivos sensibles
    location ~ /\.(ht|env|git) {
        deny all;
    }
}
```

Habilitar el sitio:
```bash
ln -s /etc/nginx/sites-available/pymepossaas /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

---

## 4. Variables de entorno (.env en producción)

```env
APP_NAME="Tu ERP Colombia"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com

# Dominio central (landlord) — SIN www, SIN protocolo
CENTRAL_DOMAIN=tudominio.com

# PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pymepossaas_prod
DB_USERNAME=pymepossaas_user
DB_PASSWORD=contraseña_segura

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Colas — OBLIGATORIO usar Redis, nunca database
QUEUE_CONNECTION=redis

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Sanctum — dominios SPA autorizados (wildcard)
SANCTUM_STATEFUL_DOMAINS=".tudominio.com"

# S3 / Cloudflare R2 para archivos
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu_key
AWS_SECRET_ACCESS_KEY=tu_secret
AWS_DEFAULT_REGION=auto
AWS_BUCKET=nombre-bucket
AWS_ENDPOINT=https://xxxx.r2.cloudflarestorage.com  # para R2

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.tudominio.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@tudominio.com
MAIL_PASSWORD=contraseña

# Sentry (errores en producción)
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/xxx

# Opcional: Telescope deshabilitado en producción
TELESCOPE_ENABLED=false
```

---

## 5. SSL Wildcard — Certificado para *.tudominio.com

Necesitas un certificado wildcard porque cada empresa tiene su propio subdominio.
Un certificado normal solo cubre `tudominio.com` — no los subdominios.

### Con Certbot + Cloudflare DNS (recomendado)

```bash
# Instalar certbot y plugin Cloudflare
apt install certbot python3-certbot-dns-cloudflare

# Crear archivo con token de API de Cloudflare
mkdir -p /root/.secrets
cat > /root/.secrets/cloudflare.ini << EOF
dns_cloudflare_api_token = TU_TOKEN_CLOUDFLARE_API
EOF
chmod 600 /root/.secrets/cloudflare.ini

# Solicitar certificado wildcard
certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /root/.secrets/cloudflare.ini \
  -d tudominio.com \
  -d "*.tudominio.com"

# Renovación automática (ya viene con certbot)
# Verificar: crontab -l o systemctl status certbot.timer
```

El certificado cubre `tudominio.com` Y todos los subdominios `*.tudominio.com`.

---

## 6. PostgreSQL — Configuración

```bash
# Crear usuario y base de datos
sudo -u postgres psql

CREATE USER pymepossaas_user WITH PASSWORD 'contraseña_segura';
CREATE DATABASE pymepossaas_prod OWNER pymepossaas_user;
GRANT ALL PRIVILEGES ON DATABASE pymepossaas_prod TO pymepossaas_user;
\q
```

El sistema crea automáticamente un schema por empresa (ej: `tenant_santinet`)
cuando se registra una nueva empresa. No necesitas hacer nada manual para esto.

---

## 7. Redis — Para colas y caché

```bash
apt install redis-server
systemctl enable redis-server
systemctl start redis-server

# Verificar
redis-cli ping  # → PONG
```

---

## 8. Supervisor — Para las colas (Horizon)

Laravel Horizon gestiona los workers de colas (emails, DIAN, etc.).
Supervisor asegura que Horizon siempre esté corriendo.

```bash
apt install supervisor
```

Crear archivo de configuración:
```ini
# /etc/supervisor/conf.d/pymepossaas-horizon.conf
[program:pymepossaas-horizon]
process_name=%(program_name)s
command=php /var/www/pymepossaas/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/pymepossaas-horizon.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start pymepossaas-horizon
supervisorctl status  # verificar que esté RUNNING
```

---

## 9. Despliegue del código — Flujo completo

### Primera vez (servidor nuevo)

```bash
# 1. Clonar el repositorio
cd /var/www
git clone git@github.com:tu-org/pymepossaas.git pymepossaas
cd pymepossaas

# 2. Instalar dependencias PHP (sin dev)
composer install --no-dev --optimize-autoloader

# 3. Instalar y compilar frontend
npm ci
npm run build

# 4. Configurar entorno
cp .env.example .env
# Editar .env con los valores de producción
nano .env
php artisan key:generate

# 5. Permisos
chown -R www-data:www-data storage bootstrap/cache
chmod -R 755 storage bootstrap/cache

# 6. Migraciones landlord (schema public)
php artisan migrate --force

# 7. Seeders landlord (planes, catalogos globales y super-admin)
php artisan db:seed --force

# 8. Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Actualizaciones (deploys posteriores)

```bash
cd /var/www/pymepossaas

# 1. Modo mantenimiento
php artisan down

# 2. Bajar código nuevo
git pull origin main

# 3. Actualizar dependencias si cambiaron
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 4. Migraciones (landlord + tenants)
php artisan migrate --force
php artisan tenants:migrate  # aplica a todos los tenants existentes
php artisan tenants:seed --class=TenantDatabaseSeeder

# 5. Regenerar caches
php artisan optimize

# 6. Reiniciar Horizon
php artisan horizon:terminate
supervisorctl restart pymepossaas-horizon

# 7. Salir de mantenimiento
php artisan up
```

---

## 10. Rutas — Cómo distingue Laravel landlord vs tenant

En `config/tenancy.php` está definido `CENTRAL_DOMAIN`.
En `bootstrap/app.php` o `routes/web.php` se carga:

```php
// landlord.php — solo para el dominio central (tudominio.com)
Route::middleware('web')->domain(config('tenancy.central_domains')[0])->group(
    base_path('routes/landlord.php')
);

// tenant.php — para subdominios (*.tudominio.com)
Route::middleware(['web', InitializeTenancyBySubdomain::class])->group(
    base_path('routes/tenant.php')
);
```

Cuando llega una petición a `santinet.tudominio.com`:
- NO ejecuta rutas de `landlord.php`
- SÍ ejecuta rutas de `tenant.php` + inicializa el schema

Cuando llega a `tudominio.com`:
- SÍ ejecuta rutas de `landlord.php` (registro, admin)
- NO inicializa ningún tenant

---

## 11. Stack recomendado para producción

### Opción A — VPS autogestinado (control total, más barato)

| Componente | Recomendación |
|---|---|
| VPS | Hetzner CX31 (€13/mes) o DigitalOcean Droplet $24/mes |
| OS | Ubuntu 24.04 LTS |
| Web server | Nginx |
| PHP | PHP 8.3-FPM |
| PostgreSQL | 16 (en el mismo VPS o RDS) |
| Redis | Redis 7 (en el mismo VPS) |
| Procesos | Supervisor para Horizon |
| SSL | Certbot + Cloudflare DNS |
| DNS | Cloudflare (gratis, wildcard DNS) |
| Storage archivos | Cloudflare R2 (gratis 10GB, sin egress) |
| CI/CD | GitHub Actions → deploy SSH |

### Opción B — Laravel Forge (simplificado, ~$19/mes)

[Laravel Forge](https://forge.laravel.com) automatiza todo lo anterior:
- Provisiona el VPS con Nginx + PHP + Redis + PostgreSQL
- Configura SSL automáticamente
- Panel web para deploys, workers, crons, variables de entorno
- Conectas tu repo GitHub → deploy automático en cada push

**Recomendado para empezar** si quieres enfocarte en el producto, no en DevOps.

### Opción C — Railway / Render (PaaS, sin gestión de servidor)

Para validación inicial con pocos clientes. Más costoso a escala.

---

## 12. Backups PostgreSQL

```bash
# Script de backup diario
# /usr/local/bin/backup-pymepossaas.sh

#!/bin/bash
DATE=$(date +%Y%m%d_%H%M)
BACKUP_DIR=/backups/pymepossaas
mkdir -p $BACKUP_DIR

# Dump completo (todos los schemas: public + todos los tenants)
pg_dump -U pymepossaas_user -h 127.0.0.1 pymepossaas_prod \
  | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Mantener solo los últimos 30 días
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup completado: backup_$DATE.sql.gz"
```

```bash
# Programar en cron (cada día a las 2am)
crontab -e
0 2 * * * /usr/local/bin/backup-pymepossaas.sh >> /var/log/backup.log 2>&1
```

Subir backups a S3/R2 para redundancia geográfica es altamente recomendado.

---

## 13. Checklist de go-live

```
[ ] Dominio comprado y DNS configurado (wildcard A record)
[ ] VPS provisionado con Ubuntu 24.04
[ ] Nginx instalado y configurado (ver Sección 3)
[ ] PHP 8.3-FPM instalado con extensiones: pgsql, redis, mbstring, xml, curl, zip
[ ] PostgreSQL 16 instalado, usuario y DB creados
[ ] Redis instalado y corriendo
[ ] Certbot configurado con certificado wildcard
[ ] Repositorio clonado en /var/www/pymepossaas
[ ] .env configurado con valores de producción
[ ] php artisan migrate --force (landlord/public)
[ ] php artisan db:seed --force (planes, catalogos globales, super-admin)
[ ] php artisan tenants:migrate y tenants:seed --class=TenantDatabaseSeeder ejecutados si ya existen tenants
[ ] npm run build ejecutado (assets compilados)
[ ] php artisan optimize ejecutado
[ ] Supervisor configurado con Horizon corriendo
[ ] Permisos correctos en storage/ y bootstrap/cache/
[ ] Admin user creado: php artisan db:seed --class=SuperAdminSeeder --force
[ ] Probada la URL: tudominio.com/admin/login
[ ] Probado el registro de empresa: tudominio.com/register
[ ] Verificado que el subdominio nuevo funciona después de registrar empresa
[ ] Backup automático configurado
```

---

## 14. Crear el primer admin en producción

Define estas variables en `.env`:

```bash
SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@tudominio.com
SUPER_ADMIN_PASSWORD="contraseña_muy_segura"
```

Luego ejecuta:

```bash
php artisan db:seed --class=SuperAdminSeeder --force
```

Luego accede a: `https://tudominio.com/admin/login`

---

## 15. Herramientas para aprender

### Esenciales (debes dominar)
- **Linux/Ubuntu CLI** — gestión básica del servidor
- **Nginx** — configuración de virtual hosts
- **Certbot** — SSL con Let's Encrypt
- **Supervisor** — gestión de procesos background
- **PostgreSQL** — backup, restore, monitoreo básico

### Recomendadas (agilizan el trabajo)
- **Laravel Forge** — abstrae toda la configuración del servidor
- **Cloudflare** — DNS + CDN + protección DDoS gratis
- **GitHub Actions** — CI/CD automatizado
- **Sentry** — monitoreo de errores en producción

### Para escalar (a futuro)
- **Laravel Octane** — servidor PHP persistente (Swoole/RoadRunner), 10x más rápido
- **PgBouncer** — pool de conexiones PostgreSQL (cuando haya 100+ tenants)
- **Redis Cluster** — cuando Redis sea el cuello de botella
