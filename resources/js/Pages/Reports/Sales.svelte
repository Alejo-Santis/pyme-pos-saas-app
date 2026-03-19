<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'
  import { router } from '@inertiajs/svelte'

  let { totals, rows, topProducts, terminals, filters } = $props()

  let dateFrom   = $state(filters.date_from)
  let dateTo     = $state(filters.date_to)
  let terminalId = $state(filters.terminal_id ?? '')
  let groupBy    = $state(filters.group_by ?? 'day')

  function apply() {
    router.get('/reports/sales', {
      date_from:   dateFrom,
      date_to:     dateTo,
      terminal_id: terminalId || undefined,
      group_by:    groupBy,
    }, { preserveScroll: true, replace: true })
  }

  const fmt    = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const fmtDec = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  const groupLabels = { day: 'Por día', product: 'Por producto', terminal: 'Por terminal', third: 'Por cliente' }

  // Calcular barras para gráfico simple
  let maxAmount = $derived(Math.max(...(rows.map(r => Number(r.total_amount)) ?? [1]), 1))
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Reporte de Ventas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Análisis de documentos de venta por período</p>
      </div>
      <ExportButtons baseUrl="/reports/sales/export" params={{ date_from: dateFrom, date_to: dateTo, terminal_id: terminalId, group_by: groupBy }} />
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
        <label class="block text-xs font-medium text-slate-600 mb-1">Terminal</label>
        <select bind:value={terminalId} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todas</option>
          {#each terminals as t}
            <option value={t.id}>{t.name}</option>
          {/each}
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Agrupar por</label>
        <select bind:value={groupBy} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          {#each Object.entries(groupLabels) as [k, v]}
            <option value={k}>{v}</option>
          {/each}
        </select>
      </div>
      <button onclick={apply} class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer">
        <i class="mdi mdi-magnify mr-1"></i> Aplicar
      </button>
    </div>

    <!-- Tarjetas de totales -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Documentos</p>
        <p class="text-2xl font-bold text-slate-800">{fmt(totals?.total_docs)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Subtotal (sin IVA)</p>
        <p class="text-2xl font-bold text-slate-800">${fmt(totals?.total_subtotal)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">IVA</p>
        <p class="text-2xl font-bold text-amber-600">${fmt(totals?.total_tax)}</p>
      </div>
      <div class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl p-4 text-white">
        <p class="text-xs text-blue-200 mb-1">Total vendido</p>
        <p class="text-2xl font-bold">${fmt(totals?.total_amount)}</p>
        <p class="text-xs text-blue-200 mt-1">Pendiente: ${fmt(totals?.total_pending)}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Tabla principal (agrupada) -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">{groupLabels[groupBy]}</h2>
          <span class="text-xs text-slate-400">{rows.length} registros</span>
        </div>

        {#if rows.length === 0}
          <div class="py-16 text-center text-slate-400 text-sm">
            <i class="mdi mdi-chart-bar text-4xl block mb-2"></i>
            Sin datos para el período
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                  <th class="px-4 py-2.5">
                    {groupBy === 'product' ? 'Producto' : groupBy === 'day' ? 'Fecha' : 'Descripción'}
                  </th>
                  {#if groupBy === 'product'}
                    <th class="px-4 py-2.5 text-right">Cantidad</th>
                    <th class="px-4 py-2.5 text-right">Documentos</th>
                  {:else}
                    <th class="px-4 py-2.5 text-right">Documentos</th>
                  {/if}
                  <th class="px-4 py-2.5 text-right">Total</th>
                  <th class="px-4 py-2.5 w-32">Barra</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each rows as row}
                  <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 font-medium text-slate-700">
                      {#if groupBy === 'product'}
                        <span class="text-xs text-slate-400 mr-1">{row.internal_code}</span>{row.product_name}
                      {:else}
                        {row.period}
                      {/if}
                    </td>
                    {#if groupBy === 'product'}
                      <td class="px-4 py-2.5 text-right text-slate-600">{fmtDec(row.total_qty)}</td>
                      <td class="px-4 py-2.5 text-right text-slate-500">{row.total_docs}</td>
                    {:else}
                      <td class="px-4 py-2.5 text-right text-slate-500">{row.total_docs}</td>
                    {/if}
                    <td class="px-4 py-2.5 text-right font-semibold text-slate-800">${fmt(row.total_amount)}</td>
                    <td class="px-4 py-2.5">
                      <div class="h-2 rounded-full bg-slate-100">
                        <div
                          class="h-2 rounded-full bg-blue-500"
                          style="width: {Math.round((Number(row.total_amount) / maxAmount) * 100)}%"
                        ></div>
                      </div>
                    </td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-slate-50 border-t border-slate-200">
                <tr class="text-sm font-semibold text-slate-700">
                  <td class="px-4 py-2.5">TOTAL</td>
                  {#if groupBy === 'product'}
                    <td class="px-4 py-2.5 text-right">{fmtDec(rows.reduce((s, r) => s + Number(r.total_qty), 0))}</td>
                  {/if}
                  <td class="px-4 py-2.5 text-right">{rows.reduce((s, r) => s + Number(r.total_docs), 0)}</td>
                  <td class="px-4 py-2.5 text-right text-blue-700">${fmt(rows.reduce((s, r) => s + Number(r.total_amount), 0))}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        {/if}
      </div>

      <!-- Top 10 productos -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700">Top 10 Productos</h2>
        </div>
        {#if topProducts.length === 0}
          <p class="text-center text-sm text-slate-400 py-10">Sin datos</p>
        {:else}
          {@const topMax = Math.max(...topProducts.map(p => Number(p.total_amount)), 1)}
          <ul class="divide-y divide-slate-100">
            {#each topProducts as p, i}
              <li class="px-4 py-3">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs font-medium text-slate-700 truncate max-w-[160px]">
                    <span class="text-slate-400 mr-1">#{i + 1}</span>{p.product_name}
                  </span>
                  <span class="text-xs font-semibold text-slate-800">${fmt(p.total_amount)}</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100">
                  <div
                    class="h-1.5 rounded-full bg-green-500"
                    style="width: {Math.round((Number(p.total_amount) / topMax) * 100)}%"
                  ></div>
                </div>
                <p class="text-xs text-slate-400 mt-0.5">{fmtDec(p.total_qty)} uds · {p.total_docs} docs</p>
              </li>
            {/each}
          </ul>
        {/if}
      </div>

    </div>
  </div>
</AppLayout>
