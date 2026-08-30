# Sentry en producción

> Sentry ya está instalado y conectado en el código (`sentry/sentry-laravel`,
> wireado en `bootstrap/app.php` vía `Integration::handles($exceptions)`).
> Hoy no hace nada porque falta el DSN — esta guía es solo para activarlo.
> Verificar con `php artisan about` → sección "Sentry": mientras diga
> `Enabled: MISSING DSN`, el SDK sigue en no-op y nada de lo de abajo aplica.

---

## 1. Crear el proyecto en Sentry

1. Crear cuenta en [sentry.io](https://sentry.io) (tienen plan gratuito, suficiente para arrancar).
2. Crear una organización si no tienes una.
3. **New Project** → plataforma **Laravel** → nombre sugerido: `pyme-pos-saas-prod`.
4. Sentry te muestra el DSN en la pantalla de setup. Si la cierras, se recupera en
   **Settings → Projects → (tu proyecto) → Client Keys (DSN)**.

El DSN se ve así: `https://xxxxxxxx@oXXXXXX.ingest.us.sentry.io/XXXXXXX`

## 2. Configurar el `.env` del servidor

Agregar en el `.env` de **producción** (no en desarrollo):

```env
SENTRY_LARAVEL_DSN=https://xxxxxxxx@oXXXXXX.ingest.us.sentry.io/XXXXXXX
SENTRY_ENVIRONMENT=production
```

Luego, en el servidor:

```bash
php artisan config:clear
```

(si `config:cache` está activo en el deploy, correr `php artisan config:cache` de nuevo después de esto)

## 3. Verificar que está llegando

```bash
php artisan sentry:test
```

Esto dispara una excepción de prueba y la envía a Sentry. Debería aparecer
en el dashboard del proyecto en segundos, bajo **Issues**.

También se puede confirmar sin generar ruido, solo revisando el estado:

```bash
php artisan about
```

Busca la sección `Sentry` — debe decir `Enabled: ENABLED` (no `MISSING DSN`)
y mostrar el `Environment` que configuraste.

## 4. Qué captura automáticamente

Con el wiring actual (`Integration::handles($exceptions)` en
`bootstrap/app.php`), Sentry recibe **toda excepción no manejada** que
llegue al exception handler de Laravel — errores 500, excepciones en jobs
de cola, comandos artisan, etc. No hace falta tocar código para que un bug
nuevo empiece a reportarse.

Para capturar algo puntual a mano (por ejemplo, un error que ya atrapaste
con `try/catch` pero quieres que quede registrado igual):

```php
use function Sentry\captureException;

try {
    $this->algoQuePuedeFallar();
} catch (\Throwable $e) {
    captureException($e);
    // seguir manejando el error normalmente
}
```

## 5. Variables opcionales (todas con default razonable, no son obligatorias)

| Variable | Para qué | Default |
|---|---|---|
| `SENTRY_ENVIRONMENT` | Distingue `production` de `staging` en el dashboard | vacío |
| `SENTRY_RELEASE` | Etiqueta cada error con la versión/commit desplegado (ver abajo) | vacío |
| `SENTRY_SAMPLE_RATE` | % de errores que se envían (1.0 = todos) | `1.0` |
| `SENTRY_TRACES_SAMPLE_RATE` | % de requests con tracing de performance (0.1–0.2 es razonable en un SaaS con tráfico real; déjalo vacío al inicio) | desactivado |
| `SENTRY_SEND_DEFAULT_PII` | Si se envían datos como IP/email del usuario autenticado | `false` (dejar así — datos de clientes DIAN) |

`SENTRY_RELEASE` conviene setearlo en el script de deploy con el hash del commit:

```bash
export SENTRY_RELEASE=$(git rev-parse --short HEAD)
```

y agregarlo al `.env` (o exportarlo antes de `php artisan config:cache`) —
así cada error en Sentry queda asociado al commit exacto que lo causó.

## 6. Notificaciones

Por defecto Sentry solo notifica dentro de su dashboard/app. Para que avise
por email o Slack cuando algo se rompe:

**Settings → Projects → (tu proyecto) → Alerts** → crear una regla (ej:
"nuevo issue" o "issue reaparece después de resuelto") y elegir el canal
(email ya viene activo para los miembros del proyecto; Slack requiere
conectar la integración una vez en **Settings → Integrations**).

## 7. Problemas comunes

- **`Enabled: MISSING DSN` después de configurar el `.env`** → falta
  `php artisan config:clear` (o `config:cache` de nuevo si el deploy cachea config).
- **No llegan errores pero `sentry:test` sí funcionó** → revisar que el
  código que falla realmente lance una excepción no capturada; un `catch`
  silencioso en el propio código nunca llega a Sentry salvo que se llame
  `captureException()` a mano (ver punto 4).
- **Se llenó de ruido con errores esperados** (ej: validaciones 422,
  intentos de login fallidos) → Sentry ya filtra por defecto las excepciones
  HTTP más comunes (404, 419, 422, etc.) vía su lista de `dont_report`
  interna; si algo específico sigue apareciendo y no debería, se puede
  publicar el config completo con `php artisan sentry:publish --dsn=...`
  (genera `config/sentry.php` editable) y agregarlo a `ignore_exceptions`.
