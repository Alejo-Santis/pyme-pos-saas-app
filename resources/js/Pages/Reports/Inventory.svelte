<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'
  import { router } from '@inertiajs/svelte'

  let { items, totals, warehouses, filters } = $props()

  let warehouseId = $state(filters.warehouse_id ?? '')
  let search      = $state(filters.search ?? '')
  let filter      = $state(filters.filter ?? 'all')
  let expanded    = $state(null) // id del item expandido para ver por bodega

  function apply() {
    router.get('/reports/inventory', {
      warehouse_id: warehouseId || undefined,
      search:       search || undefined,
      filter,
    }, { preserveScroll: true })
  }

  function toggleExpand(id) {
    expanded = expanded === id ? null : id
  }

  const fmt    = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const fmtDec = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

  const statusBadge = {
    ok:    'bg-green-100 text-green-700',
    low:   'bg-amber-100 text-amber-700',
    empty: 'bg-red-100 text-red-700',
  }
  const statusLabel = { ok: 'Normal', low: 'Stock bajo', empty: 'Sin stock' }
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Reporte de Inventario</h1>
        <p class="text-sm text-slate-500 mt-0.5">Stock actual y valor del inventario por bodega</p>
      </div>
      <ExportButtons baseUrl="/reports/inventory/export" params={{ warehouse_id: warehouseId, filter }} />
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-slate-600 mb-1">Buscar artículo</label>
        <input
          type="text"
          bind:value={search}
          placeholder="Nombre, código o barcode…"
          onkeydown={(e) => e.key === 'Enter' && apply()}
          class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Bodega</label>
        <select bind:value={warehouseId} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Todas las bodegas</option>
          {#each warehouses as w}
            <option value={w.id}>{w.name}</option>
          {/each}
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Estado stock</label>
        <select bind:value={filter} class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="all">Todos</option>
          <option value="low_stock">Stock bajo</option>
          <option value="no_stock">Sin stock</option>
        </select>
      </div>
      <button onclick={apply} class="px-5 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer">
        <i class="mdi mdi-magnify mr-1"></i> Aplicar
      </button>
    </div>

    <!-- Tarjetas de totales -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Artículos</p>
        <p class="text-2xl font-bold text-slate-800">{fmt(totals.total_items)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Valor costo</p>
        <p class="text-2xl font-bold text-slate-800">${fmt(totals.total_stock_value)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Valor venta potencial</p>
        <p class="text-2xl font-bold text-green-600">${fmt(totals.total_sale_value)}</p>
      </div>
      <div class="bg-amber-50 rounded-xl border border-amber-200 p-4">
        <p class="text-xs text-amber-600 mb-1">Stock bajo</p>
        <p class="text-2xl font-bold text-amber-700">{totals.low_stock_items}</p>
      </div>
      <div class="bg-red-50 rounded-xl border border-red-200 p-4">
        <p class="text-xs text-red-500 mb-1">Sin stock</p>
        <p class="text-2xl font-bold text-red-600">{totals.empty_stock_items}</p>
      </div>
    </div>

    <!-- Tabla de inventario -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Artículos ({items.length})</h2>
        <p class="text-xs text-slate-400">Click en una fila para ver detalle por bodega</p>
      </div>

      {#if items.length === 0}
        <div class="py-16 text-center text-slate-400 text-sm">
          <i class="mdi mdi-package-variant-closed text-4xl block mb-2"></i>
          Sin artículos para los filtros seleccionados
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 sticky top-0 z-10">
              <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                <th class="px-4 py-2.5">Código</th>
                <th class="px-4 py-2.5">Artículo</th>
                <th class="px-4 py-2.5">Categoría</th>
                <th class="px-4 py-2.5 text-right">Stock</th>
                <th class="px-4 py-2.5 text-right">Mín.</th>
                <th class="px-4 py-2.5 text-right">Costo prom.</th>
                <th class="px-4 py-2.5 text-right">Precio venta</th>
                <th class="px-4 py-2.5 text-right">Valor costo</th>
                <th class="px-4 py-2.5">Estado</th>
              </tr>
            </thead>
            <tbody>
              {#each items as item}
                <tr
                  onclick={() => toggleExpand(item.id)}
                  class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer
                    {expanded === item.id ? 'bg-blue-50' : ''}"
                >
                  <td class="px-4 py-2.5 font-mono text-xs text-slate-500">{item.internal_code ?? '—'}</td>
                  <td class="px-4 py-2.5 font-medium text-slate-700">{item.name}</td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs">{item.category ?? '—'}</td>
                  <td class="px-4 py-2.5 text-right font-semibold
                    {item.status === 'empty' ? 'text-red-600' : item.status === 'low' ? 'text-amber-600' : 'text-slate-800'}">
                    {fmtDec(item.total_stock)}
                  </td>
                  <td class="px-4 py-2.5 text-right text-slate-500">{fmtDec(item.min_stock)}</td>
                  <td class="px-4 py-2.5 text-right text-slate-600">${fmtDec(item.avg_cost)}</td>
                  <td class="px-4 py-2.5 text-right text-slate-600">${fmt(item.sale_price)}</td>
                  <td class="px-4 py-2.5 text-right font-medium text-slate-800">${fmt(item.stock_value)}</td>
                  <td class="px-4 py-2.5">
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {statusBadge[item.status]}">
                      {statusLabel[item.status]}
                    </span>
                  </td>
                </tr>

                <!-- Detalle por bodega (expandible) -->
                {#if expanded === item.id && item.warehouses.length > 0}
                  <tr class="bg-blue-50">
                    <td colspan="9" class="px-8 py-3">
                      <p class="text-xs font-semibold text-blue-700 mb-2 uppercase tracking-wide">Detalle por bodega</p>
                      <div class="flex flex-wrap gap-3">
                        {#each item.warehouses as w}
                          <div class="bg-white border border-blue-200 rounded-lg px-4 py-2 text-xs">
                            <p class="font-semibold text-slate-700 mb-1">{w.name}</p>
                            <p class="text-slate-500">Stock: <span class="font-semibold text-slate-800">{fmtDec(w.stock)}</span></p>
                            <p class="text-slate-500">Costo: <span class="font-semibold text-slate-800">${fmtDec(w.cost)}</span></p>
                          </div>
                        {/each}
                      </div>
                    </td>
                  </tr>
                {/if}
              {/each}
            </tbody>
            <tfoot class="bg-slate-50 border-t-2 border-slate-200 text-sm font-semibold text-slate-700">
              <tr>
                <td class="px-4 py-2.5" colspan="7">TOTAL</td>
                <td class="px-4 py-2.5 text-right text-blue-700">${fmt(totals.total_stock_value)}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      {/if}
    </div>

  </div>
</AppLayout>
