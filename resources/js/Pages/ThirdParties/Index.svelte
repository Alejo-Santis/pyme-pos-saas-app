<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ImportModal from '@/Components/UI/ImportModal.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let { thirds, filters = {}, types = [], docTypes = [] } = $props()

  // Filtros reactivos
  let search = $state(filters.search ?? '')
  let type   = $state(filters.type   ?? '')
  let status = $state(filters.status ?? '')

  let searchTimeout

  function applyFilters() {
    router.get('/third-parties', { search, type, status }, {
      preserveScroll: true,
      replace: true,
    })
  }

  function onSearch() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 400)
  }

  let confirmDelete = $state({ open: false, id: null })

  function deleteThird(id) {
    confirmDelete = { open: true, id }
  }

  function toggleStatus(id) {
    router.patch(`/third-parties/${id}/toggle`, {}, { preserveScroll: true })
  }

  // Badge tipo de tercero
  function linkageLabel(linkage) {
    if (!linkage) return ''
    const tags = []
    if (linkage.customer) tags.push('Cliente')
    if (linkage.provider) tags.push('Proveedor')
    if (linkage.other)    tags.push('Otro')
    return tags.join(', ')
  }

  let showImport = $state(false)
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Terceros</h1>
        <p class="text-sm text-slate-500 mt-0.5">Clientes, proveedores y contactos</p>
      </div>
      <div class="flex items-center gap-2">
        <button onclick={() => showImport = true}
          class="flex items-center gap-2 border border-slate-200 text-slate-600 px-3 py-2 rounded-lg text-sm hover:bg-slate-50 transition cursor-pointer">
          <i class="mdi mdi-file-import text-base"></i>
          Importar
        </button>
        <a use:inertia href="/third-parties/create"
          class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
          <i class="mdi mdi-plus text-base"></i>
          Nuevo tercero
        </a>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3">

        <!-- Búsqueda -->
        <div class="relative flex-1 min-w-52">
          <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <input bind:value={search} oninput={onSearch} type="text"
            placeholder="Buscar por nombre o identificación…"
            class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Tipo -->
        <select bind:value={type} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-36">
          <option value="">Todos los tipos</option>
          <option value="customer">Clientes</option>
          <option value="provider">Proveedores</option>
          <option value="other">Otros</option>
        </select>

        <!-- Estado -->
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
      {#if thirds.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-account-group-outline text-4xl mb-3"></i>
          <p class="text-sm">No se encontraron terceros</p>
          <a use:inertia href="/third-parties/create"
            class="mt-3 text-sm text-primary hover:underline">
            Crear el primero
          </a>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Identificación</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Tipo</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Email</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Teléfono</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Estado</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each thirds.data as t}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-mono text-slate-700 text-xs">
                  {t.identification_number}{t.dv ? `-${t.dv}` : ''}
                </td>
                <td class="px-4 py-3 font-medium text-slate-800">
                  {t.name}{t.surname ? ` ${t.surname}` : ''}
                </td>
                <td class="px-4 py-3">
                  {#if t.linkage}
                    <div class="flex flex-wrap gap-1">
                      {#if t.linkage.customer}
                        <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-full px-2 py-0.5">Cliente</span>
                      {/if}
                      {#if t.linkage.provider}
                        <span class="text-xs bg-purple-50 text-purple-700 border border-purple-200 rounded-full px-2 py-0.5">Proveedor</span>
                      {/if}
                      {#if t.linkage.other}
                        <span class="text-xs bg-slate-100 text-slate-600 border border-slate-200 rounded-full px-2 py-0.5">Otro</span>
                      {/if}
                    </div>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600">{t.email ?? '—'}</td>
                <td class="px-4 py-3 text-slate-600">{t.phone ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                  <button onclick={() => toggleStatus(t.id)}
                    title={t.is_active ? 'Desactivar' : 'Activar'}
                    class="cursor-pointer">
                    {#if t.is_active}
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
                    <a use:inertia href="/third-parties/{t.id}/edit"
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </a>
                    <button onclick={() => deleteThird(t.id)}
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
        {#if thirds.last_page > 1}
          <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
            <p class="text-xs text-slate-500">
              Mostrando {thirds.from}–{thirds.to} de {thirds.total} terceros
            </p>
            <div class="flex gap-1">
              {#each thirds.links as link}
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
  uploadUrl="/third-parties/import"
  templateUrl="/third-parties/import/template"
  title="Importar Terceros"
  columns={['tipo_documento','numero_documento','digito_verificacion','tipo_persona','nombre_razon_social','apellidos','email','telefono','direccion','ciudad','es_cliente','es_proveedor']}
/>

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar tercero"
  message="¿Eliminar este tercero? Esta acción no se puede deshacer."
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete('/third-parties/' + confirmDelete.id, { preserveScroll: true })}
/>
