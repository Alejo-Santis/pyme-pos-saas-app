<script>
  import { router, page } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { users = [], roles = [] } = $props()

  const currentUserId = $derived($page.props.auth?.user?.id)

  const roleLabels = {
    admin:       'Administrador',
    contador:    'Contador',
    vendedor:    'Vendedor',
    almacenista: 'Almacenista',
    cajero:      'Cajero',
  }
  function roleLabel(name) {
    return roleLabels[name] ?? name
  }

  // Modal state
  let showModal  = $state(false)
  let editTarget = $state(null)
  let delTarget  = $state(null)

  const emptyForm = () => ({
    name: '', email: '', password: '', role: roles[0] ?? '', is_active: true,
  })

  let form    = $state(emptyForm())
  let errors  = $state({})
  let loading = $state(false)

  function openCreate() {
    editTarget = null
    form   = emptyForm()
    errors = {}
    showModal = true
  }

  function openEdit(u) {
    editTarget = u
    form = {
      name:      u.name  ?? '',
      email:     u.email ?? '',
      password:  '',
      role:      u.roles?.[0]?.name ?? roles[0] ?? '',
      is_active: u.is_active ?? true,
    }
    errors = {}
    showModal = true
  }

  function closeModal() {
    showModal  = false
    editTarget = null
  }

  function submit() {
    loading = true
    errors  = {}

    const url    = editTarget ? `/config/users/${editTarget.id}` : '/config/users'
    const method = editTarget ? 'put' : 'post'

    router[method](url, form, {
      preserveScroll: true,
      onSuccess: () => { closeModal(); loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }

  function confirmDeactivate(u) {
    delTarget = u
  }

  function doDeactivate() {
    if (!delTarget) return
    router.delete(`/config/users/${delTarget.id}`, {
      preserveScroll: true,
      onFinish: () => { delTarget = null },
    })
  }
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Usuarios</h1>
        <p class="text-sm text-slate-500 mt-0.5">Accesos al sistema para tu equipo y sus roles</p>
      </div>
      <button onclick={openCreate}
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition cursor-pointer">
        <i class="mdi mdi-plus text-base"></i>
        Nuevo usuario
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if users.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-account-group-outline text-4xl mb-3"></i>
          <p class="text-sm">No hay usuarios registrados</p>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Correo</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Rol</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Estado</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each users as u}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-medium text-slate-800">
                  {u.name}
                  {#if u.id === currentUserId}
                    <span class="ml-1 text-xs text-slate-400">(tú)</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600">{u.email}</td>
                <td class="px-4 py-3">
                  <span class="inline-block bg-primary/10 text-primary text-xs font-medium px-2 py-0.5 rounded">
                    {roleLabel(u.roles?.[0]?.name ?? '—')}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  {#if u.is_active}
                    <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5 font-medium">
                      <i class="mdi mdi-check-circle text-sm"></i> Activo
                    </span>
                  {:else}
                    <span class="inline-flex items-center gap-1 text-xs bg-slate-100 text-slate-500 border border-slate-200 rounded-full px-2 py-0.5 font-medium">
                      Inactivo
                    </span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button onclick={() => openEdit(u)}
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition cursor-pointer"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </button>
                    {#if u.id !== currentUserId && u.is_active}
                      <button onclick={() => confirmDeactivate(u)}
                        class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer"
                        title="Desactivar">
                        <i class="mdi mdi-account-off-outline text-base"></i>
                      </button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      {/if}
    </div>
  </div>
</AppLayout>

<!-- ═══ Modal crear / editar ════════════════════════════════════════════════ -->
{#if showModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">

      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">
          {editTarget ? 'Editar usuario' : 'Nuevo usuario'}
        </h2>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="p-5 space-y-4">

        <!-- Nombre -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre completo <span class="text-red-500">*</span>
          </label>
          <input bind:value={form.name} type="text" placeholder="Ledier Guzmán"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>

        <!-- Correo -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Correo electrónico <span class="text-red-500">*</span>
          </label>
          <input bind:value={form.email} type="email" placeholder="usuario@empresa.com"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.email ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.email}<p class="text-xs text-red-500 mt-1">{errors.email}</p>{/if}
        </div>

        <!-- Contraseña -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Contraseña {editTarget ? '' : '*'}
          </label>
          <input bind:value={form.password} type="password"
            placeholder={editTarget ? 'Deja vacío para no cambiarla' : 'Mínimo 8 caracteres'}
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.password ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.password}<p class="text-xs text-red-500 mt-1">{errors.password}</p>{/if}
        </div>

        <!-- Rol -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Rol <span class="text-red-500">*</span>
          </label>
          <select bind:value={form.role}
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white
              {errors.role ? 'border-red-400' : 'border-slate-300'}">
            {#each roles as r}
              <option value={r}>{roleLabel(r)}</option>
            {/each}
          </select>
          {#if errors.role}<p class="text-xs text-red-500 mt-1">{errors.role}</p>{/if}
        </div>

        {#if editTarget}
          <!-- Activo -->
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={form.is_active} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Usuario activo (puede iniciar sesión)</span>
          </label>
        {/if}

      </div>

      <div class="flex justify-end gap-3 px-5 py-4 border-t border-slate-100">
        <button onclick={closeModal}
          class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 cursor-pointer">
          Cancelar
        </button>
        <button onclick={submit} disabled={loading}
          class="px-4 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition disabled:opacity-60 cursor-pointer">
          {loading ? 'Guardando…' : (editTarget ? 'Actualizar' : 'Crear')}
        </button>
      </div>
    </div>
  </div>
{/if}

<!-- ═══ Modal confirmar desactivación ════════════════════════════════════════ -->
{#if delTarget}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
      <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="mdi mdi-account-off-outline text-2xl text-red-500"></i>
      </div>
      <h3 class="font-semibold text-slate-800 mb-2">¿Desactivar usuario?</h3>
      <p class="text-sm text-slate-500 mb-6">
        <strong>{delTarget.name}</strong> no podrá iniciar sesión hasta que lo reactives editándolo.
      </p>
      <div class="flex gap-3 justify-center">
        <button onclick={() => delTarget = null}
          class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer">
          Cancelar
        </button>
        <button onclick={doDeactivate}
          class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition cursor-pointer">
          Desactivar
        </button>
      </div>
    </div>
  </div>
{/if}
