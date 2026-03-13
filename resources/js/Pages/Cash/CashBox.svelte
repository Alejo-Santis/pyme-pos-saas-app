<script>
  import { useForm, router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { cashBox, currentBalance, movements, filters } = $props()

  // ── Filtros de fecha ─────────────────────────────────────────────────────────
  let startDate = $state(filters.start_date ?? '')
  let endDate   = $state(filters.end_date ?? '')

  function applyFilters() {
    router.get(`/cash/boxes/${cashBox.id}`, { start_date: startDate, end_date: endDate },
      { preserveState: true, replace: true })
  }

  // ── Modal movimiento ─────────────────────────────────────────────────────────
  let showMoveModal = $state(false)

  const form = useForm({
    type:        'debit',
    amount:      '',
    description: '',
    reference:   '',
    issue_date:  new Date().toISOString().split('T')[0],
  })

  function submitMovement() {
    $form.post(`/cash/boxes/${cashBox.id}/movements`, {
      onSuccess: () => { showMoveModal = false; $form.reset() }
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

<AppLayout title="Caja — {cashBox.name}">
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <a href="/cash" class="text-slate-400 hover:text-slate-600">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl font-bold text-slate-800">{cashBox.name}</h1>
          {#if cashBox.is_main}
            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Principal</span>
          {/if}
        </div>
        <p class="text-sm text-slate-500 mt-0.5">{cashBox.internal_code}</p>
      </div>
    </div>
    <button onclick={() => showMoveModal = true}
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
      <i class="mdi mdi-plus"></i> Registrar Movimiento
    </button>
  </div>

  <!-- Saldo destacado -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="md:col-span-1 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-5 text-white">
      <p class="text-blue-200 text-sm">Saldo {startDate || endDate ? 'del período' : 'actual'}</p>
      <p class="text-3xl font-bold mt-1 {currentBalance < 0 ? 'text-red-300' : ''}">{fmt(currentBalance)}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <p class="text-slate-500 text-sm">Total ingresos</p>
      <p class="text-2xl font-bold text-green-600 mt-1">
        {fmt(movements.data.reduce((s, m) => s + parseFloat(m.debit ?? 0), 0))}
      </p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <p class="text-slate-500 text-sm">Total egresos</p>
      <p class="text-2xl font-bold text-red-600 mt-1">
        {fmt(movements.data.reduce((s, m) => s + parseFloat(m.credit ?? 0), 0))}
      </p>
    </div>
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
      <input bind:value={startDate} type="date"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
      <input bind:value={endDate} type="date"
             class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
    </div>
    <button onclick={applyFilters}
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
      Filtrar
    </button>
    {#if startDate || endDate}
      <button onclick={() => { startDate = ''; endDate = ''; applyFilters() }}
              class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
        Limpiar
      </button>
    {/if}
  </div>

  <!-- Tabla de movimientos -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Código</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Descripción</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Fecha</th>
          <th class="px-4 py-3 text-right font-semibold text-green-600">Ingreso</th>
          <th class="px-4 py-3 text-right font-semibold text-red-600">Egreso</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        {#each movements.data as m}
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-mono text-xs text-slate-500">{m.internal_code}</td>
            <td class="px-4 py-3">
              <p class="text-slate-800">{m.description ?? '—'}</p>
              {#if m.reference}<p class="text-xs text-slate-400">Ref: {m.reference}</p>{/if}
              {#if m.third_party}<p class="text-xs text-slate-500">{m.third_party.business_name}</p>{/if}
            </td>
            <td class="px-4 py-3 text-slate-600">{fmtDate(m.issue_date)}</td>
            <td class="px-4 py-3 text-right">
              {#if parseFloat(m.debit) > 0}
                <span class="text-green-600 font-medium">{fmt(m.debit)}</span>
              {:else}
                <span class="text-slate-300">—</span>
              {/if}
            </td>
            <td class="px-4 py-3 text-right">
              {#if parseFloat(m.credit) > 0}
                <span class="text-red-600 font-medium">{fmt(m.credit)}</span>
              {:else}
                <span class="text-slate-300">—</span>
              {/if}
            </td>
          </tr>
        {:else}
          <tr>
            <td colspan="5" class="px-4 py-12 text-center text-slate-400">
              <i class="mdi mdi-swap-horizontal text-4xl block mb-2 opacity-50"></i>
              Sin movimientos en el período
            </td>
          </tr>
        {/each}
      </tbody>
    </table>

    {#if movements.last_page > 1}
      <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Mostrando {movements.from}–{movements.to} de {movements.total}</span>
        <div class="flex gap-1">
          {#each movements.links as link}
            {#if link.url}
              <button onclick={() => router.get(link.url)}
                      class="px-3 py-1 rounded {link.active ? 'bg-blue-600 text-white' : 'hover:bg-slate-100'}">
                {@html link.label}
              </button>
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </div>

  <!-- Modal: registrar movimiento -->
  {#if showMoveModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Registrar Movimiento</h2>
          <button onclick={() => showMoveModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div class="flex gap-2">
            <button onclick={() => $form.type = 'debit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$form.type === 'debit' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 text-slate-600'}">
              <i class="mdi mdi-arrow-down-circle-outline mr-1"></i> Ingreso
            </button>
            <button onclick={() => $form.type = 'credit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$form.type === 'credit' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 text-slate-600'}">
              <i class="mdi mdi-arrow-up-circle-outline mr-1"></i> Egreso
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Valor <span class="text-red-500">*</span></label>
            <input bind:value={$form.amount} type="number" min="0" placeholder="0"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción <span class="text-red-500">*</span></label>
            <input bind:value={$form.description} type="text"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Referencia</label>
            <input bind:value={$form.reference} type="text"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha <span class="text-red-500">*</span></label>
            <input bind:value={$form.issue_date} type="date"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showMoveModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitMovement}
                  disabled={$form.processing}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$form.processing ? 'Guardando…' : 'Registrar'}
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
