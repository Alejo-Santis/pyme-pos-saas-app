<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'
  import { router } from '@inertiajs/svelte'

  let { items = [], warehouses = [], movements = [], item = null, stockInfo = [], filters } = $props()

  let itemId      = $state(filters.itemId ?? '')
  let warehouseId = $state(filters.warehouseId ?? '')
  let dateFrom    = $state(filters.from ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10))
  let dateTo      = $state(filters.to ?? new Date().toISOString().slice(0,10))

  function apply() {
    if (!itemId) return
    router.get('/reports/kardex', {
      item_id:      itemId,
      warehouse_id: warehouseId || undefined,
      date_from:    dateFrom,
      date_to:      dateTo,
    }, { preserveScroll: true, replace: true })
  }

  const fmt    = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 4 })
  const fmtCOP = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })

  // Saldo corriente calculado sobre los movimientos
  let runningBalance = $derived(() => {
    let bal = 0
    return movements.map(m => {
      bal += m.input_quantity - m.output_quantity
      return { ...m, balance: bal }
    })
  })

  let totals = $derived({
    totalIn:  movements.reduce((s, m) => s + m.input_quantity,  0),
    totalOut: movements.reduce((s, m) => s + m.output_quantity, 0),
  })
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Kardex de Inventario</h1>
        <p class="text-sm text-slate-500 mt-0.5">Historial de entradas y salidas por producto y bodega</p>
      </div>
      {#if item && movements.length > 0}
        <ExportButtons baseUrl="/reports/kardex/export" params={{ item_id: itemId, warehouse_id: warehouseId || undefined, date_from: dateFrom, date_to: dateTo }} />
      {/if}
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 items-end rounded-xl border border-slate-200 bg-white p-4">
      <div class="w-64">
        <label class="block text-xs font-medium text-slate-600 mb-1">Producto <span class="text-rose-500">*</span></label>
        <select bind:value={itemId} class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="">Seleccione un producto</option>
          {#each items as i}
            <option value={i.id}>[{i.internal_code}] {i.name}</option>
          {/each}
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Bodega</label>
        <select bind:value={warehouseId} class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="">Todas</option>
          {#each warehouses as w}
            <option value={w.id}>{w.name}</option>
          {/each}
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
        <input type="date" bind:value={dateFrom} class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
        <input type="date" bind:value={dateTo} class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>
      <button onclick={apply} disabled={!itemId} class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed">
        <i class="mdi mdi-magnify mr-1"></i>Consultar
      </button>
    </div>

    {#if item}
      <!-- Info del producto + saldo por bodega -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-1">
          <p class="text-xs font-medium text-slate-500">Producto</p>
          <p class="mt-1 text-base font-bold text-slate-800">{item.name}</p>
          <p class="text-xs text-slate-500">{item.internal_code}</p>
        </div>
        {#each (stockInfo ?? []) as si}
          <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500">{si.warehouse}</p>
            <p class="mt-1 text-lg font-bold text-slate-800">{fmt(si.stock)} unid.</p>
            <p class="text-xs text-slate-500">Costo promedio: $ {fmtCOP(si.average_cost)}</p>
          </div>
        {/each}
      </div>

      <!-- Tabla de movimientos -->
      {#if movements.length === 0}
        <div class="rounded-xl border border-slate-200 bg-white p-12 text-center text-sm text-slate-400">
          <i class="mdi mdi-package-variant-closed text-4xl text-slate-300"></i>
          <p class="mt-2">No hay movimientos para el período seleccionado.</p>
        </div>
      {:else}
        <!-- Resumen del período -->
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs text-emerald-700">Total entradas</p>
            <p class="mt-1 text-xl font-bold text-emerald-800">+ {fmt(totals.totalIn)}</p>
          </div>
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
            <p class="text-xs text-rose-700">Total salidas</p>
            <p class="mt-1 text-xl font-bold text-rose-800">- {fmt(totals.totalOut)}</p>
          </div>
          <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs text-blue-700">Movimiento neto período</p>
            <p class="mt-1 text-xl font-bold {totals.totalIn - totals.totalOut >= 0 ? 'text-blue-800' : 'text-rose-800'}">
              {totals.totalIn - totals.totalOut >= 0 ? '+' : ''}{fmt(totals.totalIn - totals.totalOut)}
            </p>
          </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm">
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                <tr>
                  <th class="px-3 py-2.5 text-left font-medium">Fecha</th>
                  <th class="px-3 py-2.5 text-left font-medium">Documento</th>
                  <th class="px-3 py-2.5 text-left font-medium">Bodega</th>
                  <th class="px-3 py-2.5 text-right font-medium text-emerald-700">Entrada</th>
                  <th class="px-3 py-2.5 text-right font-medium text-rose-700">Salida</th>
                  <th class="px-3 py-2.5 text-right font-medium">Saldo</th>
                  <th class="px-3 py-2.5 text-right font-medium">Costo Unit.</th>
                  <th class="px-3 py-2.5 text-right font-medium">Costo Prom.</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each runningBalance() as row}
                  <tr class="hover:bg-slate-50 {row.type === 'in' ? 'bg-emerald-50/30' : 'bg-rose-50/20'}">
                    <td class="px-3 py-2 text-slate-600">{row.date}</td>
                    <td class="px-3 py-2">
                      <span class="font-medium text-slate-800">{row.document}</span>
                    </td>
                    <td class="px-3 py-2 text-slate-500">{row.warehouse}</td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {#if row.input_quantity > 0}
                        <span class="font-semibold text-emerald-700">+ {fmt(row.input_quantity)}</span>
                      {:else}
                        <span class="text-slate-300">—</span>
                      {/if}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {#if row.output_quantity > 0}
                        <span class="font-semibold text-rose-600">- {fmt(row.output_quantity)}</span>
                      {:else}
                        <span class="text-slate-300">—</span>
                      {/if}
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums font-bold text-slate-800">{fmt(row.balance)}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-slate-600">$ {fmtCOP(row.purchase_price)}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">$ {fmtCOP(row.new_average)}</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    {:else}
      <div class="rounded-xl border border-slate-200 bg-white p-16 text-center text-slate-400">
        <i class="mdi mdi-warehouse text-5xl text-slate-300"></i>
        <p class="mt-3 text-sm">Selecciona un producto para ver su kardex</p>
      </div>
    {/if}

  </div>
</AppLayout>
