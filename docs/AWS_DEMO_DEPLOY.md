# Deploy Demo en AWS Free Tier + DuckDNS

Esta guía es para un ambiente de pruebas con pocos usuarios: compañeros, clientes demo o validación comercial. No reemplaza una arquitectura productiva con backups, monitoreo, colas dedicadas y dominio wildcard propio.

## 1. Modelo de dominios

El sistema identifica cada empresa por dominio completo.

### Opción recomendada para producción real

Usar un dominio propio con wildcard:

```text
erp.tudominio.com          -> landlord / registro
cliente1.erp.tudominio.com -> tenant cliente1
cliente2.erp.tudominio.com -> tenant cliente2
```

Requiere DNS wildcard:

```text
A  erp       IP_SERVIDOR
A  *.erp     IP_SERVIDOR
```

`.env`:

```env
APP_URL=https://erp.tudominio.com
CENTRAL_DOMAIN=erp.tudominio.com
TENANT_DOMAIN_MODE=subdomain
TENANT_DOMAIN_SUFFIX=
```

### Opción demo con DuckDNS

DuckDNS normalmente no da wildcard debajo de `miapp.duckdns.org`. Para demo usa dominios completos por tenant:

```text
miapp.duckdns.org     -> landlord / registro
cliente1.duckdns.org  -> tenant cliente1
cliente2.duckdns.org  -> tenant cliente2
```

En DuckDNS debes crear/actualizar cada dominio que quieras usar y apuntarlo a la IP pública del servidor. El registro dentro del ERP crea automáticamente el tenant y guarda `cliente1.duckdns.org` en la tabla `domains`.

`.env`:

```env
APP_URL=https://miapp.duckdns.org
CENTRAL_DOMAIN=miapp.duckdns.org
TENANT_DOMAIN_MODE=suffix
TENANT_DOMAIN_SUFFIX=duckdns.org
```

## 2. Servidor AWS

Instancia sugerida para demo:

```text
Ubuntu 24.04 LTS
1 vCPU / 1 GB RAM
Disco 20-30 GB
Security Group: 22, 80, 443
```

Instala paquetes:

```bash
sudo apt update
sudo apt install -y nginx postgresql postgresql-contrib redis-server unzip git curl supervisor
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl
```

Instala Composer y Node.js según tu método preferido. Para compilar local en el servidor necesitas Node 20+.

## 3. Base de datos

```bash
sudo -u postgres psql
```

```sql
CREATE USER pymepos_user WITH PASSWORD 'CAMBIA_ESTA_PASSWORD';
CREATE DATABASE pymepos_demo OWNER pymepos_user;
GRANT ALL PRIVILEGES ON DATABASE pymepos_demo TO pymepos_user;
\q
```

## 4. Código y variables

```bash
cd /var/www
sudo git clone TU_REPO pyme-pos-saas-app
sudo chown -R $USER:www-data pyme-pos-saas-app
cd pyme-pos-saas-app
cp .env.example .env
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
```

Configura `.env` para DuckDNS demo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://miapp.duckdns.org

CENTRAL_DOMAIN=miapp.duckdns.org
TENANT_DOMAIN_MODE=suffix
TENANT_DOMAIN_SUFFIX=duckdns.org

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pymepos_demo
DB_USERNAME=pymepos_user
DB_PASSWORD=CAMBIA_ESTA_PASSWORD

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

SUPER_ADMIN_NAME="Super Admin"
SUPER_ADMIN_EMAIL=admin@tuempresa.com
SUPER_ADMIN_PASSWORD=CAMBIA_ESTA_PASSWORD_SEGURA
```

Inicializa landlord:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan optimize
```

## 5. Nginx

Para DuckDNS demo necesitas que Nginx acepte el dominio central y los posibles dominios tenant:

```nginx
server {
    listen 80;
    server_name miapp.duckdns.org cliente1.duckdns.org cliente2.duckdns.org;

    root /var/www/pyme-pos-saas-app/public;
    index index.php;

    client_max_body_size 25M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param SERVER_NAME $host;
    }

    location ~ /\.(ht|env|git) {
        deny all;
    }
}
```

Si luego agregas `cliente3.duckdns.org`, agrégalo a `server_name` y recarga Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Con dominio propio wildcard puedes usar:

```nginx
server_name erp.tudominio.com *.erp.tudominio.com;
```

## 6. SSL

Para demo puedes iniciar en HTTP, pero si usarás clientes reales de prueba, usa HTTPS.

Con dominios DuckDNS individuales:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d miapp.duckdns.org -d cliente1.duckdns.org -d cliente2.duckdns.org
```

Cada nuevo tenant DuckDNS requiere agregar el dominio al certificado o emitir otro certificado.

## 7. Queue Worker

Archivo `/etc/supervisor/conf.d/pymepos-worker.conf`:

```ini
[program:pymepos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pyme-pos-saas-app/artisan queue:work redis --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/pyme-pos-saas-app/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start pymepos-worker:*
```

## 8. Permisos

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 9. Flujo de prueba demo

1. Entra a `https://miapp.duckdns.org/register`.
2. Registra empresa con identificador `cliente1`.
3. El sistema crea tenant, migra schema, siembra defaults y redirige a `https://cliente1.duckdns.org/login`.
4. Si usas DuckDNS, asegúrate antes de que `cliente1.duckdns.org` apunte a la IP del servidor y esté en Nginx/SSL.
5. Entra al tenant y sigue `/setup`.

## 10. Comandos útiles

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan tenants:migrate
php artisan tenants:seed --class=TenantDatabaseSeeder
npm run build
sudo systemctl reload nginx
sudo supervisorctl restart pymepos-worker:*
```

## Nota importante

Para creación automática de tenants sin tocar DNS/Nginx por cada empresa, compra un dominio propio y usa wildcard DNS. DuckDNS sirve para demo, pero no es cómodo para multi-tenant automático masivo.
