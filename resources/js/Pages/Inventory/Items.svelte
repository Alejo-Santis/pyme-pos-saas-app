<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ImportModal from '@/Components/UI/ImportModal.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let {
    items,
    categories = [],
    filters    = {},
    unitMeasures = [],
    taxes      = [],
  } = $props()

  let search      = $state(filters.search      ?? '')
  let category_id = $state(filters.category_id ?? '')
  let type        = $state(filters.type        ?? '')
  let status      = $state(filters.status      ?? '')

  let searchTimeout

  function applyFilters() {
    router.get('/inventory', { search, category_id, type, status }, {
      preserveScroll: true,
      replace: true,
    })
  }

  function onSearch() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 400)
  }

  function toggleStatus(id) {
    router.patch(`/inventory/${id}/toggle`, {}, { preserveScroll: true })
  }

  let confirmDelete = $state({ open: false, id: null, name: '' })

  function deleteItem(id, name) {
    confirmDelete = { open: true, id, name }
  }

  const typeLabel = { product: 'Producto', service: 'Servicio', combo: 'Combo' }
  const typeBadge = {
    product: 'bg-blue-50 text-blue-700 border-blue-200',
    service: 'bg-purple-50 text-purple-700 border-purple-200',
    combo:   'bg-amber-50 text-amber-700 border-amber-200',
  }

  let showImport = $state(false)
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Artículos</h1>
        <p class="text-sm text-slate-500 mt-0.5">Productos y servicios del inventario</p>
      </div>
      <div class="flex gap-2">
        <a use:inertia href="/inventory/categories"
          class="flex items-center gap-2 border border-slate-200 text-slate-600 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 transition">
          <i class="mdi mdi-tag-multiple-outline text-base"></i>
          Categorías
        </a>
        <button onclick={() => showImport = true}
          class="flex items-center gap-2 border border-slate-200 text-slate-600 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 transition cursor-pointer">
          <i class="mdi mdi-file-import text-base"></i>
          Importar
        </button>
        <a use:inertia href="/inventory/create"
          class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
          <i class="mdi mdi-plus text-base"></i>
          Nuevo artículo
        </a>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-52">
          <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <input bind:value={search} oninput={onSearch} type="text"
            placeholder="Buscar por nombre o código…"
            class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <select bind:value={category_id} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-40">
          <option value="">Todas las categorías</option>
          {#each categories as cat}
            <option value={cat.id}>{cat.name}</option>
          {/each}
        </select>

        <select bind:value={type} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-36">
          <option value="">Todos los tipos</option>
          <option value="product">Producto</option>
          <option value="service">Servicio</option>
          <option value="combo">Combo</option>
        </select>

        <select bind:value={status} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-36">
          <option value="">Todos los estados</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if items.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-package-variant-closed text-4xl mb-3"></i>
          <p class="text-sm">No se encontraron artículos</p>
          <a use:inertia href="/inventory/create"
            class="mt-3 text-sm text-primary hover:underline">
            Crear el primero
          </a>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Código</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Categoría</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Tipo</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Precio venta</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Stock</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Estado</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each items.data as item}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-mono text-xs text-slate-600">{item.internal_code ?? '—'}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-800">{item.name}</p>
                  {#if item.short_name}
                    <p class="text-xs text-slate-400">{item.short_name}</p>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">{item.item_category?.name ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                  <span class="text-xs border rounded-full px-2 py-0.5 font-medium {typeBadge[item.type] ?? ''}">
                    {typeLabel[item.type] ?? item.type}
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-medium text-slate-700">
                  {#if item.default_sale_price > 0}
                    ${Number(item.default_sale_price).toLocaleString('es-CO')}
                  {:else}
                    <span class="text-slate-400">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-center">
                  {#if item.manages_stock}
                    <i class="mdi mdi-check-circle text-green-500" title="Maneja stock"></i>
                  {:else}
                    <i class="mdi mdi-minus-circle text-slate-300" title="No maneja stock"></i>
                  {/if}
                </td>
                <td class="px-4 py-3 text-center">
                  <button onclick={() => toggleStatus(item.id)} class="cursor-pointer" title="Cambiar estado">
                    {#if item.is_active}
                      <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5 font-medium">
                        <i class="mdi mdi-check-circle text-sm"></i> Activo
                      </span>
                    {:else}
                      <span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-500 border border-slate-200 rounded-full px-2 py-0.5 font-medium">
                        <i class="mdi mdi-minus-circle text-sm"></i> Inactivo
                      </span>
                    {/if}
                  </button>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a use:inertia href="/inventory/{item.id}/edit"
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </a>
                    <button onclick={() => deleteItem(item.id, item.name)}
                      class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer"
                      title="Eliminar">
                      <i class="mdi mdi-trash-can-outline text-base"></i>
                    </button>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>

        <!-- Paginación -->
        {#if items.last_page > 1}
          <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
            <p class="text-xs text-slate-500">
              Mostrando {items.from}–{items.to} de {items.total} artículos
            </p>
            <div class="flex gap-1">
              {#each items.links as link}
                {#if link.url}
                  <a use:inertia href={link.url}
                    class="px-3 py-1.5 text-xs rounded border transition
                      {link.active
                        ? 'bg-primary text-white border-primary'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-primary hover:text-primary'}">
                    {@html link.label}
                  </a>
                {:else}
                  <span class="px-3 py-1.5 text-xs rounded border border-slate-200 text-slate-300">
                    {@html link.label}
                  </span>
                {/if}
              {/each}
            </div>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</AppLayout>

<ImportModal
  bind:open={showImport}
  uploadUrl="/inventory/import"
  templateUrl="/inventory/import/template"
  title="Importar Artículos"
  columns={['nombre','nombre_corto','codigo_interno','tipo','categoria','unidad_medida','precio_venta','costo_promedio','stock_minimo','porcentaje_iva']}
/>

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar artículo"
  message={confirmDelete.name ? `¿Eliminar "${confirmDelete.name}"? Esta acción no se puede deshacer.` : ''}
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete(`/inventory/${confirmDelete.id}`, { preserveScroll: true })}
/>
