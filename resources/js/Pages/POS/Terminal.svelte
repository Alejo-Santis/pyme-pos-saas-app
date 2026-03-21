<script>
  import { router, page } from '@inertiajs/svelte'
  import { connect, isConnected, printRaw } from '@/Services/QzTrayService.js'
  import { buildReceipt } from '@/Services/ReceiptBuilder.js'
  import { toast } from '@/Stores/toast.svelte.js'

  let { terminal, shift, items, thirds, recentSales, taxes, company } = $props()

  // ── Estado de la venta ────────────────────────────────────────────────
  let lines         = $state([])
  let selectedThird = $state(null)
  let note          = $state('')
  let paymentForms  = $state([{ payment_form_id: 1, payment_method_id: 10, value: 0 }])
  let processing    = $state(false)
  let saleError     = $state('')       // error de validación inline en el carrito
  let lastSale      = $state(null)    // resultado de la última venta
  let lastSaleCtx   = $state(null)    // contexto completo para reimprimir
  let searchItem    = $state('')
  let searchThird   = $state('')

  // ── Estado de impresora ───────────────────────────────────────────────
  let printerReady    = $state(false)
  let printerChecking = $state(false)
  let printing        = $state(false)

  const hasPrinter = $derived(!!terminal.printer_name)

  // Intentar conectar a QZ Tray al cargar la página (si hay impresora configurada)
  $effect(() => {
    if (hasPrinter) initPrinter()
  })

  async function initPrinter() {
    printerChecking = true
    printerReady = await connect()
    printerChecking = false
  }

  // ── Catálogos de búsqueda ─────────────────────────────────────────────
  let filteredItems = $derived(
    searchItem.length < 2
      ? []
      : items.filter(i =>
          i.name.toLowerCase().includes(searchItem.toLowerCase()) ||
          (i.internal_code ?? '').toLowerCase().includes(searchItem.toLowerCase()) ||
          (i.barcode_one ?? '').includes(searchItem)
        ).slice(0, 8)
  )

  let filteredThirds = $derived(
    searchThird.length < 2
      ? []
      : thirds.filter(t =>
          (t.name ?? '').toLowerCase().includes(searchThird.toLowerCase()) ||
          (t.identification_number ?? '').includes(searchThird)
        ).slice(0, 6)
  )

  // ── Totales ───────────────────────────────────────────────────────────
  const calcLine = (line) => {
    const subtotal      = line.amount * line.sale_price
    const discountAmt   = subtotal * (line.discount / 100)
    const taxable       = subtotal - discountAmt
    const taxPercent    = (line.taxes ?? []).reduce((s, t) => s + (t.percent ?? 0), 0)
    const taxAmt        = taxable * (taxPercent / 100)
    return { subtotal, discountAmt, taxable, taxAmt, lineTotal: taxable + taxAmt }
  }

  let totals = $derived.by(() => {
    let subtotal = 0, discount = 0, tax = 0
    for (const l of lines) {
      const c = calcLine(l)
      subtotal += c.subtotal
      discount += c.discountAmt
      tax      += c.taxAmt
    }
    return { subtotal, discount, tax, total: subtotal - discount + tax }
  })

  let totalPaid    = $derived(paymentForms.reduce((s, p) => s + (Number(p.value) || 0), 0))
  let changeAmount = $derived(Math.max(0, totalPaid - totals.total))

  // ── Agregar artículo ──────────────────────────────────────────────────
  function addItem(item) {
    searchItem = ''
    const existing = lines.find(l => l.item_id === item.id && l.discount === 0)
    if (existing) {
      existing.amount++
      lines = [...lines]
      return
    }
    const itemTaxes = (item.taxes ?? []).map(t => ({ tax_id: t.id ?? t.tax?.id, percent: t.percent ?? t.tax?.percent ?? 0 }))
    lines = [...lines, {
      item_id:         item.id,
      description:     item.name,
      amount:          1,
      sale_price:      item.default_sale_price ?? 0,
      cost_value:      item.itemWarehouses?.[0]?.average_cost ?? 0,
      discount:        0,
      unit_measure_id: item.unit_measure_id ?? null,
      warehouse_out:   terminal.warehouse_id ?? null,
      movement_type:   'OUT',
      taxes:           itemTaxes,
    }]
  }

  function removeLine(idx) {
    lines = lines.filter((_, i) => i !== idx)
  }

  function setPaymentValue(idx, value) {
    paymentForms[idx].value = Number(value) || 0
    paymentForms = [...paymentForms]
  }

  function autoFillPayment() {
    if (paymentForms.length === 1) {
      paymentForms[0].value = totals.total
      paymentForms = [...paymentForms]
    }
  }

  // ── Procesar venta ────────────────────────────────────────────────────
  async function processSale() {
    if (lines.length === 0) { saleError = 'Agrega al menos un artículo.'; return }
    if (totalPaid < totals.total) { saleError = 'El pago no cubre el total.'; return }
    saleError = ''

    processing = true
    // Capturar contexto antes de limpiar
    const saleCtx = {
      lines:        [...lines],
      paymentForms: [...paymentForms],
      third:        selectedThird,
      totals:       { ...totals },
    }

    try {
      const res = await fetch(`/pos/${terminal.id}/sale`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        body: JSON.stringify({
          third_party_id: selectedThird?.id ?? null,
          payment_forms:  paymentForms,
          note,
          lines,
        }),
      })
      const data = await res.json()
      if (data.success) {
        lastSale    = data
        lastSaleCtx = { ...saleCtx, sale: data }
        const ticketNum = data.internal_code ?? data.number ?? ''
        toast.success(`Venta ${ticketNum} procesada — Cambio: $${Number(data.change ?? 0).toLocaleString('es-CO')}`)
        // Reset carrito
        lines         = []
        selectedThird = null
        note          = ''
        paymentForms  = [{ payment_form_id: 1, payment_method_id: 10, value: 0 }]
        // Recargar ventas recientes y estado del turno sin recargar toda la página
        router.reload({ only: ['recentSales', 'shift'], preserveScroll: true })
        // Imprimir automáticamente si hay impresora configurada
        if (hasPrinter) printReceipt(lastSaleCtx)
      } else {
        toast.error('Error al procesar la venta.')
      }
    } catch {
      toast.error('Error de red al procesar la venta.')
    } finally {
      processing = false
    }
  }

  // ── Impresión de recibo ────────────────────────────────────────────────
  async function printReceipt(ctx) {
    if (!hasPrinter) return
    printing = true
    try {
      if (!isConnected()) {
        const ok = await connect()
        if (!ok) {
          toast.warning('QZ Tray no está disponible. Instálalo en este equipo para imprimir.')
          return
        }
        printerReady = true
      }
      const data = buildReceipt({
        company:      company,
        terminal:     terminal,
        shift:        shift,
        sale:         ctx.sale,
        lines:        ctx.lines,
        paymentForms: ctx.paymentForms,
        third:        ctx.third,
        totals:       ctx.totals,
        is80mm:       terminal.printer_type === '80mm',
      })
      await printRaw(terminal.printer_name, data)
    } catch (err) {
      console.error('[Impresión] Error:', err)
      toast.error('Error al imprimir: ' + err.message)
    } finally {
      printing = false
    }
  }

  function clearSale() {
    lines         = []
    selectedThird = null
    note          = ''
    paymentForms  = [{ payment_form_id: 1, payment_method_id: 10, value: 0 }]
    lastSale      = null
  }

  function exitPOS() {
    router.visit('/pos')
  }

  const fmt = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
</script>

<!-- Layout propio para POS (sin sidebar) -->
<div class="flex h-screen bg-slate-100 overflow-hidden select-none">

  <!-- ── Columna izquierda: catálogo + líneas ─────────────────────────── -->
  <div class="flex flex-col flex-1 min-w-0">

    <!-- Topbar POS -->
    <header class="flex items-center justify-between bg-blue-700 px-4 h-12 shrink-0 text-white">
      <div class="flex items-center gap-3">
        <button onclick={exitPOS} class="text-blue-200 hover:text-white cursor-pointer" title="Salir">
          <i class="mdi mdi-arrow-left text-lg"></i>
        </button>
        <span class="font-semibold text-sm">{terminal.name}</span>
        <span class="text-blue-300 text-xs">· {terminal.warehouse?.name}</span>
      </div>
      <div class="flex items-center gap-4 text-xs text-blue-200">
        <span class="hidden sm:inline">Sesión: {shift.cashier_session_key}</span>
        <span>Res. {terminal.resolution?.prefix ?? ''} {terminal.resolution?.current_number}/{terminal.resolution?.to}</span>
        <span class="flex items-center gap-1 bg-blue-800/50 px-2 py-0.5 rounded">
          <i class="mdi mdi-cash-multiple"></i>
          Ventas: <strong class="text-white ml-0.5">${fmt(shift.total_sales)}</strong>
        </span>
        <span class="flex items-center gap-1 bg-blue-800/50 px-2 py-0.5 rounded">
          <i class="mdi mdi-cash-register"></i>
          Caja: <strong class="text-white ml-0.5">${fmt(Number(shift.initial_balance) + Number(shift.total_cash))}</strong>
        </span>
        <!-- Indicador de impresora -->
        {#if hasPrinter}
          <span class="flex items-center gap-1 px-2 py-0.5 rounded
            {printerChecking ? 'bg-blue-800/50 text-blue-300' : printerReady ? 'bg-green-700/60 text-green-200' : 'bg-red-700/50 text-red-200'}"
            title="{printerReady ? 'Impresora conectada: ' + terminal.printer_name : 'QZ Tray no disponible'}">
            <i class="mdi {printerChecking ? 'mdi-loading mdi-spin' : printerReady ? 'mdi-printer-check' : 'mdi-printer-off'} text-sm"></i>
            <span class="hidden md:inline">{printerChecking ? 'Conectando…' : printerReady ? 'Impresora OK' : 'Sin impresora'}</span>
          </span>
        {/if}
      </div>
    </header>

    <!-- Buscador de artículos -->
    <div class="relative px-3 py-2 bg-white border-b border-slate-200 shrink-0">
      <div class="relative">
        <i class="mdi mdi-barcode-scan absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
        <input
          type="text"
          bind:value={searchItem}
          placeholder="Buscar artículo por nombre, código o código de barras…"
          class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
          onkeydown={(e) => { if (e.key === 'Escape') searchItem = '' }}
        />
      </div>

      {#if filteredItems.length > 0}
        <div class="absolute left-3 right-3 top-full mt-1 z-30 bg-white rounded-lg shadow-lg border border-slate-200 divide-y divide-slate-100 max-h-64 overflow-y-auto">
          {#each filteredItems as item}
            <button
              onclick={() => addItem(item)}
              class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-blue-50 transition cursor-pointer"
            >
              <div>
                <p class="text-sm font-medium text-slate-800">{item.name}</p>
                <p class="text-xs text-slate-500">{item.internal_code ?? ''} · Stock: {item.itemWarehouses?.[0]?.stock ?? '—'}</p>
              </div>
              <span class="text-sm font-semibold text-slate-700">${fmt(item.default_sale_price)}</span>
            </button>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Tabla de líneas -->
    <div class="flex-1 overflow-y-auto">
      {#if lines.length === 0}
        <div class="flex flex-col items-center justify-center h-full text-slate-400 gap-3">
          <i class="mdi mdi-cart-outline text-5xl"></i>
          <p class="text-sm">Busca y agrega artículos para comenzar</p>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 sticky top-0 z-10">
            <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
              <th class="px-3 py-2">Artículo</th>
              <th class="px-3 py-2 w-20 text-center">Cant.</th>
              <th class="px-3 py-2 w-28 text-right">Precio</th>
              <th class="px-3 py-2 w-16 text-center">Desc %</th>
              <th class="px-3 py-2 w-28 text-right">Total</th>
              <th class="px-3 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each lines as line, i}
              {@const calc = calcLine(line)}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-3 py-2 font-medium text-slate-800">{line.description}</td>
                <td class="px-3 py-2">
                  <div class="flex items-center justify-center gap-1">
                    <button
                      onclick={() => { if (line.amount > 1) { line.amount--; lines = [...lines] } else removeLine(i) }}
                      class="w-6 h-6 rounded bg-slate-200 hover:bg-slate-300 text-xs font-bold flex items-center justify-center cursor-pointer"
                    >−</button>
                    <input
                      type="number"
                      min="0.001"
                      step="1"
                      bind:value={line.amount}
                      oninput={() => lines = [...lines]}
                      class="w-12 text-center text-sm border border-slate-300 rounded py-0.5 focus:outline-none focus:ring-1 focus:ring-blue-400"
                    />
                    <button
                      onclick={() => { line.amount++; lines = [...lines] }}
                      class="w-6 h-6 rounded bg-slate-200 hover:bg-slate-300 text-xs font-bold flex items-center justify-center cursor-pointer"
                    >+</button>
                  </div>
                </td>
                <td class="px-3 py-2 text-right">
                  <input
                    type="number"
                    min="0"
                    step="100"
                    bind:value={line.sale_price}
                    oninput={() => lines = [...lines]}
                    class="w-24 text-right text-sm border border-slate-300 rounded py-0.5 px-2 focus:outline-none focus:ring-1 focus:ring-blue-400"
                  />
                </td>
                <td class="px-3 py-2 text-center">
                  <input
                    type="number"
                    min="0"
                    max="100"
                    step="1"
                    bind:value={line.discount}
                    oninput={() => lines = [...lines]}
                    class="w-14 text-center text-sm border border-slate-300 rounded py-0.5 focus:outline-none focus:ring-1 focus:ring-blue-400"
                  />
                </td>
                <td class="px-3 py-2 text-right font-semibold text-slate-800">${fmt(calc.lineTotal)}</td>
                <td class="px-3 py-2">
                  <button onclick={() => removeLine(i)} class="text-slate-300 hover:text-red-500 transition cursor-pointer">
                    <i class="mdi mdi-close text-base"></i>
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      {/if}
    </div>

    <!-- Historial reciente (mini) -->
    {#if recentSales.length > 0}
      <div class="border-t border-slate-200 bg-white px-3 py-2 shrink-0">
        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide mb-1.5">Ventas de hoy</p>
        <div class="flex gap-2 overflow-x-auto pb-1">
          {#each recentSales as sale}
            <div class="shrink-0 text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 min-w-[100px]">
              <p class="font-mono font-medium text-slate-700">{sale.internal_code}</p>
              <p class="text-slate-500">${fmt(sale.total)}</p>
              <span class="text-[10px] {sale.electronic ? 'text-green-600' : 'text-amber-500'}">
                {sale.electronic ? '✓ DIAN' : '⏳ Pendiente'}
              </span>
            </div>
          {/each}
        </div>
      </div>
    {/if}

  </div>

  <!-- ── Columna derecha: cliente + pago + totales ─────────────────────── -->
  <div class="w-80 shrink-0 flex flex-col bg-white border-l border-slate-200">

    <!-- Cliente -->
    <div class="p-4 border-b border-slate-100">
      <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Cliente</p>

      {#if selectedThird}
        <div class="flex items-center justify-between bg-blue-50 rounded-lg px-3 py-2">
          <div>
            <p class="text-sm font-medium text-blue-900">{selectedThird.name}</p>
            <p class="text-xs text-blue-600">{selectedThird.identification_number}</p>
          </div>
          <button onclick={() => { selectedThird = null; searchThird = '' }} class="text-blue-300 hover:text-red-400 cursor-pointer">
            <i class="mdi mdi-close"></i>
          </button>
        </div>
      {:else}
        <div class="relative">
          <input
            type="text"
            bind:value={searchThird}
            placeholder="Consumidor final (opcional)"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          {#if filteredThirds.length > 0}
            <div class="absolute left-0 right-0 top-full mt-1 z-30 bg-white rounded-lg shadow-lg border border-slate-200 max-h-48 overflow-y-auto">
              {#each filteredThirds as t}
                <button
                  onclick={() => { selectedThird = t; searchThird = '' }}
                  class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50 cursor-pointer"
                >
                  <p class="font-medium">{t.name}</p>
                  <p class="text-xs text-slate-500">{t.identification_number}</p>
                </button>
              {/each}
            </div>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Totales -->
    <div class="p-4 border-b border-slate-100 space-y-1.5">
      <div class="flex justify-between text-sm text-slate-600">
        <span>Subtotal</span>
        <span>${fmt(totals.subtotal)}</span>
      </div>
      {#if totals.discount > 0}
        <div class="flex justify-between text-sm text-green-600">
          <span>Descuentos</span>
          <span>−${fmt(totals.discount)}</span>
        </div>
      {/if}
      <div class="flex justify-between text-sm text-slate-600">
        <span>IVA / Impuestos</span>
        <span>${fmt(totals.tax)}</span>
      </div>
      <div class="flex justify-between text-base font-bold text-slate-900 pt-1 border-t border-slate-200">
        <span>TOTAL</span>
        <span>${fmt(totals.total)}</span>
      </div>
    </div>

    <!-- Medios de pago -->
    <div class="p-4 border-b border-slate-100 space-y-2">
      <div class="flex items-center justify-between mb-1">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Pago</p>
        <button onclick={autoFillPayment} class="text-xs text-blue-600 hover:underline cursor-pointer">
          Pagar exacto
        </button>
      </div>

      {#each paymentForms as pf, idx}
        <div class="flex items-center gap-2">
          <select bind:value={pf.payment_method_id} class="flex-1 text-xs border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-blue-400">
            <option value={10}>Efectivo</option>
            <option value={42}>Transferencia</option>
            <option value={48}>Tarjeta Crédito</option>
            <option value={49}>Tarjeta Débito</option>
          </select>
          <input
            type="number"
            min="0"
            step="1000"
            bind:value={pf.value}
            oninput={() => { pf.value = Number(pf.value) || 0; paymentForms = [...paymentForms] }}
            class="w-28 text-sm text-right border border-slate-300 rounded-lg px-2 py-1.5 focus:ring-1 focus:ring-blue-400"
          />
        </div>
      {/each}

      {#if changeAmount > 0}
        <div class="flex justify-between text-sm font-semibold text-green-700 bg-green-50 rounded-lg px-3 py-2 mt-1">
          <span>Cambio</span>
          <span>${fmt(changeAmount)}</span>
        </div>
      {/if}
    </div>

    <!-- Nota -->
    <div class="px-4 py-3 border-b border-slate-100">
      <input
        type="text"
        bind:value={note}
        placeholder="Nota del documento (opcional)"
        class="w-full px-3 py-1.5 text-xs border border-slate-200 rounded-lg focus:ring-1 focus:ring-blue-400"
      />
    </div>

    <!-- Botones acción -->
    <div class="p-4 space-y-2 mt-auto">

      {#if lastSale}
        <div class="text-sm bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-green-700 mb-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <i class="mdi mdi-check-circle text-green-500 text-lg"></i>
              <span class="font-medium">{lastSale.internal_code}</span>
              <span>· ${fmt(lastSale.total)}</span>
            </div>
            {#if hasPrinter}
              <button
                onclick={() => printReceipt(lastSaleCtx)}
                disabled={printing}
                title="Reimprimir recibo"
                class="flex items-center gap-1 text-xs px-2 py-1 bg-white border border-green-300 rounded-lg text-green-700 hover:bg-green-100 transition cursor-pointer disabled:opacity-50">
                <i class="mdi {printing ? 'mdi-loading mdi-spin' : 'mdi-printer'} text-sm"></i>
                {printing ? 'Imprimiendo…' : 'Reimprimir'}
              </button>
            {/if}
          </div>
          {#if lastSale.change > 0}
            <p class="font-semibold mt-1 ml-6">Cambio: ${fmt(lastSale.change)}</p>
          {/if}
        </div>
      {/if}

      {#if saleError}
        <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-2.5">
          <i class="mdi mdi-alert-circle-outline flex-shrink-0"></i>
          {saleError}
        </div>
      {/if}

      <button
        onclick={processSale}
        disabled={processing || lines.length === 0 || totalPaid < totals.total}
        class="w-full flex items-center justify-center gap-2 py-3 text-base font-bold bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:opacity-50 transition cursor-pointer shadow-sm"
      >
        <i class="mdi {processing ? 'mdi-loading mdi-spin' : 'mdi-cash-register'} text-xl"></i>
        {processing ? 'Procesando…' : `Cobrar $${fmt(totals.total)}`}
      </button>

      <button
        onclick={clearSale}
        disabled={lines.length === 0}
        class="w-full py-2 text-sm text-slate-500 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 transition cursor-pointer"
      >
        <i class="mdi mdi-broom"></i> Limpiar venta
      </button>

    </div>

  </div>

</div>
