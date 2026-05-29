<script>
  import { useForm, router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { order, warehouses } = $props()

  const statusConfig = {
    draft:     { label: 'Borrador',   cls: 'bg-slate-100 text-slate-700' },
    pending:   { label: 'Pendiente',  cls: 'bg-yellow-100 text-yellow-800' },
    approved:  { label: 'Aprobada',   cls: 'bg-blue-100 text-blue-800' },
    partial:   { label: 'Parcial',    cls: 'bg-orange-100 text-orange-800' },
    received:  { label: 'Recibida',   cls: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Anulada',    cls: 'bg-red-100 text-red-800' },
  }

  const cfg = $derived(statusConfig[order.status] ?? { label: order.status, cls: 'bg-slate-100 text-slate-700' })
  const lines = $derived(order.items ?? [])
  const itemCount = $derived(lines.length)
  const totalQuantity = $derived(lines.reduce((sum, line) => sum + Number(line.invoice_quantity ?? 0), 0))
  const subtotal = $derived(lines.reduce((sum, line) => sum + Number(line.line_extension_amount ?? 0), 0))
  const avgUnitCost = $derived(totalQuantity > 0 ? subtotal / totalQuantity : 0)
  const statusSteps = [
    { key: 'draft', label: 'Borrador', icon: 'mdi-file-outline' },
    { key: 'approved', label: 'Aprobada', icon: 'mdi-check-decagram-outline' },
    { key: 'partial', label: 'En recepción', icon: 'mdi-truck-delivery-outline' },
    { key: 'received', label: 'Recibida', icon: 'mdi-package-variant-closed-check' },
  ]
  const normalizedStatus = $derived(order.status === 'pending' ? 'draft' : order.status)
  const statusIndex = $derived(Math.max(0, statusSteps.findIndex(step => step.key === normalizedStatus)))

  // ── Modal recepción ─────────────────────────────────────────────────────────
  let showReceiveModal = $state(false)

  const receiveForm = useForm({
    warehouse_id: warehouses[0]?.id ?? '',
    lines: order.items.map(l => ({
      item_id:           l.item_id,
      item_name:         l.item?.name ?? l.item_id,
      received_quantity: l.invoice_quantity,
      average_cost:      l.average_cost,
    }))
  })

  function submitReceive() {
    $receiveForm.post(`/purchases/${order.id}/receive`, {
      onSuccess: () => { showReceiveModal = false }
    })
  }

  // ── Modal anulación ─────────────────────────────────────────────────────────
  let showAnnulModal = $state(false)
  let annulReason    = $state('')

  function submitAnnul() {
    router.post(`/purchases/${order.id}/annul`, { reason: annulReason }, { preserveScroll: true })
  }

  // ── Documento Soporte ────────────────────────────────────────────────────────
  // Disponible cuando la OC está recibida y no tiene DS previo
  const canGenerateDS = $derived(
    order.status === 'received' && !order.document_id && !order.annulled
  )

  let showDsModal  = $state(false)
  let dsNote       = $state('')
  let dsSubmitting = $state(false)

  function submitSupportDocument() {
    dsSubmitting = true
    router.post(`/purchases/${order.id}/support-document`, { note: dsNote.trim() || null }, {
      preserveScroll: true,
      onFinish: () => { dsSubmitting = false },
    })
  }

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
  }
</script>

<AppLayout title="Orden de Compra {order.internal_code}">
  <div class="max-w-7xl mx-auto space-y-5">

    <!-- Encabezado -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="flex items-center gap-3">
        <a href="/purchases" class="text-slate-400 hover:text-slate-600" title="Volver a compras">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold text-slate-800">{order.internal_code}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {cfg.cls}">
              {cfg.label}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {order.third_party?.name ?? 'Sin proveedor'} · Emitida {fmtDate(order.issue_date)}
          </p>
          {#if order.reference}
            <p class="text-xs text-slate-400 mt-1">Referencia proveedor: {order.reference}</p>
          {/if}
        </div>
      </div>

      <!-- Acciones -->
      <div class="flex flex-wrap gap-2">
        {#if order.status === 'draft'}
          <form method="POST" action="/purchases/{order.id}/approve">
            <input type="hidden" name="_method" value="POST"/>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
              <i class="mdi mdi-check-circle-outline"></i> Aprobar
            </button>
          </form>
        {/if}
        {#if order.status === 'approved' || order.status === 'partial'}
          <button onclick={() => showReceiveModal = true}
                  class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            <i class="mdi mdi-package-variant-closed-check"></i> Recibir Mercancía
          </button>
        {/if}
        {#if canGenerateDS}
          <button onclick={() => showDsModal = true}
                  class="inline-flex items-center gap-1.5 border border-teal-300 text-teal-700 hover:bg-teal-50 text-sm font-medium px-4 py-2 rounded-lg">
            <i class="mdi mdi-file-document-outline"></i> Documento Soporte
          </button>
        {/if}
        {#if !['received', 'cancelled'].includes(order.status)}
          <button onclick={() => showAnnulModal = true}
                  class="inline-flex items-center gap-1.5 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg">
            <i class="mdi mdi-cancel"></i> Anular
          </button>
        {/if}
      </div>
    </div>

    <!-- DS ya emitido -->
    {#if order.document}
      <div class="mb-4 bg-teal-50 border border-teal-200 rounded-xl px-5 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 text-teal-700 text-sm font-semibold">
          <i class="mdi mdi-file-document-check-outline text-base"></i>
          Documento Soporte emitido: {order.document.internal_code ?? order.document.prefix + order.document.number}
        </div>
        <a href="/invoices/{order.document.id}"
           class="text-xs text-teal-700 underline hover:text-teal-900 font-medium">
          Ver documento →
        </a>
      </div>
    {/if}

    <!-- Resumen operativo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-slate-400 uppercase">Total orden</p>
        <p class="text-xl font-bold text-slate-800 mt-1">{fmt(order.amount)}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-slate-400 uppercase">Lineas</p>
        <p class="text-xl font-bold text-slate-800 mt-1">{itemCount}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-slate-400 uppercase">Cantidad</p>
        <p class="text-xl font-bold text-slate-800 mt-1">{totalQuantity.toLocaleString('es-CO')}</p>
      </div>
      <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
        <p class="text-xs font-semibold text-slate-400 uppercase">Costo promedio</p>
        <p class="text-xl font-bold text-slate-800 mt-1">{fmt(avgUnitCost)}</p>
      </div>
    </div>

    <!-- Estado -->
    <div class="bg-white border border-slate-200 rounded-xl px-5 py-4">
      <div class="flex items-center justify-between gap-3 mb-4">
        <div>
          <h2 class="font-semibold text-slate-800">Seguimiento de la orden</h2>
          <p class="text-xs text-slate-500 mt-0.5">Estado actual y avance operativo de compra.</p>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {cfg.cls}">
          {cfg.label}
        </span>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        {#each statusSteps as step, index}
          {@const done = order.status === 'cancelled' ? false : index <= statusIndex}
          <div class="flex items-center gap-3 rounded-lg border px-3 py-2 {done ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50'}">
            <span class="w-8 h-8 rounded-full flex items-center justify-center {done ? 'bg-blue-600 text-white' : 'bg-white text-slate-400 border border-slate-200'}">
              <i class="mdi {step.icon} text-base"></i>
            </span>
            <span class="text-sm font-medium {done ? 'text-blue-800' : 'text-slate-500'}">{step.label}</span>
          </div>
        {/each}
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-5">

      <!-- Artículos -->
      <div class="xl:col-span-3 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="font-semibold text-slate-800">Lineas de compra</h2>
            <p class="text-xs text-slate-500 mt-0.5">Productos, cantidades y costos negociados con el proveedor.</p>
          </div>
          <span class="text-xs font-semibold text-slate-500 bg-slate-100 rounded-full px-2.5 py-1">
            {itemCount} lineas
          </span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left font-semibold text-slate-600">Producto</th>
                <th class="px-4 py-3 text-left font-semibold text-slate-600">Referencia</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-600">Cantidad</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-600">Costo unit.</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-600">Impuestos</th>
                <th class="px-4 py-3 text-right font-semibold text-slate-600">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each lines as line, index}
                {@const taxAmount = Array.isArray(line.tax) ? line.tax.reduce((sum, tax) => sum + Number(tax.amount ?? 0), 0) : 0}
                <tr class="hover:bg-slate-50">
                  <td class="px-4 py-3 min-w-72">
                    <div class="flex items-start gap-3">
                      <span class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        {index + 1}
                      </span>
                      <div>
                        <p class="font-medium text-slate-800 leading-tight">{line.item?.name ?? line.item_id}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{line.item?.internal_code ?? 'Sin codigo interno'}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-500">{line.item?.reference ?? '—'}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{Number(line.invoice_quantity).toLocaleString('es-CO')}</td>
                  <td class="px-4 py-3 text-right tabular-nums">{fmt(line.average_cost)}</td>
                  <td class="px-4 py-3 text-right tabular-nums text-slate-500">{taxAmount > 0 ? fmt(taxAmount) : '—'}</td>
                  <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-800">{fmt(line.line_extension_amount)}</td>
                </tr>
              {:else}
                <tr>
                  <td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin artículos</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>

      <!-- Panel lateral -->
      <div class="xl:col-span-1 space-y-5">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Proveedor</h2>
          </div>
          <div class="px-5 py-4 space-y-3 text-sm">
            <div>
              <p class="text-xs font-semibold uppercase text-slate-400">Nombre</p>
              <p class="font-semibold text-slate-800 mt-0.5">{order.third_party?.name ?? 'Sin proveedor'}</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Identificacion</p>
                <p class="text-slate-700 mt-0.5">{order.third_party?.identification_number ?? '—'}</p>
              </div>
              <div>
                <p class="text-xs font-semibold uppercase text-slate-400">Dias pago</p>
                <p class="text-slate-700 mt-0.5">{order.third_party?.payment_days ?? 0}</p>
              </div>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-400">Contacto</p>
              <p class="text-slate-700 mt-0.5">{order.third_party?.email ?? '—'}</p>
              <p class="text-slate-500">{order.third_party?.phone ?? ''}</p>
            </div>
            <div>
              <p class="text-xs font-semibold uppercase text-slate-400">Direccion</p>
              <p class="text-slate-700 mt-0.5">{order.third_party?.address ?? '—'}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-semibold text-slate-800">Totales</h2>
          </div>
          <div class="px-5 py-4 space-y-2 text-sm">
            <div class="flex justify-between text-slate-600">
              <span>Subtotal lineas</span>
              <span class="font-medium tabular-nums">{fmt(subtotal)}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Ajustes / fletes</span>
              <span class="font-medium tabular-nums">{fmt(Number(order.amount ?? 0) - subtotal)}</span>
            </div>
            <div class="flex justify-between border-t border-slate-100 pt-3 mt-3">
              <span class="font-semibold text-slate-800">Total orden</span>
              <span class="font-bold text-blue-700 tabular-nums">{fmt(order.amount)}</span>
            </div>
          </div>
        </div>

        {#if order.notes}
          <div class="bg-amber-50 rounded-xl border border-amber-200 px-5 py-4">
            <h2 class="font-semibold text-amber-900 flex items-center gap-2">
              <i class="mdi mdi-note-text-outline"></i>
              Notas internas
            </h2>
            <p class="text-sm text-amber-800 mt-2 leading-relaxed">{order.notes}</p>
          </div>
        {/if}
      </div>
    </div>

    <!-- Historial -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-slate-800">Historial y trazabilidad</h2>
          <p class="text-xs text-slate-500 mt-0.5">Cambios de estado, aprobaciones, recepciones y observaciones.</p>
        </div>
        <span class="text-xs font-semibold text-slate-500 bg-slate-100 rounded-full px-2.5 py-1">
          {(order.histories ?? []).length} eventos
        </span>
      </div>
      <div class="divide-y divide-slate-100">
        {#each order.histories ?? [] as h}
          <div class="px-5 py-4 flex gap-3">
            <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
              <i class="mdi mdi-timeline-clock-outline text-slate-500"></i>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm font-semibold text-slate-800">{h.history}</p>
                <p class="text-xs text-slate-400">
                  {new Date(h.history_issue_date).toLocaleString('es-CO')}
                </p>
              </div>
              {#if h.notes}<p class="text-sm text-slate-500 mt-1">{h.notes}</p>{/if}
              <p class="text-xs text-slate-400 mt-1">Usuario: {h.user?.name ?? 'Sistema'}</p>
            </div>
          </div>
        {:else}
          <div class="px-4 py-6 text-center text-slate-400 text-sm">Sin historial</div>
        {/each}
      </div>
    </div>
  </div>

  <!-- Modal: Recibir mercancía -->
  {#if showReceiveModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Recibir Mercancía</h2>
          <button onclick={() => showReceiveModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <!-- Bodega destino -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Bodega destino <span class="text-red-500">*</span></label>
            <select bind:value={$receiveForm.warehouse_id}
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              {#each warehouses as w}
                <option value={w.id}>{w.name}</option>
              {/each}
            </select>
          </div>

          <!-- Cantidades recibidas -->
          <div>
            <p class="text-sm font-medium text-slate-700 mb-2">Cantidades recibidas</p>
            <div class="space-y-2 max-h-48 overflow-y-auto">
              {#each $receiveForm.lines as line, i}
                <div class="flex items-center gap-3 p-2 border border-slate-200 rounded-lg">
                  <div class="flex-1 text-sm text-slate-700 truncate">{line.item_name}</div>
                  <input type="number" min="0" step="0.001"
                         bind:value={line.received_quantity}
                         class="w-24 border border-slate-300 rounded px-2 py-1 text-right text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"/>
                </div>
              {/each}
            </div>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showReceiveModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitReceive}
                  disabled={$receiveForm.processing}
                  class="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$receiveForm.processing ? 'Procesando…' : 'Confirmar Recepción'}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: Documento Soporte -->
  {#if showDsModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      onclick={(e) => { if (e.target === e.currentTarget) showDsModal = false }}>
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-teal-100 flex items-center justify-center flex-shrink-0">
            <i class="mdi mdi-file-document-outline text-teal-600 text-lg"></i>
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-slate-800 text-base">Generar Documento Soporte</h2>
            <p class="text-xs text-slate-500">OC: {order.internal_code} · {order.third_party?.name ?? 'Sin proveedor'}</p>
          </div>
          <button onclick={() => showDsModal = false} class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div class="bg-teal-50 border border-teal-200 rounded-xl px-4 py-3 text-sm text-teal-700">
            <p class="font-semibold mb-1 flex items-center gap-1.5">
              <i class="mdi mdi-information-outline"></i>
              ¿Cuándo emitir un Documento Soporte?
            </p>
            <p class="text-xs leading-relaxed">
              Se emite cuando el proveedor es una persona natural NO obligada a facturar (régimen simplificado).
              En ese caso, el comprador debe expedir este documento para soportar el gasto ante la DIAN.
            </p>
          </div>

          <!-- Resumen de la OC -->
          <div class="text-sm space-y-1.5">
            <div class="flex justify-between text-slate-600">
              <span>Proveedor</span>
              <span class="font-medium text-slate-800">{order.third_party?.name ?? '—'}</span>
            </div>
            <div class="flex justify-between text-slate-600">
              <span>Ítems</span>
              <span class="font-medium text-slate-800">{order.items.length}</span>
            </div>
            <div class="flex justify-between text-slate-600 border-t border-slate-100 pt-1.5">
              <span class="font-semibold">Total</span>
              <span class="font-bold text-teal-700">{fmt(order.amount)}</span>
            </div>
          </div>

          <!-- Observación opcional -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Observación (opcional)
            </label>
            <textarea
              bind:value={dsNote}
              rows="2"
              placeholder="Ej: Compra a proveedor régimen simplificado..."
              class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-300"
            ></textarea>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showDsModal = false} disabled={dsSubmitting}
            class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
            Cancelar
          </button>
          <button onclick={submitSupportDocument} disabled={dsSubmitting}
            class="flex items-center gap-2 px-5 py-2 bg-teal-600 text-white text-sm font-semibold rounded-xl hover:bg-teal-700 transition cursor-pointer disabled:opacity-60">
            {#if dsSubmitting}
              <i class="mdi mdi-loading mdi-spin text-base"></i>
              Generando...
            {:else}
              <i class="mdi mdi-file-check-outline text-base"></i>
              Generar DS
            {/if}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: Anular -->
  {#if showAnnulModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Anular Orden</h2>
          <button onclick={() => showAnnulModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4">
          <p class="text-sm text-slate-600 mb-3">¿Estás seguro de anular la orden <strong>{order.internal_code}</strong>? Esta acción no se puede deshacer.</p>
          <label class="block text-sm font-medium text-slate-700 mb-1">Motivo (opcional)</label>
          <textarea bind:value={annulReason} rows="3"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"></textarea>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showAnnulModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitAnnul}
                  class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Confirmar Anulación
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
