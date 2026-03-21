<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let { users, roles = [], filters = {} } = $props()

  let search  = $state(filters.search ?? '')
  let role    = $state(filters.role   ?? '')
  let status  = $state(filters.status ?? '')

  let searchTimeout

  function applyFilters() {
    router.get('/users', { search, role, status }, { preserveScroll: true, replace: true })
  }

  function onSearch() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 400)
  }

  function toggleStatus(id) {
    router.patch(`/users/${id}/toggle`, {}, { preserveScroll: true })
  }

  let confirmDelete = $state({ open: false, id: null, name: '' })

  function deleteUser(id, name) {
    confirmDelete = { open: true, id, name }
  }

  const roleColors = {
    admin:       'bg-purple-50 text-purple-700 border-purple-200',
    contador:    'bg-blue-50 text-blue-700 border-blue-200',
    vendedor:    'bg-green-50 text-green-700 border-green-200',
    cajero:      'bg-amber-50 text-amber-700 border-amber-200',
    almacenista: 'bg-cyan-50 text-cyan-700 border-cyan-200',
  }

  const roleLabels = {
    admin:       'Administrador',
    contador:    'Contador',
    vendedor:    'Vendedor',
    cajero:      'Cajero',
    almacenista: 'Almacenista',
  }
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Usuarios</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gestión de usuarios y roles de la empresa</p>
      </div>
      <a use:inertia href="/users/create"
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
        <i class="mdi mdi-account-plus text-base"></i>
        Nuevo usuario
      </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3">

        <div class="relative flex-1 min-w-52">
          <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <input bind:value={search} oninput={onSearch} type="text"
            placeholder="Buscar por nombre o correo…"
            class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <select bind:value={role} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white
                 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-40">
          <option value="">Todos los roles</option>
          {#each roles as r}
            <option value={r}>{roleLabels[r] ?? r}</option>
          {/each}
        </select>

        <select bind:value={status} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white
                 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-36">
          <option value="">Todos los estados</option>
          <option value="1">Activos</option>
          <option value="0">Inactivos</option>
        </select>

      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if users.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-account-group-outline text-4xl mb-3"></i>
          <p class="text-sm">No se encontraron usuarios</p>
          <a use:inertia href="/users/create" class="mt-3 text-sm text-primary hover:underline">
            Crear el primero
          </a>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Usuario</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Rol</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Teléfono</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Registrado</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Estado</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each users.data as user}
              <tr class="hover:bg-slate-50 transition">

                <!-- Nombre + email con avatar inicial -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                      <span class="text-primary font-semibold text-xs">
                        {user.name.split(' ').slice(0,2).map(w => w[0]?.toUpperCase() ?? '').join('')}
                      </span>
                    </div>
                    <div>
                      <p class="font-medium text-slate-800">{user.name}</p>
                      <p class="text-xs text-slate-400">{user.email}</p>
                    </div>
                  </div>
                </td>

                <!-- Rol -->
                <td class="px-4 py-3">
                  {#if user.roles.length > 0}
                    {#each user.roles as r}
                      <span class="text-xs border rounded-full px-2.5 py-0.5 font-medium
                        {roleColors[r] ?? 'bg-slate-50 text-slate-600 border-slate-200'}">
                        {roleLabels[r] ?? r}
                      </span>
                    {/each}
                  {:else}
                    <span class="text-slate-400 text-xs">Sin rol</span>
                  {/if}
                </td>

                <!-- Teléfono -->
                <td class="px-4 py-3 text-slate-600 text-sm">
                  {user.phone ?? '—'}
                </td>

                <!-- Fecha de registro -->
                <td class="px-4 py-3 text-slate-500 text-sm">
                  {user.created_at ?? '—'}
                </td>

                <!-- Estado -->
                <td class="px-4 py-3 text-center">
                  <button
                    onclick={() => toggleStatus(user.id)}
                    class="text-xs border rounded-full px-2.5 py-0.5 font-medium transition cursor-pointer
                      {user.is_active
                        ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
                        : 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100'}">
                    {user.is_active ? 'Activo' : 'Inactivo'}
                  </button>
                </td>

                <!-- Acciones -->
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a use:inertia href="/users/{user.id}/edit"
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </a>
                    <button onclick={() => deleteUser(user.id, user.name)}
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
        {#if users.last_page > 1}
          <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
            <p class="text-xs text-slate-500">
              Mostrando {users.from}–{users.to} de {users.total} usuarios
            </p>
            <div class="flex gap-1">
              {#each users.links as link}
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

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar usuario"
  message="¿Eliminar al usuario «{confirmDelete.name}»? Esta acción no se puede deshacer."
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete(`/users/${confirmDelete.id}`, { preserveScroll: true })}
/>
