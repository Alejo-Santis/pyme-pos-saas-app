<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { establishments = [], company = null } = $props()

  // Modal state
  let showModal   = $state(false)
  let editTarget  = $state(null)   // null = crear, objeto = editar
  let delTarget   = $state(null)

  // Flash
  let flash = $state({ success: null, error: null })

  // Form
  const emptyForm = () => ({
    name: '', business_name: '', address: '',
    municipality_id: '', is_main: false, sync_items_full: false,
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

  function openEdit(est) {
    editTarget = est
    form = {
      name:            est.name            ?? '',
      business_name:   est.business_name   ?? '',
      address:         est.address         ?? '',
      municipality_id: est.municipality_id ?? '',
      is_main:         est.is_main         ?? false,
      sync_items_full: est.sync_items_full ?? false,
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

    const url    = editTarget ? `/config/establishments/${editTarget.id}` : '/config/establishments'
    const method = editTarget ? 'put' : 'post'

    router[method](url, form, {
      preserveScroll: true,
      onSuccess: () => { closeModal(); loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }

  function confirmDelete(est) {
    delTarget = est
  }

  function doDelete() {
    if (!delTarget) return
    router.delete(`/config/establishments/${delTarget.id}`, {
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
        <h1 class="text-xl font-bold text-slate-800">Establecimientos</h1>
        <p class="text-sm text-slate-500 mt-0.5">Sedes físicas de la empresa</p>
      </div>
      <button onclick={openCreate}
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition cursor-pointer">
        <i class="mdi mdi-plus text-base"></i>
        Nuevo establecimiento
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if establishments.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-store-outline text-4xl mb-3"></i>
          <p class="text-sm">No hay establecimientos registrados</p>
          <button onclick={openCreate}
            class="mt-3 text-sm text-primary hover:underline cursor-pointer">
            Crear el primero
          </button>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Razón social</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Dirección</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Bodegas</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Principal</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each establishments as est}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-medium text-slate-800">
                  {est.name}
                </td>
                <td class="px-4 py-3 text-slate-600">{est.business_name ?? '—'}</td>
                <td class="px-4 py-3 text-slate-600">{est.address ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center w-6 h-6 bg-slate-100 rounded-full text-xs font-semibold text-slate-600">
                    {est.warehouses_count ?? 0}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  {#if est.is_main}
                    <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5 font-medium">
                      <i class="mdi mdi-check-circle text-sm"></i> Principal
                    </span>
                  {:else}
                    <span class="text-slate-400 text-xs">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button onclick={() => openEdit(est)}
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition cursor-pointer"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </button>
                    {#if !est.is_main}
                      <button onclick={() => confirmDelete(est)}
                        class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer"
                        title="Eliminar">
                        <i class="mdi mdi-trash-can-outline text-base"></i>
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

<!-- ═══ Modal crear / editar ═══════════════════════════════════════════════ -->
{#if showModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">

      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">
          {editTarget ? 'Editar establecimiento' : 'Nuevo establecimiento'}
        </h2>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="p-5 space-y-4">

        <!-- Nombre -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre <span class="text-red-500">*</span>
          </label>
          <input bind:value={form.name} type="text" placeholder="Sede principal"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>

        <!-- Razón social -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Razón social</label>
          <input bind:value={form.business_name} type="text" placeholder="Igual a la empresa (opcional)"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Dirección -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
          <input bind:value={form.address} type="text" placeholder="Calle 1 # 2-3"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Checks -->
        <div class="flex gap-6">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={form.is_main} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Establecimiento principal</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={form.sync_items_full} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Sincronizar artículos completo</span>
          </label>
        </div>

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

<!-- ═══ Modal confirmar eliminar ════════════════════════════════════════════ -->
{#if delTarget}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
      <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="mdi mdi-trash-can-outline text-2xl text-red-500"></i>
      </div>
      <h3 class="font-semibold text-slate-800 mb-2">¿Eliminar establecimiento?</h3>
      <p class="text-sm text-slate-500 mb-6">
        Se eliminará <strong>{delTarget.name}</strong>. Esta acción no se puede deshacer.
      </p>
      <div class="flex gap-3 justify-center">
        <button onclick={() => delTarget = null}
          class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer">
          Cancelar
        </button>
        <button onclick={doDelete}
          class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition cursor-pointer">
          Eliminar
        </button>
      </div>
    </div>
  </div>
{/if}
