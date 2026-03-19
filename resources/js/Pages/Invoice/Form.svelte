<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    document      = null,
    company       = null,
    resolutions   = [],
    thirds        = [],
    items         = [],
    taxes         = [],
    documentTypes = [],
  } = $props()

  const isEdit = $derived(!!document)

  // Mapas para búsqueda rápida
  const taxMap  = Object.fromEntries(taxes.map(t => [String(t.id), t]))
  const itemMap = Object.fromEntries(items.map(i => [i.id, i]))

  // ── Encabezado ──────────────────────────────────────────────────────────
  // Este formulario es EXCLUSIVAMENTE para Facturas de Venta (tipo 01).
  // Las Notas Crédito (91) y Notas Débito (92) se emiten desde el detalle
  // de una factura existente. El Documento Soporte (05) se genera desde
  // el detalle de una Orden de Compra recibida.
  let hdr = $state({
    type_document_id:           '1',
    type_document_operation_id: '1',
    third_party_id:             document?.third_party_id  ?? '',
    resolution_id:              document?.resolution_id   ?? '',
    issue_date:                 document?.issue_date      ?? new Date().toISOString().slice(0, 10),
    payment_form:               String(document?.payment_forms?.[0] ?? '1'),
    note:                       document?.note            ?? '',
  })

  // ── Líneas ───────────────────────────────────────────────────────────────
  const emptyLine = () => ({
    item_id:         '',
    description:     '',
    amount:          1,
    sale_price:      0,
    discount:        0,
    cost_value:      0,
    unit_measure_id: '',
    taxes:           [],
    _tax_percent:    0,
    _tax_name:       '',
  })

  let lines = $state(
    document?.lines?.length
      ? document.lines.map(l => ({
          item_id:         l.item_id         ?? '',
          description:     l.description     ?? '',
          amount:          Number(l.amount)  || 1,
          sale_price:      Number(l.sale_price) || 0,
          discount:        Number(l.discount)   || 0,
          cost_value:      Number(l.cost_value) || 0,
          unit_measure_id: l.unit_measure_id ?? '',
          taxes:           l.taxes           ?? [],
          _tax_percent:    l.taxes?.[0]?.percent ?? 0,
          _tax_name:       '',
        }))
      : [emptyLine()]
  )

  let errors  = $state({})
  let loading = $state(false)

  // Auto-relleno al seleccionar artículo
  function onItemSelect(idx) {
    const item = itemMap[lines[idx].item_id]
    if (!item) return
    lines[idx].description     = item.name
    lines[idx].sale_price      = Number(item.default_sale_price) || 0
    lines[idx].unit_measure_id = item.unit_measure_id ?? ''
    const tax = taxMap[String(item.tax_category_id)]
    if (tax) {
      lines[idx]._tax_percent = Number(tax.percent) || 0
      lines[idx]._tax_name    = tax.name
      lines[idx].taxes        = [{ percent: Number(tax.percent) || 0 }]
    } else {
      lines[idx]._tax_percent = 0
      lines[idx]._tax_name    = ''
      lines[idx].taxes        = []
    }
  }

  function addLine()       { lines.push(emptyLine()) }
  function removeLine(idx) { if (lines.length > 1) lines.splice(idx, 1) }

  // ── Cálculos por línea ───────────────────────────────────────────────────
  const lineBase     = (l) => Number(l.amount) * Number(l.sale_price)
  const lineDiscount = (l) => lineBase(l) * (Number(l.discount) / 100)
  const lineSubtotal = (l) => lineBase(l) - lineDiscount(l)
  const lineTax      = (l) => lineSubtotal(l) * (Number(l._tax_percent) / 100)
  const lineTotal    = (l) => lineSubtotal(l) + lineTax(l)

  // ── Totales globales ─────────────────────────────────────────────────────
  const subtotal       = $derived(lines.reduce((s, l) => s + lineSubtotal(l), 0))
  const totalDiscounts = $derived(lines.reduce((s, l) => s + lineDiscount(l), 0))
  const totalTax       = $derived(lines.reduce((s, l) => s + lineTax(l), 0))
  const total          = $derived(subtotal + totalTax)

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  // ── Envío ────────────────────────────────────────────────────────────────
  function submit() {
    loading = true
    errors  = {}

    const payload = {
      company_id:                  company?.id ?? '',
      type_document_id:            Number(hdr.type_document_id),
      type_document_operation_id:  Number(hdr.type_document_operation_id),
      third_party_id:              hdr.third_party_id  || null,
      resolution_id:               hdr.resolution_id   || null,
      issue_date:                  hdr.issue_date,
      currency_id:                 35, // COP
      payment_forms:               [hdr.payment_form],
      lines: lines.map(l => ({
        item_id:         l.item_id         || null,
        description:     l.description,
        amount:          Number(l.amount),
        sale_price:      Number(l.sale_price),
        discount:        Number(l.discount)   || 0,
        cost_value:      Number(l.cost_value) || 0,
        unit_measure_id: l.unit_measure_id || null,
        taxes:           l.taxes,
      })),
    }

    const url    = isEdit ? `/invoices/${document.id}` : '/invoices'
    const method = isEdit ? 'put' : 'post'

    router[method](url, payload, {
      onSuccess: () => { loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-6xl mx-auto space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center gap-3">
      <a use:inertia href="/invoices" class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-slate-800">
          {isEdit ? 'Editar documento' : 'Nuevo documento'}
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {isEdit ? `${document.prefix ?? ''}${document.number ?? ''}` : 'Facturación electrónica DIAN'}
        </p>
      </div>
    </div>

    {#if errors.document}
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
        <i class="mdi mdi-alert-circle-outline mr-1"></i>{errors.document}
      </div>
    {/if}

    <!-- ── Sección 1: Encabezado del documento ──────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-file-document-outline text-primary"></i>
          Encabezado del documento
          <span class="ml-2 text-xs font-normal text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
            Factura de Venta · tipo 01
          </span>
        </h2>
      </div>

      <!-- Aviso informativo sobre NC / ND / DS -->
      <div class="px-5 pt-4">
        <div class="flex items-start gap-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-xs text-blue-700">
          <i class="mdi mdi-information-outline text-blue-500 text-sm mt-0.5 flex-shrink-0"></i>
          <span>
            Este formulario es para <strong>Facturas de Venta</strong>.
            Para emitir una <strong>Nota Crédito</strong> o <strong>Nota Débito</strong>,
            abre el detalle de una factura existente y usa el botón correspondiente.
            Para el <strong>Documento Soporte</strong>, ve al detalle de una Orden de Compra recibida.
          </span>
        </div>
      </div>

      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        <!-- Fecha de emisión -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Fecha de emisión <span class="text-red-500">*</span>
          </label>
          <input bind:value={hdr.issue_date} type="date"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.issue_date ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.issue_date}<p class="text-xs text-red-500 mt-1">{errors.issue_date}</p>{/if}
        </div>

        <!-- Forma de pago -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Forma de pago</label>
          <div class="flex gap-2">
            {#each [['1','Contado','mdi-cash'], ['2','Crédito','mdi-credit-card-outline']] as [val, lbl, icon]}
              <label class="flex-1 cursor-pointer">
                <input bind:group={hdr.payment_form} type="radio" value={val} class="sr-only">
                <div class="flex items-center justify-center gap-1.5 border-2 rounded-lg px-2 py-2 text-xs font-medium transition
                  {hdr.payment_form === val
                    ? 'border-primary bg-primary/5 text-primary'
                    : 'border-slate-200 text-slate-500 hover:border-slate-300'}">
                  <i class="mdi {icon} text-sm"></i>
                  {lbl}
                </div>
              </label>
            {/each}
          </div>
        </div>

        <!-- Tercero -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Tercero (cliente)</label>
          <select bind:value={hdr.third_party_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">— Consumidor final —</option>
            {#each thirds as t}
              <option value={t.id}>
                {t.identification_number}{t.dv ? `-${t.dv}` : ''} · {t.name}{t.surname ? ` ${t.surname}` : ''}
              </option>
            {/each}
          </select>
        </div>

        <!-- Resolución DIAN (solo si es FEV) -->
        {#if hdr.type_document_id === '1'}
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Resolución DIAN</label>
            <select bind:value={hdr.resolution_id}
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
              <option value="">Sin resolución</option>
              {#each resolutions as r}
                <option value={r.id}>{r.prefix ?? ''} · Res. {r.resolution_number ?? r.id}</option>
              {/each}
            </select>
          </div>
        {/if}

      </div>
    </div>

    <!-- ── Sección 2: Líneas de detalle ─────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-format-list-bulleted text-primary"></i>
          Líneas de detalle
        </h2>
        <button onclick={addLine} type="button"
          class="flex items-center gap-1.5 text-xs font-medium text-primary hover:bg-primary/5 px-3 py-1.5 rounded-lg border border-primary/30 transition cursor-pointer">
          <i class="mdi mdi-plus text-sm"></i>
          Agregar línea
        </button>
      </div>

      <!-- Tabla de líneas -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="text-left px-3 py-2.5 font-medium text-slate-500 text-xs w-8">#</th>
              <th class="text-left px-3 py-2.5 font-medium text-slate-500 text-xs min-w-44">Artículo</th>
              <th class="text-left px-3 py-2.5 font-medium text-slate-500 text-xs min-w-36">Descripción</th>
              <th class="text-right px-3 py-2.5 font-medium text-slate-500 text-xs w-20">Cant.</th>
              <th class="text-right px-3 py-2.5 font-medium text-slate-500 text-xs w-28">P. Venta</th>
              <th class="text-right px-3 py-2.5 font-medium text-slate-500 text-xs w-16">Dto %</th>
              <th class="text-left px-3 py-2.5 font-medium text-slate-500 text-xs w-24">Impuesto</th>
              <th class="text-right px-3 py-2.5 font-medium text-slate-500 text-xs w-28">Subtotal</th>
              <th class="w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each lines as line, idx}
              <tr class="hover:bg-slate-50/50">
                <td class="px-3 py-2 text-slate-400 text-xs">{idx + 1}</td>

                <!-- Selector de artículo -->
                <td class="px-3 py-2">
                  <select
                    bind:value={line.item_id}
                    onchange={() => onItemSelect(idx)}
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">Libre</option>
                    {#each items as item}
                      <option value={item.id}>{item.internal_code ? `[${item.internal_code}] ` : ''}{item.name}</option>
                    {/each}
                  </select>
                </td>

                <!-- Descripción -->
                <td class="px-3 py-2">
                  <input bind:value={line.description} type="text" placeholder="Descripción…"
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </td>

                <!-- Cantidad -->
                <td class="px-3 py-2">
                  <input bind:value={line.amount} type="number" min="0.001" step="0.001"
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </td>

                <!-- Precio de venta -->
                <td class="px-3 py-2">
                  <input bind:value={line.sale_price} type="number" min="0" step="0.01"
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </td>

                <!-- Descuento -->
                <td class="px-3 py-2">
                  <input bind:value={line.discount} type="number" min="0" max="100" step="0.01"
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                </td>

                <!-- Impuesto -->
                <td class="px-3 py-2">
                  <select
                    onchange={(e) => {
                      const t = taxes.find(t => String(t.id) === e.target.value)
                      if (t) {
                        line._tax_percent = Number(t.percent) || 0
                        line._tax_name    = t.name
                        line.taxes        = [{ percent: Number(t.percent) || 0 }]
                      } else {
                        line._tax_percent = 0
                        line._tax_name    = ''
                        line.taxes        = []
                      }
                    }}
                    class="w-full border border-slate-200 rounded-md px-2 py-1.5 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                    <option value="">0%</option>
                    {#each taxes as tax}
                      <option value={String(tax.id)} selected={line._tax_percent === Number(tax.percent)}>
                        {tax.name} ({tax.percent}%)
                      </option>
                    {/each}
                  </select>
                </td>

                <!-- Subtotal (calculado) -->
                <td class="px-3 py-2 text-right font-semibold text-slate-700 text-xs tabular-nums">
                  ${fmt(lineTotal(line))}
                </td>

                <!-- Eliminar -->
                <td class="px-3 py-2">
                  <button onclick={() => removeLine(idx)} type="button"
                    disabled={lines.length === 1}
                    class="p-1 text-slate-300 hover:text-red-400 disabled:opacity-30 transition cursor-pointer">
                    <i class="mdi mdi-close text-base"></i>
                  </button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      {#if errors.lines}
        <div class="px-5 py-2 text-xs text-red-500 border-t border-slate-100">
          {typeof errors.lines === 'string' ? errors.lines : 'Verifica las líneas del documento.'}
        </div>
      {/if}

      <!-- Totales -->
      <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
        <div class="flex justify-end">
          <div class="space-y-1.5 min-w-64">
            <div class="flex justify-between text-sm text-slate-600">
              <span>Subtotal</span>
              <span class="tabular-nums font-medium">${fmt(subtotal + totalDiscounts)}</span>
            </div>
            {#if totalDiscounts > 0}
              <div class="flex justify-between text-sm text-slate-500">
                <span>Descuentos</span>
                <span class="tabular-nums text-red-600">-${fmt(totalDiscounts)}</span>
              </div>
              <div class="flex justify-between text-sm text-slate-600">
                <span>Base gravable</span>
                <span class="tabular-nums font-medium">${fmt(subtotal)}</span>
              </div>
            {/if}
            {#if totalTax > 0}
              <div class="flex justify-between text-sm text-slate-500">
                <span>IVA</span>
                <span class="tabular-nums">${fmt(totalTax)}</span>
              </div>
            {/if}
            <div class="flex justify-between font-bold text-slate-800 text-base border-t border-slate-300 pt-2 mt-1">
              <span>Total</span>
              <span class="tabular-nums">${fmt(total)}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Acciones ─────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between pb-6">
      <a use:inertia href="/invoices"
        class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
        Cancelar
      </a>
      <button onclick={submit} disabled={loading}
        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition disabled:opacity-60 cursor-pointer">
        {#if loading}
          <i class="mdi mdi-loading mdi-spin text-base"></i>
          Guardando…
        {:else}
          <i class="mdi mdi-content-save-outline text-base"></i>
          {isEdit ? 'Actualizar documento' : 'Crear documento'}
        {/if}
      </button>
    </div>

  </div>
</AppLayout>
