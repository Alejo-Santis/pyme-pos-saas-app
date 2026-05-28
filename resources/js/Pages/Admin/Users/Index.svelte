<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router } from '@inertiajs/svelte'

  let { admins = { data: [] }, filters = {} } = $props()

  let search = $state(filters.search ?? '')
  let status = $state(filters.status ?? '')
  let showForm = $state(false)
  let showPasswordForm = $state(false)
  let editingAdmin = $state(null)
  let passwordAdmin = $state(null)
  let form = $state({ name: '', email: '', password: '', password_confirmation: '', is_active: true })
  let passwordForm = $state({ password: '', password_confirmation: '' })

  function applyFilters() {
    router.get('/admin/users', { search, status }, { preserveScroll: true })
  }

  function clearFilters() {
    search = ''
    status = ''
    router.get('/admin/users', {}, { replace: true })
  }

  function openCreate() {
    editingAdmin = null
    form = { name: '', email: '', password: '', password_confirmation: '', is_active: true }
    showForm = true
  }

  function openEdit(admin) {
    editingAdmin = admin
    form = { name: admin.name, email: admin.email, password: '', password_confirmation: '', is_active: admin.is_active }
    showForm = true
  }

  function saveAdmin() {
    if (editingAdmin) {
      router.put(`/admin/users/${editingAdmin.id}`, {
        name: form.name,
        email: form.email,
        is_active: form.is_active,
      }, { preserveScroll: true, onSuccess: () => showForm = false })
      return
    }

    router.post('/admin/users', form, { preserveScroll: true, onSuccess: () => showForm = false })
  }

  function openPassword(admin) {
    passwordAdmin = admin
    passwordForm = { password: '', password_confirmation: '' }
    showPasswordForm = true
  }

  function savePassword() {
    router.patch(`/admin/users/${passwordAdmin.id}/password`, passwordForm, {
      preserveScroll: true,
      onSuccess: () => showPasswordForm = false,
    })
  }

  function toggleAdmin(admin) {
    router.patch(`/admin/users/${admin.id}/toggle`, {}, { preserveScroll: true })
  }
</script>

<AdminLayout>
  <div class="space-y-5">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-slate-800">Administradores</h2>
        <p class="text-slate-500 text-sm">Usuarios con acceso al portal super admin</p>
      </div>
      <button onclick={openCreate} class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark transition">
        <i class="mdi mdi-plus"></i>
        Nuevo admin
      </button>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
          <label for="admin-search" class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <div class="relative">
            <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input
              id="admin-search"
              type="text"
              bind:value={search}
              placeholder="Nombre o email..."
              onkeydown={(e) => e.key === 'Enter' && applyFilters()}
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
        </div>

        <div>
          <label for="admin-status" class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
          <select
            id="admin-status"
            bind:value={status}
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>

        <button onclick={applyFilters} class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
          Filtrar
        </button>

        {#if filters.search || filters.status}
          <button onclick={clearFilters} class="text-slate-500 hover:text-slate-700 text-sm transition">
            Limpiar
          </button>
        {/if}
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Admin</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Estado</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Último login</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Creado</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {#if admins.data.length === 0}
              <tr>
                <td colspan="5" class="text-center py-12 text-slate-400">
                  <i class="mdi mdi-shield-account-outline text-3xl block mb-2"></i>
                  No hay administradores
                </td>
              </tr>
            {:else}
              {#each admins.data as admin}
                <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                        <span class="text-primary text-xs font-bold">{admin.name?.charAt(0)?.toUpperCase()}</span>
                      </div>
                      <div>
                        <p class="font-medium text-slate-800">{admin.name}</p>
                        <p class="text-xs text-slate-400">{admin.email}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold {admin.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                      {admin.is_active ? 'Activo' : 'Inactivo'}
                    </span>
                    {#if admin.is_current}
                      <span class="ml-2 text-xs text-primary font-medium">Tú</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{admin.last_login_at ?? '—'}</td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{admin.created_at}</td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-2">
                      <button onclick={() => openEdit(admin)} class="text-slate-400 hover:text-primary transition" title="Editar">
                        <i class="mdi mdi-pencil-outline text-lg"></i>
                      </button>
                      <button onclick={() => openPassword(admin)} class="text-slate-400 hover:text-primary transition" title="Cambiar contraseña">
                        <i class="mdi mdi-key-outline text-lg"></i>
                      </button>
                      <button
                        onclick={() => toggleAdmin(admin)}
                        disabled={admin.is_current}
                        class="text-slate-400 hover:text-amber-600 disabled:opacity-30 disabled:cursor-not-allowed transition"
                        title={admin.is_active ? 'Desactivar' : 'Activar'}
                      >
                        <i class="mdi {admin.is_active ? 'mdi-account-off-outline' : 'mdi-account-check-outline'} text-lg"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      {#if admins.last_page > 1}
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Mostrando {admins.from}–{admins.to} de {admins.total}</p>
          <div class="flex gap-1">
            {#each admins.links as link}
              {#if link.url}
                <a href={link.url} class="px-3 py-1 rounded text-xs transition {link.active ? 'bg-primary text-white font-medium' : 'text-slate-600 hover:bg-slate-100'}">
                  {@html link.label}
                </a>
              {:else}
                <span class="px-3 py-1 rounded text-xs text-slate-300">{@html link.label}</span>
              {/if}
            {/each}
          </div>
        </div>
      {/if}
    </div>
  </div>
</AdminLayout>

{#if showForm}
  <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onclick={(e) => e.target === e.currentTarget && (showForm = false)}>
    <div class="w-full max-w-lg bg-white rounded-xl shadow-xl border border-slate-100">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-800">{editingAdmin ? 'Editar administrador' : 'Nuevo administrador'}</h3>
        <button onclick={() => showForm = false} class="text-slate-400 hover:text-slate-600" title="Cerrar">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="p-5 space-y-4">
        <div>
          <label for="admin-name" class="block text-xs font-medium text-slate-600 mb-1">Nombre</label>
          <input id="admin-name" bind:value={form.name} type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
        </div>
        <div>
          <label for="admin-email" class="block text-xs font-medium text-slate-600 mb-1">Email</label>
          <input id="admin-email" bind:value={form.email} type="email" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
        </div>

        {#if !editingAdmin}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="admin-password" class="block text-xs font-medium text-slate-600 mb-1">Contraseña</label>
              <input id="admin-password" bind:value={form.password} type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>
            <div>
              <label for="admin-password-confirmation" class="block text-xs font-medium text-slate-600 mb-1">Confirmar</label>
              <input id="admin-password-confirmation" bind:value={form.password_confirmation} type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>
          </div>
        {/if}

        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
          <input type="checkbox" bind:checked={form.is_active} class="rounded border-slate-300 text-primary focus:ring-primary" />
          Activo
        </label>
      </div>

      <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100">
        <button onclick={() => showForm = false} class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition">Cancelar</button>
        <button onclick={saveAdmin} class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition">Guardar</button>
      </div>
    </div>
  </div>
{/if}

{#if showPasswordForm}
  <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onclick={(e) => e.target === e.currentTarget && (showPasswordForm = false)}>
    <div class="w-full max-w-md bg-white rounded-xl shadow-xl border border-slate-100">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-800">Cambiar contraseña</h3>
        <button onclick={() => showPasswordForm = false} class="text-slate-400 hover:text-slate-600" title="Cerrar">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>
      <div class="p-5 space-y-4">
        <p class="text-sm text-slate-500">{passwordAdmin?.email}</p>
        <div>
          <label for="password-new" class="block text-xs font-medium text-slate-600 mb-1">Nueva contraseña</label>
          <input id="password-new" bind:value={passwordForm.password} type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
        </div>
        <div>
          <label for="password-confirmation" class="block text-xs font-medium text-slate-600 mb-1">Confirmar contraseña</label>
          <input id="password-confirmation" bind:value={passwordForm.password_confirmation} type="password" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
        </div>
      </div>
      <div class="flex justify-end gap-2 px-5 py-4 border-t border-slate-100">
        <button onclick={() => showPasswordForm = false} class="px-4 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-100 transition">Cancelar</button>
        <button onclick={savePassword} class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition">Actualizar</button>
      </div>
    </div>
  </div>
{/if}
