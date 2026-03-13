<script>
  import { page, router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { orders, filters } = $props()

  let search = $state(filters.search ?? '')
  let statusFilter = $state(filters.status ?? '')

  const statuses = [
    { value: '',          label: 'Todos' },
    { value: 'draft',     label: 'Borrador' },
    { value: 'pending',   label: 'Pendiente' },
    { value: 'approved',  label: 'Aprobada' },
    { value: 'partial',   label: 'Parcial' },
    { value: 'received',  label: 'Recibida' },
    { value: 'cancelled', label: 'Anulada' },
  ]

  const statusConfig = {
    draft:     { label: 'Borrador',   cls: 'bg-slate-100 text-slate-700' },
    pending:   { label: 'Pendiente',  cls: 'bg-yellow-100 text-yellow-800' },
    approved:  { label: 'Aprobada',   cls: 'bg-blue-100 text-blue-800' },
    partial:   { label: 'Parcial',    cls: 'bg-orange-100 text-orange-800' },
    received:  { label: 'Recibida',   cls: 'bg-green-100 text-green-800' },
    cancelled: { label: 'Anulada',    cls: 'bg-red-100 text-red-800' },
  }

  function applyFilters() {
    router.get('/purchases', { search, status: statusFilter }, { preserveState: true, replace: true })
  }

  function clearFilters() {
    search = ''
    statusFilter = ''
    router.get('/purchases', {})
  }

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
  }
</script>

<AppLayout title="Órdenes de Compra">
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Órdenes de Compra</h1>
      <p class="text-sm text-slate-500 mt-0.5">Gestión de compras a proveedores</p>
    </div>
    <a href="/purchases/create"
       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
      <i class="mdi mdi-plus"></i>
      Nueva Orden
    </a>
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-48">
      <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
      <input bind:value={search}
             onkeydown={(e) => e.key === 'Enter' && applyFilters()}
             type="text" placeholder="Código interno..."
             class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
    </div>
    <div class="min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
      <select bind:value={statusFilter}
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        {#each statuses as s}
          <option value={s.value}>{s.label}</option>
        {/each}
      </select>
    </div>
    <button onclick={applyFilters}
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
      <i class="mdi mdi-magnify mr-1"></i> Buscar
    </button>
    {#if search || statusFilter}
      <button onclick={clearFilters}
              class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
        Limpiar
      </button>
    {/if}
  </div>

  <!-- Tabla -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Código</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Proveedor</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Fecha</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Total</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Estado</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        {#each orders.data as order}
          {@const cfg = statusConfig[order.status] ?? { label: order.status, cls: 'bg-slate-100 text-slate-700' }}
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-medium text-blue-700">{order.internal_code}</td>
            <td class="px-4 py-3 text-slate-700">
              {order.third_party?.business_name ?? 'Sin proveedor'}
            </td>
            <td class="px-4 py-3 text-slate-600">{fmtDate(order.issue_date)}</td>
            <td class="px-4 py-3 text-right font-medium text-slate-800">{fmt(order.amount)}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {cfg.cls}">
                {cfg.label}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <a href="/purchases/{order.id}"
                 class="text-blue-600 hover:text-blue-800 font-medium">
                Ver
              </a>
            </td>
          </tr>
        {:else}
          <tr>
            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
              <i class="mdi mdi-cart-outline text-4xl block mb-2 opacity-50"></i>
              No hay órdenes de compra
            </td>
          </tr>
        {/each}
      </tbody>
    </table>

    <!-- Paginación -->
    {#if orders.last_page > 1}
      <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Mostrando {orders.from}–{orders.to} de {orders.total}</span>
        <div class="flex gap-1">
          {#each orders.links as link}
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
</AppLayout>
