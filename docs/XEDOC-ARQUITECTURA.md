# Arquitectura xedoc → nextPosSaas

> Documento de referencia: reproduce la arquitectura completa de `xedoc-laravel-svelte`
> adaptada al stack moderno de `nextPosSaas` (Laravel 12 + Svelte 5 + multi-tenancy UUID).
>
> **REGLA:** Antes de escribir cualquier módulo nuevo, consultar este documento.

---

## 1. Visión general del sistema

```
nextPosSaas es una copia mejorada de xedoc con:
  - Multi-tenancy real (schema-per-tenant, sin company_id)
  - UUID en todas las tablas
  - Svelte 5 Runes (en lugar de Svelte 4 stores)
  - Tailwind 4 (en lugar de Bootstrap 5)
  - Estructura modular en app/Modules/ (en lugar de carpetas planas)

La ESENCIA del negocio es la misma:
  ERP colombiano → Facturación electrónica DIAN como core,
  con POS, Inventario, Contabilidad, Compras, Caja, Nómina, Reportes.
```

---

## 2. Flujo central: creación de un documento de venta

Este es el flujo más importante. Todo el sistema gira alrededor de él.

```
Usuario (Svelte) → POST /transactions/create
         ↓
TransactionController::createTransaction()
  1. Valida con FormRequest
  2. DB::beginTransaction()
  3. InternalCodeService::reserveInternalCode(typeOpId)  ← atómico, evita duplicados
  4. Document::create([...])  ← una fila con todo: invoice_lines(json), taxes(json), etc.
  5. Por cada línea:
     a. DocumentsDetail::create([...])
     b. updateItemInventory() → item_warehouse (stock + costo promedio)
     c. updateItemStocktaking() → item_stocktakings (kardex)
  6. createPaymentReceiptsFromDocument()  ← payment_receipts + payment_receipts_details
  7. DocumentHistories::create("Documento creado")
  8. Actualizar consecutivo en resolutions / sends
  9. DB::commit()
         ↓
DocumentCreateObserver::created(Document)
  - Si type_document_operation_id == 1 (Venta):
      ProcessElectronicInvoiceJob::dispatch($document, attempt=1)
  - Si type_document_operation_id == 5 (Documento Soporte):
      ProcessElectronicSupportDocumentJob::dispatch($document, attempt=1)
         ↓
ProcessElectronicInvoiceJob (queue: redis)
  - Delay: intento 1=0s, 2=5s, 3=30s (backoff exponencial)
  - InvoiceJsonBuilder::fromDocument($document)  → construye JSON UBL 2.1
  - ElectronicDocumentsProcessorService::process($builder, 'invoice', 'is_invoice')
         ↓
ElectronicDocumentsProcessorService
  - ApiNextpymeService::makeRequest('POST', '/ubl2.1/send-bill-sync', $json)
  - Guarda respuesta en sending_electronic_documents
  - Si exitoso: document.electronic=true, document.cufe=...
  - Si error 90 (Regla 90 — ya procesado): marca como válido
  - Si error recuperable: reprograma job con delay
  - Si error fatal: marca como fallido en sending_electronic_documents
```

---

## 3. Módulos y su relación con el documento

### 3.1 Módulo Invoice (Documentos de venta)

**Qué hace:**
- Gestiona documentos de VENTA: Facturas (01), Notas Crédito (91), Notas Débito (92)
- Envía automáticamente a DIAN via Observer + Job
- Permite consulta de estado DIAN, reintento de envío, descarga XML/PDF
- Gestión de eventos RADIAN (acuse, reclamo, recibo bienes, aceptación)

**No hace POS.** El POS tiene su propio controlador y flujo.

**Tablas principales:**
- `documents` — fila central con TODO el documento
- `documents_details` — líneas de detalle (items, cantidades, precios, taxes)
- `document_histories` — auditoría de cambios
- `document_payment_methods` — pivot: qué medios de pago se usaron
- `sending_electronic_documents` — historial de intentos DIAN (respuestas, errores, CUFE)
- `events` — eventos RADIAN (CUFE + CUDE de cada evento enviado)
- `document_corrections` — correcciones a documentos ya enviados
- `document_transactions_id` — UUID de transacción DIAN

**Clases PHP necesarias:**
```
app/Modules/Invoice/
├── Models/
│   ├── Document.php            ✓ (mejorar: Observer, relaciones completas)
│   ├── DocumentLine.php        ✓ (renombrar: DocumentDetail más fiel a xedoc)
│   ├── DocumentHistory.php     ✗ falta
│   ├── SendingElectronicDocument.php  ✗ falta (CRÍTICO)
│   └── Event.php               ✗ falta
├── Controllers/
│   └── TransactionController.php  ✗ (InvoiceController actual es simplificado)
├── Services/
│   ├── InternalCodeService.php     ✗ falta (CRÍTICO)
│   ├── ElectronicDocumentsProcessorService.php  ✗ falta (CRÍTICO)
│   ├── ApiNextpymeService.php      ✗ falta (CRÍTICO)
│   ├── InvoiceService.php          ~ (presente pero incompleto)
│   ├── RefundAvailabilityService.php  ✗ falta
│   └── CreditNoteCashbackService.php  ✗ falta
├── Builders/
│   ├── InvoiceJsonBuilder.php      ✗ falta (CRÍTICO — adaptar de xedoc)
│   ├── CreditNoteJsonBuilder.php   ✗ falta
│   ├── SupportDocumentBuilder.php  ✗ falta
│   └── EventJsonBuilder.php        ✗ falta
├── Jobs/
│   ├── ProcessElectronicInvoiceJob.php       ✗ falta (CRÍTICO)
│   ├── ProcessElectronicCreditNoteJob.php    ✗ falta
│   ├── ProcessElectronicSupportDocumentJob.php  ✗ falta
│   └── SendSingleRadianEventJob.php          ✗ falta
└── Observers/
    └── DocumentCreateObserver.php  ✗ falta (CRÍTICO — trigger del flujo FE)
```

### 3.2 Módulo POS (Punto de Venta)

**Qué hace:**
- Gestiona terminales POS (`pos_terminals`)
- Maneja turnos de caja (`cash_register_counts` → abrir/cerrar)
- Crea documentos de venta IGUAL que Invoice pero con contexto POS
  (`type_document_operation_id = 4` en xedoc, con `pos_terminal_id` en el documento)
- Genera ticket en formato ESC/POS para impresoras térmicas
- Retiros de caja (`cash_with_drawals`)
- Resumen de cierre (`cash_register_summaries`)

**Tablas propias:**
- `pos_terminals` — ← **FALTA en nuestra migración** (tenemos pos_terminal_users pero no pos_terminals)
- `pos_terminal_users` ✓
- `cash_register_counts` ✓
- `cash_register_summaries` ← **FALTA**
- `cash_with_drawals` ✓

### 3.3 Módulo Inventory (Items)

**Tablas correctamente creadas:**
- `items` ✓, `item_groups` ✓, `item_lines` ✓
- `item_taxes` ✓, `item_price_lists` ✓
- `item_warehouse` ✓ (stock por bodega)
- `item_stocktakings` ✓ (kardex — **se llena desde el flujo de transacción**)
- `item_inventory_controls` ✓
- `item_by_third_parties` ✓
- `item_presentations` ✓

**Falta:**
- `item_price_changes` — historial de cambios de precio
- `item_price_history_by_suppliers` — historial precios de compra
- `cost_of_sales` — costo de ventas calculado

**Nota:** El módulo `item_categories` que creamos (con `parent_id`) NO existe en xedoc.
xedoc usa `item_groups` + `item_lines` (dos dimensiones de clasificación). Nuestro
`item_categories` es una mejora válida, pero hay que decidir: ¿mantenemos ambas o solo groups/lines?

### 3.4 Módulo Accounting (Contabilidad)

**Tablas correctamente creadas:**
- `accounting_concepts` ✓
- `accounting_documents` ✓
- `accounting_documents_details` ✓
- `initial_balance_accounts` ✓

**Falta:**
- `accounting_parameters` ← **CRÍTICO** — mapea tipos de operación → cuentas PUC
  Sin esto, el `AccountingEngineTrait` no puede generar asientos automáticos
- `cost_centers` ← CRÍTICO (referenciado en accounting_documents_details)
- `type_operation_accounts` — configuración de cuentas por tipo de operación

**Código faltante:**
- `AccountingEngineTrait` — el motor contable (adaptar de xedoc)

### 3.5 Módulo Cash/Banks (Caja y Bancos)

**Tablas faltantes (TODO):**
- `banks` — catálogo de bancos
- `bank_accounts` — cuentas bancarias de la empresa (polimórfico con accountable)
- `bank_account_movements` — movimientos bancarios
- `cash_boxes` — cajas de efectivo (polimórfico con MinorAccountingAccount)
- `cash_movements` — movimientos de caja
- `income_and_expenses` ✓ (ya existe en pos migration)
- `withholdings` — retenciones (actualmente solo referenciada)
- `expenses` — conceptos de gastos

### 3.6 Módulo Purchases (Compras)

**Tablas presentes:**
- `purchase_orders` ✓
- `items_purchase_orders` ✓
- `purchase_order_histories` ✓
- `document_file_purchase_orders` ✓
- `purchase_in_progress_mail_boxes` ✓

**Flujo de compra:**
```
OC aprobada → se convierte en documento de compra
  (type_document_operation_id = 2, o 5 para soporte)
  → mismo flujo de documentos, actualiza kardex con movimiento IN
  → genera asientos contables (Inventario Débito + CXP Crédito)
```

### 3.7 Módulo Transfers (Traslados)

**Tablas presentes:**
- `transfers` ✓, `items_transfer` ✓, `transfer_histories` ✓

**Flujo:**
```
Traslado aprobado → InventoryService::processApprovalWithDocument()
  → crea Document (tipo traslado)
  → DocumentDetail OUT de bodega origen
  → DocumentDetail IN a bodega destino
  → actualiza item_warehouse en ambas bodegas
  → genera item_stocktakings (kardex entrada y salida)
```

---

## 4. Tablas globales (public schema — catálogos DIAN)

Estas tablas viven en el schema `public` y son compartidas por todos los tenants.
Se cargan como seeds una vez. Los tenants solo las leen via `DB::table()` o `DB::connection('pgsql')`.

```
type_documents          — Factura(01), NC(91), ND(92), DS(05), etc.
type_document_operations — Venta(1), Compra(2), POS(4), Soporte(5), etc.
type_organizations      — Persona Natural, Persona Jurídica
type_document_identifications — CC, NIT, CE, Pasaporte, etc.
type_regimes            — Responsable de IVA, No responsable, etc.
type_liabilities        — Responsabilidades fiscales DIAN
payment_forms           — Contado(1), Crédito(2)
payment_methods         — Efectivo, Tarjeta, Transferencia, etc.
unit_measures           — Unidad, Kilo, Metro, etc. (cod. DIAN)
type_currencies         — COP, USD, EUR
taxes                   — IVA 19%, IVA 5%, INC, etc. con sus porcentajes
type_thirds             — Cliente, Proveedor, Empleado, Acreedor, etc.
type_events             — 030 Acuse, 031 Reclamo, 032 Recibo bienes, 033 Aceptación
departments / municipalities — Colombia: 33 dptos, ~1123 municipios
item_clasifications     — Producto, Servicio, Otro
item_tax_categories     — Gravado, Excluido, Exento
```

---

## 5. InvoiceJsonBuilder — estructura del JSON DIAN

El builder más crítico. Construye el payload para la API Nextpyme:

```php
// Estructura del JSON que espera Nextpyme para una factura:
[
  "resolution_number" => "...",
  "type_document_id"  => 1,           // 1=FEV, 91=NC, 92=ND, 5=DS
  "prefix"            => "FV",
  "number"            => 1,
  "type_currency_id"  => 35,          // COP
  "payment_form_id"   => 1,           // 1=Contado
  "payment_method_id" => 10,          // 10=Efectivo
  "date"              => "2024-01-15",
  "time"              => "10:30:00",
  "customer"          => [             // ThirdParty data
    "identification_number" => "900123456",
    "dv"                    => 7,
    "name"                  => "Empresa XYZ",
    "phone"                 => "3001234567",
    "address"               => "Calle 1 # 2-3",
    "email"                 => "email@empresa.com",
    "type_document_identification_id" => 6,  // NIT
    "type_organization_id"  => 2,
    "type_regime_id"        => 2,
    "type_liability_id"     => 14,
    "municipality_id"       => 149,
  ],
  "legal_monetary_totals" => [
    "line_extension_amount"   => 100000.00,   // subtotal sin IVA
    "tax_exclusive_amount"    => 100000.00,
    "tax_inclusive_amount"    => 119000.00,   // total con IVA
    "allowance_total_amount"  => 0.00,        // descuentos
    "payable_amount"          => 119000.00,   // total a pagar
  ],
  "tax_totals" => [
    ["tax_id" => 1, "tax_amount" => 19000.00, "taxable_amount" => 100000.00, "percent" => 19.00],
  ],
  "invoice_lines" => [
    [
      "unit_measure_id"       => 70,     // cod. unidad DIAN
      "invoiced_quantity"     => 2,
      "line_extension_amount" => 50000.00,
      "free_of_charge"        => false,
      "description"           => "Producto X",
      "code"                  => "ART-001",
      "type_item_identification_id" => 4,
      "price_amount"          => 25000.00,
      "base_quantity"         => 1,
      "tax_totals" => [
        ["tax_id" => 1, "tax_amount" => 9500.00, "taxable_amount" => 50000.00, "percent" => 19.00],
      ],
    ],
  ],
  "send_email"  => true,
  "email"       => "cliente@email.com",
]
```

---

## 6. Diagnóstico del proyecto actual vs xedoc

### ✅ Correcto / bien alineado
- Migraciones de tablas principales: documents, documents_details, items, item_warehouse, etc.
- Modelos básicos: Document, ThirdParty, Item, Establishment, Warehouse
- Autenticación multi-tenant, onboarding, panel admin SaaS
- UI: dashboard, terceros, items, establecimientos, bodegas (correcto y mejorado)
- Estructura de módulos en app/Modules/ (mejor organizado que xedoc)

### ⚠️ Parcial / requiere completar
- `InvoiceService` — presente pero simplificado: falta movimiento de inventario,
  kardex, recibos de pago, historial del documento, InternalCodeService atómico
- `Item.php` — falta relaciones: `itemTaxes()`, `itemWarehouses()`, `priceLists()`, `presentations()`
- `Document.php` — falta relaciones: `histories()`, `sendings()`, `paymentMethods()` (belongsToMany), `withholdings()`
- Migraciones POS: falta `pos_terminals` y `cash_register_summaries`
- Migraciones contabilidad: falta `accounting_parameters`, `cost_centers`
- Migraciones caja/bancos: falta `banks`, `bank_accounts`, `bank_account_movements`, `cash_boxes`, `cash_movements`

### ❌ Crítico / falta por completo (no funciona FE DIAN sin esto)
- `sending_electronic_documents` — tabla de tracking de envíos DIAN
- `DocumentCreateObserver` — trigger automático del flujo electrónico
- `ProcessElectronicInvoiceJob` — job con backoff para DIAN
- `ElectronicDocumentsProcessorService` — orquestador de la respuesta DIAN
- `ApiNextpymeService` — cliente HTTP para Nextpyme
- `InvoiceJsonBuilder` — construcción del JSON UBL 2.1
- `InternalCodeService` — generación atómica de códigos internos
- Tabla `pos_terminals` — el POS no puede funcionar sin ella

### 🔄 Decisión de diseño (xedoc vs mejoras)
- `item_categories` (nuestro) vs `item_groups` + `item_lines` (xedoc):
  xedoc usa dos dimensiones. Nuestras categorías con jerarquía es una simplificación.
  **Recomendación**: agregar `item_groups` y `item_lines` compatibles con xedoc,
  mantener `item_categories` como categoría adicional opcional.
- Un solo `TransactionController` (xedoc) vs módulos separados `Invoice/`, `Purchases/` (nuestro):
  **Mantenemos la organización modular** — es una mejora arquitectural válida.

---

## 7. Plan de trabajo corregido

### Fase actual: completar el núcleo de documentos

**Prioridad 1 — Tablas faltantes críticas** (migración única):
- `pos_terminals`
- `cash_register_summaries`
- `sending_electronic_documents`
- `accounting_parameters`, `cost_centers`
- `banks`, `bank_accounts`, `bank_account_movements`
- `cash_boxes`, `cash_movements`
- `withholdings`, `expenses`

**Prioridad 2 — Motor de documentos completo:**
- `InternalCodeService`
- `DocumentCreateObserver` registrado en `EventServiceProvider`
- `ProcessElectronicInvoiceJob` (con backoff)
- `ElectronicDocumentsProcessorService`
- `ApiNextpymeService`
- `InvoiceJsonBuilder` (adaptar de xedoc)

**Prioridad 3 — UI correcta de Transacciones:**
- La vista `Invoice/Index` muestra todos los documentos (FEV, NC, ND)
- La vista `Invoice/Form` crea con líneas + medios de pago + referencia a OC si aplica
- `Invoice/Show` muestra estado DIAN, CUFE, permite reenvío/descarga
- Ruta `/invoices/purchase` para documentos de compra (separado visualmente)

**Prioridad 4 — POS:**
- CRUD de terminales POS
- Flujo de turno (abrir/cerrar caja)
- Vista de venta POS (simple, rápida, táctil)

---

## 8. Referencia rápida de type_document_operation_id

| ID | Nombre | Tipo documento | Genera FE |
|---|---|---|---|
| 1 | Venta | Factura (01) / NC (91) / ND (92) | Sí |
| 2 | Compra | Factura proveedor | Depende |
| 3 | Devolución Venta | NC automática | Sí |
| 4 | POS | Factura POS | Sí (POSFE) |
| 5 | Documento Soporte | DS (05) | Sí |
| 6 | Traslado | Interno | No |
| 7 | Ajuste inventario | Interno | No |
| 8 | Recibo de Pago | Interno | No |

---

## 9. Traits a implementar (adaptar de xedoc)

```php
// app/Shared/Traits/AccountingEngineTrait.php
// Genera asientos automáticos al crear cualquier documento.
// Requiere: accounting_parameters configurados con cuentas PUC

// app/Shared/Traits/ToolTrait.php
// Helpers: createDocumentHistory(), updateItemInventory(), updateItemStocktaking(),
//          createPaymentReceiptsFromDocument(), createSystemNotification()

// app/Shared/Traits/ElectronicDocumentTrait.php
// Helpers para sending_electronic_documents: getOrCreateSending()
```
