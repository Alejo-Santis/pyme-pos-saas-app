<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { warehouses = [], establishments = [] } = $props()

  // Modal state
  let showModal  = $state(false)
  let editTarget = $state(null)
  let delTarget  = $state(null)

  const emptyForm = () => ({
    establishment_id: establishments[0]?.id ?? '',
    name:          '',
    internal_code: '',
    description:   '',
    is_main:       false,
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

  function openEdit(wh) {
    editTarget = wh
    form = {
      establishment_id: wh.establishment_id ?? '',
      name:             wh.name             ?? '',
      internal_code:    wh.internal_code    ?? '',
      description:      wh.description      ?? '',
      is_main:          wh.is_main          ?? false,
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

    const url    = editTarget ? `/config/warehouses/${editTarget.id}` : '/config/warehouses'
    const method = editTarget ? 'put' : 'post'

    router[method](url, form, {
      preserveScroll: true,
      onSuccess: () => { closeModal(); loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }

  function confirmDelete(wh) {
    delTarget = wh
  }

  function doDelete() {
    if (!delTarget) return
    router.delete(`/config/warehouses/${delTarget.id}`, {
      preserveScroll: true,
      onFinish: () => { delTarget = null },
    })
  }

  // Nombre del establecimiento para mostrar en tabla
  function estName(id) {
    return establishments.find(e => e.id === id)?.name ?? '—'
  }
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Bodegas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Almacenes y puntos de stock por establecimiento</p>
      </div>
      <button onclick={openCreate}
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition cursor-pointer">
        <i class="mdi mdi-plus text-base"></i>
        Nueva bodega
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if warehouses.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-warehouse text-4xl mb-3"></i>
          <p class="text-sm">No hay bodegas registradas</p>
          {#if establishments.length > 0}
            <button onclick={openCreate}
              class="mt-3 text-sm text-primary hover:underline cursor-pointer">
              Crear la primera
            </button>
          {:else}
            <p class="mt-2 text-xs text-slate-400">Primero crea un establecimiento</p>
          {/if}
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Código interno</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Establecimiento</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Descripción</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Principal</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each warehouses as wh}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-medium text-slate-800">{wh.name}</td>
                <td class="px-4 py-3 text-slate-600">
                  {#if wh.internal_code}
                    <span class="inline-block bg-slate-100 text-slate-700 text-xs font-mono px-2 py-0.5 rounded">
                      {wh.internal_code}
                    </span>
                  {:else}
                    —
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600">{wh.establishment?.name ?? estName(wh.establishment_id)}</td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{wh.description ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                  {#if wh.is_main}
                    <span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5 font-medium">
                      <i class="mdi mdi-check-circle text-sm"></i> Principal
                    </span>
                  {:else}
                    <span class="text-slate-400 text-xs">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button onclick={() => openEdit(wh)}
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition cursor-pointer"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </button>
                    {#if !wh.is_main}
                      <button onclick={() => confirmDelete(wh)}
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

<!-- ═══ Modal crear / editar ════════════════════════════════════════════════ -->
{#if showModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">

      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">
          {editTarget ? 'Editar bodega' : 'Nueva bodega'}
        </h2>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="p-5 space-y-4">

        <!-- Establecimiento -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Establecimiento <span class="text-red-500">*</span>
          </label>
          <select bind:value={form.establishment_id}
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white
              {errors.establishment_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each establishments as est}
              <option value={est.id}>{est.name}</option>
            {/each}
          </select>
          {#if errors.establishment_id}
            <p class="text-xs text-red-500 mt-1">{errors.establishment_id}</p>
          {/if}
        </div>

        <!-- Nombre -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre <span class="text-red-500">*</span>
          </label>
          <input bind:value={form.name} type="text" placeholder="Bodega principal"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>

        <!-- Código interno -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Código interno</label>
          <input bind:value={form.internal_code} type="text" placeholder="BOD-01"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary font-mono">
        </div>

        <!-- Descripción -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
          <textarea bind:value={form.description} rows="2" placeholder="Descripción opcional…"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none">
          </textarea>
        </div>

        <!-- Principal -->
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input bind:checked={form.is_main} type="checkbox"
            class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
          <span class="text-sm text-slate-700">Bodega principal del establecimiento</span>
        </label>

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
      <h3 class="font-semibold text-slate-800 mb-2">¿Eliminar bodega?</h3>
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
