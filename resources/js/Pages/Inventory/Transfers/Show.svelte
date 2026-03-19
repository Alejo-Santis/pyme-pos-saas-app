<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let {
    transfer    = null,
    stockOrigin = {},
  } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  const statusConfig = {
    draft:      { label: 'Borrador',     cls: 'bg-slate-100 text-slate-600',   icon: 'mdi-file-outline' },
    in_transit: { label: 'En tránsito',  cls: 'bg-amber-100 text-amber-700',   icon: 'mdi-truck-delivery-outline' },
    received:   { label: 'Recibido',     cls: 'bg-green-100 text-green-700',   icon: 'mdi-check-circle-outline' },
    cancelled:  { label: 'Cancelado',    cls: 'bg-red-100 text-red-700',       icon: 'mdi-close-circle-outline' },
  }

  const sc = $derived(statusConfig[transfer?.status] ?? statusConfig.draft)

  const items = $derived(transfer?.items ?? [])
  const histories = $derived(transfer?.histories ?? [])

  // Acciones
  let processing = $state(false)
  let confirmAction = $state({ open: false, endpoint: '', message: '' })

  function action(endpoint, confirmMsg) {
    confirmAction = { open: true, endpoint, message: confirmMsg }
  }

  function executeAction() {
    processing = true
    router.post(`/inventory/transfers/${transfer.id}/${confirmAction.endpoint}`, {}, {
      onFinish: () => { processing = false },
    })
  }

  let cancelNotes = $state('')
  let showCancelModal = $state(false)

  function confirmCancel() {
    processing = true
    showCancelModal = false
    router.post(`/inventory/transfers/${transfer.id}/cancel`, { notes: cancelNotes }, {
      onFinish: () => { processing = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-4xl mx-auto space-y-5">

    <!-- Cabecera -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex items-center gap-3">
        <a use:inertia href="/inventory/transfers" class="text-slate-400 hover:text-slate-600 transition">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex items-center gap-2.5">
            <h1 class="text-xl font-bold text-slate-800">Traslado</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {sc.cls}">
              <i class="mdi {sc.icon} mr-1"></i>{sc.label}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {transfer?.transfer_date} ·
            {transfer?.warehouse_origin?.name} →
            {transfer?.warehouse_destination?.name}
          </p>
        </div>
      </div>

      <!-- Botones de acción según estado -->
      <div class="flex items-center gap-2">
        {#if transfer?.status === 'draft'}
          <button onclick={() => action('dispatch', '¿Despachar el traslado? Se descontará el stock de la bodega origen.')}
            disabled={processing}
            class="flex items-center gap-1.5 px-4 py-2 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition cursor-pointer disabled:opacity-60">
            <i class="mdi mdi-truck-delivery-outline text-base"></i>
            Despachar
          </button>
          <button onclick={() => { showCancelModal = true }}
            disabled={processing}
            class="flex items-center gap-1.5 px-3 py-2 border border-red-200 text-red-600 text-sm rounded-lg hover:bg-red-50 transition cursor-pointer disabled:opacity-60">
            <i class="mdi mdi-close-circle-outline text-base"></i>
            Cancelar
          </button>
        {/if}

        {#if transfer?.status === 'in_transit'}
          <button onclick={() => action('receive', '¿Recibir el traslado? Se agregará el stock en la bodega destino.')}
            disabled={processing}
            class="flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition cursor-pointer disabled:opacity-60">
            <i class="mdi mdi-check-circle-outline text-base"></i>
            Recibir mercancía
          </button>
          <button onclick={() => { showCancelModal = true }}
            disabled={processing}
            class="flex items-center gap-1.5 px-3 py-2 border border-red-200 text-red-600 text-sm rounded-lg hover:bg-red-50 transition cursor-pointer disabled:opacity-60">
            <i class="mdi mdi-close-circle-outline text-base"></i>
            Cancelar
          </button>
        {/if}
      </div>
    </div>

    <!-- Bodegas -->
    <div class="grid grid-cols-2 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Bodega origen</p>
        <p class="font-bold text-slate-800">{transfer?.warehouse_origin?.name ?? '—'}</p>
        {#if transfer?.warehouse_origin?.code}
          <p class="text-sm text-slate-500">{transfer.warehouse_origin.code}</p>
        {/if}
        <p class="text-xs text-amber-600 mt-2 flex items-center gap-1">
          <i class="mdi mdi-arrow-up-circle-outline"></i> Sale inventario de aquí
        </p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Bodega destino</p>
        <p class="font-bold text-slate-800">{transfer?.warehouse_destination?.name ?? '—'}</p>
        {#if transfer?.warehouse_destination?.code}
          <p class="text-sm text-slate-500">{transfer.warehouse_destination.code}</p>
        {/if}
        <p class="text-xs text-green-600 mt-2 flex items-center gap-1">
          <i class="mdi mdi-arrow-down-circle-outline"></i> Entra inventario aquí
        </p>
      </div>
    </div>

    <!-- Ítems -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-package-variant text-primary"></i>
          Ítems ({items.length})
        </h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-100">
          <tr>
            <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Ítem</th>
            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-24">Cantidad</th>
            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Costo c/u</th>
            <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Total</th>
            {#if transfer?.status === 'draft'}
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Stock origen</th>
            {/if}
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          {#each items as line}
            {@const stockAvail = stockOrigin[line.item_id]?.stock ?? 0}
            {@const insufficient = transfer?.status === 'draft' && Number(line.quantity) > Number(stockAvail)}
            <tr class="hover:bg-slate-50/50 {insufficient ? 'bg-red-50/40' : ''}">
              <td class="px-4 py-3">
                <p class="font-medium text-slate-700">{line.item?.name ?? '—'}</p>
                {#if line.item?.internal_code}
                  <p class="text-xs text-slate-400">{line.item.internal_code}</p>
                {/if}
              </td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-700">
                {Number(line.quantity).toLocaleString('es-CO')}
              </td>
              <td class="px-4 py-3 text-right tabular-nums text-slate-600">${fmt(line.cost)}</td>
              <td class="px-4 py-3 text-right tabular-nums font-semibold text-slate-800">${fmt(line.line_total)}</td>
              {#if transfer?.status === 'draft'}
                <td class="px-4 py-3 text-right tabular-nums text-sm {insufficient ? 'text-red-600 font-semibold' : 'text-slate-500'}">
                  {Number(stockAvail).toLocaleString('es-CO')}
                  {#if insufficient}
                    <i class="mdi mdi-alert text-xs ml-1"></i>
                  {/if}
                </td>
              {/if}
            </tr>
          {/each}
        </tbody>
        <tfoot class="bg-slate-50 border-t border-slate-200">
          <tr>
            <td colspan={transfer?.status === 'draft' ? 3 : 3} class="px-4 py-3 text-sm font-semibold text-slate-700">
              Total traslado
            </td>
            <td class="px-4 py-3 text-right font-bold text-slate-800 tabular-nums">${fmt(transfer?.total)}</td>
            {#if transfer?.status === 'draft'}<td></td>{/if}
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Notas -->
    {#if transfer?.notes}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Observaciones</p>
        <p class="text-sm text-slate-700">{transfer.notes}</p>
      </div>
    {/if}

    <!-- Historial -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-history text-primary"></i>
          Historial
        </h2>
      </div>
      <div class="divide-y divide-slate-100">
        {#each histories as h}
          <div class="px-5 py-3 flex items-start gap-3">
            <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
              <i class="mdi mdi-clock-outline text-primary text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-slate-700">{h.action}</p>
              {#if h.notes}
                <p class="text-xs text-slate-500 mt-0.5">{h.notes}</p>
              {/if}
              <p class="text-xs text-slate-400 mt-0.5">
                {h.user?.name ?? 'Sistema'} · {h.action_date}
              </p>
            </div>
          </div>
        {/each}
        {#if histories.length === 0}
          <p class="px-5 py-4 text-sm text-slate-400">Sin historial</p>
        {/if}
      </div>
    </div>

  </div>
</AppLayout>

<ConfirmModal
  bind:open={confirmAction.open}
  title="Confirmar acción"
  message={confirmAction.message}
  confirmLabel="Confirmar"
  danger={false}
  onConfirm={executeAction}
/>

<!-- Modal de cancelación -->
{#if showCancelModal}
  <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    onclick={(e) => { if (e.target === e.currentTarget) showCancelModal = false }}>
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 z-50">
      <h3 class="font-bold text-slate-800 text-base mb-1">Cancelar traslado</h3>
      <p class="text-sm text-slate-500 mb-4">
        {transfer?.status === 'in_transit'
          ? 'El stock descontado en bodega origen será revertido.'
          : 'El traslado pasará a estado cancelado.'}
      </p>
      <textarea bind:value={cancelNotes} rows="3"
        placeholder="Motivo de cancelación (opcional)..."
        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-300 mb-4"></textarea>
      <div class="flex justify-end gap-3">
        <button onclick={() => { showCancelModal = false }}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer">
          No cancelar
        </button>
        <button onclick={confirmCancel}
          class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-xl hover:bg-red-700 transition cursor-pointer">
          Sí, cancelar traslado
        </button>
      </div>
    </div>
  </div>
{/if}
