# Pendientes del Proyecto — ERP SaaS Colombia

> Última actualización: Marzo 2026
> Estado: Producto funcional. Facturación, POS, Inventario, Contabilidad, Caja, Compras operativos.

---

## ✅ Módulos completados

| Módulo | Descripción |
|---|---|
| Auth + Onboarding | Registro de empresa, login por subdominio, wizard inicial |
| Panel super-admin | Gestión tenants, planes CRUD completo, suscripciones |
| Configuración empresa | Datos tributarios, DIAN (api_path_fe, api_token_fe), resoluciones |
| Establecimientos y bodegas | CRUD con relaciones |
| Terceros | Clientes y proveedores |
| Inventario | Artículos, categorías, traslados entre bodegas |
| Facturación electrónica | CRUD documentos, Nota Crédito, observer DIAN automático |
| Motor contable | Asientos automáticos en ventas/compras/NC |
| PUC Colombia | chart_of_accounts + seeder 60+ cuentas |
| Contabilidad | Libro Diario, Libro Mayor, Balance de Prueba |
| Reportes financieros | Estado de Resultados (P&G), Balance General |
| Caja y bancos | Cajas, cuentas bancarias, movimientos |
| POS | Terminales, turnos, ventas → integración caja |
| Compras | Órdenes, aprobación, recepción |
| Reportes operativos | Ventas, Caja/turnos, Inventario |

---

## ❌ Pendientes

### 1. Nómina colombiana (prioridad media)
Módulo completo. El más complejo del ERP.
- [ ] Modelo `Employee` con datos laborales (cargo, salario, fecha ingreso, tipo contrato)
- [ ] Configuración de devengados: salario, horas extra, comisiones, bonificaciones
- [ ] Configuración de deducciones: salud (4%), pensión (4%), fondo solidaridad
- [ ] Liquidación mensual automática según tabla DIAN
- [ ] Prestaciones sociales: prima (jun/dic), cesantías, intereses cesantías, vacaciones
- [ ] Integración PILA (Planilla Integrada de Liquidación de Aportes)
- [ ] Nómina electrónica DIAN (JobProcessElectronicPayrollJob — ya existe parcialmente)
- [ ] Vista: listado empleados, formulario, liquidación mensual, historial

### 2. Configuración de cuentas contables por empresa (prioridad baja)
Hoy el motor contable usa códigos PUC hardcodeados en `AccountingEngineTrait::defaultPucCode()`.
- [ ] UI en `/config/accounting` para que cada empresa mapee sus cuentas
- [ ] CRUD de `accounting_concepts`: `type_concept` = `"{opId}_{slug}"` → `accountable_id` = código PUC
- [ ] Afecta: ventas (1_CXC, 1_INGRESO, etc.), compras (14_CXP...), NC (91_...)
- [ ] Mientras no se configure, el motor usa los defaults del PUC (funciona correctamente)

### 3. Libro Mayor — mejoras (prioridad baja)
- [ ] Exportar a Excel/PDF
- [ ] Saldo inicial del período (carryforward de períodos anteriores)
- [ ] Filtro por tercero (ver movimientos de un cliente/proveedor específico)

### 4. Reportes financieros — mejoras (prioridad baja)
- [ ] Estado de Flujo de Caja (método indirecto)
- [ ] Comparativo año anterior vs año actual
- [ ] Exportar a Excel (Maatwebsite ya instalado)
- [ ] Exportar a PDF (DomPDF ya instalado)

### 5. Buzón tributario DIAN (prioridad baja)
Tabla `tax_mailboxes` ya existe en el schema.
- [ ] Recepción automática de facturas electrónicas de proveedores
- [ ] Vista de bandeja de entrada tributaria
- [ ] Conversión a orden de compra

---

## 🚀 Producción — Guía completa

Ver: `docs/PRODUCCION.md` (archivo separado con instrucciones detalladas)

---

## 📋 Deuda técnica

- [ ] Tests (Pest PHP) — ningún test escrito aún. Prioritario antes de lanzar.
- [ ] Horizon UI — el worker de colas está en Redis pero no hay dashboard de monitoreo
- [ ] Telescope — deshabilitado en producción, configurar Sentry para errores
- [ ] Rate limiting en rutas API externas
- [ ] Política de backups automáticos PostgreSQL
