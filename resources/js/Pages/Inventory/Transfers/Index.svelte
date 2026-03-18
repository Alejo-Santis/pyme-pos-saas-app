<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    transfers  = { data: [], links: [], meta: {} },
    warehouses = [],
    filters    = {},
  } = $props()

  let form = $state({
    status:       filters.status       ?? '',
    date_from:    filters.date_from    ?? '',
    date_to:      filters.date_to      ?? '',
    warehouse_id: filters.warehouse_id ?? '',
  })

  function search() {
    router.get('/inventory/transfers', form, { preserveState: true, replace: true })
  }

  function clear() {
    form = { status: '', date_from: '', date_to: '', warehouse_id: '' }
    search()
  }

  const statusConfig = {
    draft:      { label: 'Borrador',     cls: 'bg-slate-100 text-slate-600' },
    in_transit: { label: 'En tránsito',  cls: 'bg-amber-100 text-amber-700' },
    received:   { label: 'Recibido',     cls: 'bg-green-100 text-green-700' },
    cancelled:  { label: 'Cancelado',    cls: 'bg-red-100 text-red-700' },
  }

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Traslados entre bodegas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Mueve inventario de una bodega a otra con trazabilidad completa</p>
      </div>
      <a use:inertia href="/inventory/transfers/create"
        class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm rounded-xl font-medium hover:bg-primary-dark transition">
        <i class="mdi mdi-transfer text-base"></i>
        Nuevo traslado
      </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <select bind:value={form.status} onchange={search}
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30">
          <option value="">Todos los estados</option>
          <option value="draft">Borrador</option>
          <option value="in_transit">En tránsito</option>
          <option value="received">Recibido</option>
          <option value="cancelled">Cancelado</option>
        </select>

        <select bind:value={form.warehouse_id} onchange={search}
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30">
          <option value="">Todas las bodegas</option>
          {#each warehouses as wh}
            <option value={wh.id}>{wh.name}</option>
          {/each}
        </select>

        <input type="date" bind:value={form.date_from} onchange={search}
          placeholder="Desde"
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />

        <div class="flex gap-2">
          <input type="date" bind:value={form.date_to} onchange={search}
            placeholder="Hasta"
            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
          <button onclick={clear} class="px-3 py-2 text-slate-400 hover:text-slate-600 transition cursor-pointer" title="Limpiar filtros">
            <i class="mdi mdi-filter-remove-outline text-lg"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-medium text-slate-500 text-xs">Fecha</th>
              <th class="text-left px-4 py-3 font-medium text-slate-500 text-xs">Origen</th>
              <th class="text-left px-4 py-3 font-medium text-slate-500 text-xs">Destino</th>
              <th class="text-center px-4 py-3 font-medium text-slate-500 text-xs">Estado</th>
              <th class="text-right px-4 py-3 font-medium text-slate-500 text-xs">Total</th>
              <th class="text-center px-4 py-3 font-medium text-slate-500 text-xs w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each transfers.data as t}
              {@const sc = statusConfig[t.status] ?? statusConfig.draft}
              <tr class="hover:bg-slate-50/50 transition">
                <td class="px-4 py-3 text-slate-600 tabular-nums text-xs">
                  {t.transfer_date}
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-700">{t.warehouse_origin?.name ?? '—'}</p>
                  {#if t.warehouse_origin?.code}
                    <p class="text-xs text-slate-400">{t.warehouse_origin.code}</p>
                  {/if}
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-700">{t.warehouse_destination?.name ?? '—'}</p>
                  {#if t.warehouse_destination?.code}
                    <p class="text-xs text-slate-400">{t.warehouse_destination.code}</p>
                  {/if}
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {sc.cls}">
                    {sc.label}
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums">
                  ${fmt(t.total)}
                </td>
                <td class="px-4 py-3 text-center">
                  <a use:inertia href="/inventory/transfers/{t.id}"
                    class="text-primary hover:text-primary-dark transition">
                    <i class="mdi mdi-eye-outline text-lg"></i>
                  </a>
                </td>
              </tr>
            {/each}
            {#if transfers.data.length === 0}
              <tr>
                <td colspan="6" class="px-4 py-12 text-center">
                  <i class="mdi mdi-transfer text-4xl text-slate-200 block mb-2"></i>
                  <p class="text-sm text-slate-400">Sin traslados registrados</p>
                  <a use:inertia href="/inventory/transfers/create"
                    class="inline-flex items-center gap-1.5 mt-3 text-sm text-primary hover:underline">
                    <i class="mdi mdi-plus"></i> Crear primer traslado
                  </a>
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      {#if transfers.meta?.last_page > 1}
        <div class="border-t border-slate-100 px-4 py-3 flex justify-between items-center text-sm text-slate-500">
          <span>Página {transfers.meta.current_page} de {transfers.meta.last_page}</span>
          <div class="flex gap-1">
            {#each transfers.links as link}
              {#if link.url}
                <button onclick={() => router.visit(link.url)}
                  class="px-3 py-1 rounded-lg border transition cursor-pointer
                    {link.active ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:bg-slate-50'}">
                  {@html link.label}
                </button>
              {/if}
            {/each}
          </div>
        </div>
      {/if}
    </div>

  </div>
</AppLayout>
