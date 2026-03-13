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
    router.post(`/purchases/${order.id}/annul`, { reason: annulReason })
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
  <div class="max-w-5xl mx-auto">

    <!-- Encabezado -->
    <div class="flex items-start justify-between mb-6">
      <div class="flex items-center gap-3">
        <a href="/purchases" class="text-slate-400 hover:text-slate-600">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-slate-800">{order.internal_code}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {cfg.cls}">
              {cfg.label}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {order.third_party?.business_name ?? 'Sin proveedor'} · {fmtDate(order.issue_date)}
          </p>
        </div>
      </div>

      <!-- Acciones -->
      <div class="flex gap-2">
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
        {#if !['received', 'cancelled'].includes(order.status)}
          <button onclick={() => showAnnulModal = true}
                  class="inline-flex items-center gap-1.5 border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium px-4 py-2 rounded-lg">
            <i class="mdi mdi-cancel"></i> Anular
          </button>
        {/if}
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Artículos -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
          <h2 class="font-semibold text-slate-700">Artículos</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-slate-600">Artículo</th>
              <th class="px-4 py-3 text-right font-semibold text-slate-600">Cantidad</th>
              <th class="px-4 py-3 text-right font-semibold text-slate-600">Costo unit.</th>
              <th class="px-4 py-3 text-right font-semibold text-slate-600">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each order.items as line}
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{line.item?.name ?? line.item_id}</p>
                </td>
                <td class="px-4 py-3 text-right">{line.invoice_quantity}</td>
                <td class="px-4 py-3 text-right">{fmt(line.average_cost)}</td>
                <td class="px-4 py-3 text-right font-medium">{fmt(line.line_extension_amount)}</td>
              </tr>
            {:else}
              <tr>
                <td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin artículos</td>
              </tr>
            {/each}
          </tbody>
          <tfoot class="bg-slate-50 border-t border-slate-200">
            <tr>
              <td colspan="3" class="px-4 py-3 text-right font-bold text-slate-700">Total</td>
              <td class="px-4 py-3 text-right font-bold text-blue-700">{fmt(order.amount)}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Historial -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-semibold text-slate-700">Historial</h2>
          </div>
          <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
            {#each order.histories as h}
              <div class="px-4 py-3">
                <p class="text-sm font-medium text-slate-800">{h.history}</p>
                {#if h.notes}<p class="text-xs text-slate-500 mt-0.5">{h.notes}</p>{/if}
                <p class="text-xs text-slate-400 mt-1">
                  {new Date(h.history_issue_date).toLocaleString('es-CO')}
                </p>
              </div>
            {:else}
              <div class="px-4 py-6 text-center text-slate-400 text-sm">Sin historial</div>
            {/each}
          </div>
        </div>
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
