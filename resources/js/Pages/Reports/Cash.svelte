<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'
  import { router } from '@inertiajs/svelte'

  let { shifts, movements, boxTotals, shiftSummary, cashBoxes, filters } = $props()

  let dateFrom   = $state(filters.date_from)
  let dateTo     = $state(filters.date_to)
  let cashBoxId  = $state(filters.cash_box_id ?? '')
  let activeTab  = $state('shifts') // shifts | movements

  function apply() {
    router.get('/reports/cash', {
      date_from:    dateFrom,
      date_to:      dateTo,
      cash_box_id:  cashBoxId || undefined,
    }, { preserveScroll: true })
  }

  const fmt    = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const fmtDec = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  const fmtDate = (d) => {
    if (!d) return '—'
    return new Date(d).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' })
  }
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Reporte de Caja</h1>
        <p class="text-sm text-slate-500 mt-0.5">Historial de turnos y movimientos de caja</p>
      </div>
      <ExportButtons baseUrl="/reports/cash/export" params={{ date_from: dateFrom, date_to: dateTo, cash_box_id: cashBoxId }} />
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
        <input type="date" bind:value={dateFrom} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
        <input type="date" bind:value={dateTo} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Caja</label>
        <select bind:value={cashBoxId} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todas las cajas</option>
          {#each cashBoxes as cb}
            <option value={cb.id}>{cb.name}</option>
          {/each}
        </select>
      </div>
      <button onclick={apply} class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer">
        <i class="mdi mdi-magnify mr-1"></i> Aplicar
      </button>
    </div>

    <!-- Tarjetas de totales de turnos -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Turnos cerrados</p>
        <p class="text-2xl font-bold text-slate-800">{shiftSummary.total_shifts}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Total ventas</p>
        <p class="text-2xl font-bold text-slate-800">${fmt(shiftSummary.total_sales)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Efectivo</p>
        <p class="text-2xl font-bold text-green-600">${fmt(shiftSummary.total_cash)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Tarjeta</p>
        <p class="text-2xl font-bold text-blue-600">${fmt(shiftSummary.total_card)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Transferencia</p>
        <p class="text-2xl font-bold text-purple-600">${fmt(shiftSummary.total_transfer)}</p>
      </div>
    </div>

    <!-- Totales por caja -->
    {#if boxTotals.length > 0}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {#each boxTotals as box}
          <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">{box.name}</p>
            <div class="flex justify-between text-sm mb-1">
              <span class="text-green-600">Entradas</span>
              <span class="font-semibold">${fmt(box.total_debit)}</span>
            </div>
            <div class="flex justify-between text-sm mb-2">
              <span class="text-red-500">Salidas</span>
              <span class="font-semibold">${fmt(box.total_credit)}</span>
            </div>
            <div class="flex justify-between text-sm border-t pt-2 font-bold">
              <span class="text-slate-700">Balance</span>
              <span class="{Number(box.balance) >= 0 ? 'text-green-700' : 'text-red-600'}">${fmt(box.balance)}</span>
            </div>
          </div>
        {/each}
      </div>
    {/if}

    <!-- Tabs: Turnos / Movimientos -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="flex border-b border-slate-200">
        <button
          onclick={() => activeTab = 'shifts'}
          class="px-5 py-3 text-sm font-medium cursor-pointer border-b-2 transition
            {activeTab === 'shifts' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}"
        >
          Turnos ({shifts.length})
        </button>
        <button
          onclick={() => activeTab = 'movements'}
          class="px-5 py-3 text-sm font-medium cursor-pointer border-b-2 transition
            {activeTab === 'movements' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'}"
        >
          Movimientos ({movements.length})
        </button>
      </div>

      <!-- Tab: Turnos -->
      {#if activeTab === 'shifts'}
        {#if shifts.length === 0}
          <div class="py-16 text-center text-slate-400 text-sm">
            <i class="mdi mdi-clock-outline text-4xl block mb-2"></i>
            Sin turnos cerrados en el período
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                  <th class="px-4 py-2.5">Cajero</th>
                  <th class="px-4 py-2.5">Terminal</th>
                  <th class="px-4 py-2.5">Apertura</th>
                  <th class="px-4 py-2.5">Cierre</th>
                  <th class="px-4 py-2.5 text-right">Base</th>
                  <th class="px-4 py-2.5 text-right">Ventas</th>
                  <th class="px-4 py-2.5 text-right">Efectivo</th>
                  <th class="px-4 py-2.5 text-right">Tarjeta</th>
                  <th class="px-4 py-2.5 text-right">Balance final</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each shifts as s}
                  <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 font-medium text-slate-700">{s.user?.name ?? '—'}</td>
                    <td class="px-4 py-2.5 text-slate-500">{s.terminal?.name ?? '—'}</td>
                    <td class="px-4 py-2.5 text-slate-500 text-xs">{fmtDate(s.shift_opened_at)}</td>
                    <td class="px-4 py-2.5 text-slate-500 text-xs">{fmtDate(s.shift_closed_at)}</td>
                    <td class="px-4 py-2.5 text-right text-slate-600">${fmt(s.initial_balance)}</td>
                    <td class="px-4 py-2.5 text-right font-medium text-slate-800">${fmt(s.total_sales)}</td>
                    <td class="px-4 py-2.5 text-right text-green-600">${fmt(s.total_cash)}</td>
                    <td class="px-4 py-2.5 text-right text-blue-600">${fmt(Number(s.total_card) + Number(s.total_transfer))}</td>
                    <td class="px-4 py-2.5 text-right font-bold text-slate-800">${fmt(s.final_balance)}</td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-slate-50 border-t border-slate-200 text-sm font-semibold text-slate-700">
                <tr>
                  <td class="px-4 py-2.5" colspan="5">TOTAL</td>
                  <td class="px-4 py-2.5 text-right text-blue-700">${fmt(shiftSummary.total_sales)}</td>
                  <td class="px-4 py-2.5 text-right text-green-700">${fmt(shiftSummary.total_cash)}</td>
                  <td class="px-4 py-2.5 text-right text-blue-700">${fmt(Number(shiftSummary.total_card) + Number(shiftSummary.total_transfer))}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        {/if}
      {/if}

      <!-- Tab: Movimientos -->
      {#if activeTab === 'movements'}
        {#if movements.length === 0}
          <div class="py-16 text-center text-slate-400 text-sm">
            <i class="mdi mdi-swap-horizontal text-4xl block mb-2"></i>
            Sin movimientos en el período
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                  <th class="px-4 py-2.5">Código</th>
                  <th class="px-4 py-2.5">Caja</th>
                  <th class="px-4 py-2.5">Fecha</th>
                  <th class="px-4 py-2.5">Descripción</th>
                  <th class="px-4 py-2.5">Documento</th>
                  <th class="px-4 py-2.5 text-right">Entrada</th>
                  <th class="px-4 py-2.5 text-right">Salida</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each movements as m}
                  <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{m.internal_code}</td>
                    <td class="px-4 py-2.5 text-slate-600">{m.cash_box?.name ?? '—'}</td>
                    <td class="px-4 py-2.5 text-slate-500 text-xs">{m.issue_date}</td>
                    <td class="px-4 py-2.5 text-slate-700 max-w-[200px] truncate" title={m.description}>{m.description ?? '—'}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-400">{m.document?.internal_code ?? m.reference ?? '—'}</td>
                    <td class="px-4 py-2.5 text-right text-green-600 font-medium">
                      {Number(m.debit) > 0 ? '$' + fmt(m.debit) : '—'}
                    </td>
                    <td class="px-4 py-2.5 text-right text-red-500 font-medium">
                      {Number(m.credit) > 0 ? '$' + fmt(m.credit) : '—'}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}
      {/if}
    </div>

  </div>
</AppLayout>
