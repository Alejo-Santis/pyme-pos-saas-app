# Pendientes — Seguridad, concurrencia y preparación para producción

> Creado: 2026-08-28
> Contexto: auditoría de seguridad/escalabilidad pensando en llegar a varios
> clientes (tenants) activos simultáneamente. Ninguno de estos puntos se
> implementó todavía — es la lista para retomar mañana.

---

## Crítico — bugs de concurrencia que existen hoy

- [ ] **Sobreventa de inventario**: `ToolTrait::updateItemInventory()`
  (`app/Shared/Traits/ToolTrait.php`) lee el stock, calcula y actualiza sin
  `lockForUpdate()`. Dos cajeros vendiendo el último ítem al mismo tiempo
  pueden ambos pasar la validación y vender lo mismo dos veces.
  → Agregar bloqueo pesimista sobre la fila de `item_warehouse` (o un
  decremento atómico condicionado `WHERE stock >= cantidad`).

- [ ] **Doble turno de caja**: `PosController::openShift()`
  (`app/Modules/POS/Controllers/PosController.php`) valida "¿hay turno
  activo?" y luego crea, sin transacción ni lock — dos clics rápidos o dos
  pestañas pueden abrir turno en la misma terminal simultáneamente.
  → Envolver la verificación + `updateOrCreate` en una transacción con
  `lockForUpdate()` sobre la fila de `pos_terminal_users` en cuestión.

- [ ] **`documents.internal_code` sin UNIQUE en la base de datos**: el
  locking de `InternalCodeService` es sólido en cómo se usa hoy (los 4
  llamadores — Invoice/CreditNote/DebitNote/SupportDocument — están
  correctamente envueltos en su transacción externa), pero sin el
  constraint a nivel de BD no hay última línea de defensa si algo cambia
  mañana (ej. una inserción manual, un bug futuro).
  → Migración nueva: índice único sobre `internal_code` (nullable-safe).

## Alto — bloqueante para producción real con clientes

- [ ] `.env` actual: `APP_ENV=local`, `APP_DEBUG=true` — si esto llega a
  producción tal cual, cualquier error expone stack traces completos
  (rutas del servidor, queries SQL, variables) a un usuario final.
  → Crear `.env.production` de referencia + checklist de despliegue.

- [ ] `SESSION_SECURE_COOKIE` sin definir y `SESSION_ENCRYPT=false` — la
  cookie de sesión viajaría sin el flag `Secure` y el contenido de sesión
  no está cifrado en la tabla `sessions`.

- [ ] `QUEUE_CONNECTION=database` sin Redis/Horizon — el propio
  `CLAUDE.md` del proyecto ya marca esto como prohibido. Con varios
  tenants generando jobs de DIAN/PDF/imports en paralelo, la tabla `jobs`
  se vuelve cuello de botella y no hay panel de reintentos/monitoreo.

- [ ] `spatie/laravel-backup` está instalado pero **sin ningún archivo de
  configuración** (`config/backup.php` no existe) — hoy no hay backups
  automáticos corriendo pese a que la dependencia ya está en el proyecto.

- [ ] Sin Sentry ni ningún error tracking en producción — si algo falla,
  nadie se entera salvo que el cliente lo reporte.

## Medio

- [ ] El rate limiting agregado hasta ahora solo cubre login/registro
  (`throttle:5,1` en `/login`, `/admin/login`, `/register`). Los
  endpoints de importación masiva (Excel) y generación de PDF/reportes
  son pesados en CPU/IO y también deberían tener throttle — con varios
  tenants activos, uno solo podría tumbar el servidor sin querer.

- [ ] Quedan 52 advisories de `composer audit` sin revisar (detectadas al
  instalar `pragmarx/google2fa-laravel`, pero preexistentes al proyecto).
  Falta clasificar cuáles afectan paquetes que realmente corren en
  producción vs. dependencias de desarrollo.

---

## Sugerencia de orden para retomar

1. Los 3 críticos de concurrencia (cambios acotados, alto impacto).
2. Checklist de producción (4-8: en su mayoría configuración, no código).
3. Medio (9-10) si el tiempo alcanza.
