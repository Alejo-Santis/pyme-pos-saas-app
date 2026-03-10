<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { categories = [] } = $props()

  let showModal  = $state(false)
  let editTarget = $state(null)
  let delTarget  = $state(null)

  const emptyForm = () => ({ name: '', description: '', parent_id: '' })
  let form    = $state(emptyForm())
  let errors  = $state({})
  let loading = $state(false)

  // Solo categorías raíz (para selector de padre)
  const rootCategories = $derived(
    editTarget
      ? categories.filter(c => !c.parent_id && c.id !== editTarget.id)
      : categories.filter(c => !c.parent_id)
  )

  function openCreate() {
    editTarget = null
    form   = emptyForm()
    errors = {}
    showModal = true
  }

  function openEdit(cat) {
    editTarget = cat
    form = {
      name:        cat.name        ?? '',
      description: cat.description ?? '',
      parent_id:   cat.parent_id   ?? '',
    }
    errors = {}
    showModal = true
  }

  function closeModal() { showModal = false; editTarget = null }

  function submit() {
    loading = true
    errors  = {}
    const url    = editTarget ? `/inventory/categories/${editTarget.id}` : '/inventory/categories'
    const method = editTarget ? 'put' : 'post'
    router[method](url, form, {
      preserveScroll: true,
      onSuccess: () => { closeModal(); loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }

  function doDelete() {
    if (!delTarget) return
    router.delete(`/inventory/categories/${delTarget.id}`, {
      preserveScroll: true,
      onFinish: () => { delTarget = null },
    })
  }

  // Categorías raíz con sus hijos
  const grouped = $derived(() => {
    const roots = categories.filter(c => !c.parent_id)
    return roots.map(r => ({
      ...r,
      children: categories.filter(c => c.parent_id === r.id),
    }))
  })
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Categorías de artículos</h1>
        <p class="text-sm text-slate-500 mt-0.5">Organiza tu inventario por categorías</p>
      </div>
      <button onclick={openCreate}
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition cursor-pointer">
        <i class="mdi mdi-plus text-base"></i>
        Nueva categoría
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if categories.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-tag-multiple-outline text-4xl mb-3"></i>
          <p class="text-sm">No hay categorías registradas</p>
          <button onclick={openCreate} class="mt-3 text-sm text-primary hover:underline cursor-pointer">
            Crear la primera
          </button>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Nombre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Categoría padre</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Descripción</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Artículos</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each categories as cat}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-medium text-slate-800">
                  {#if cat.parent_id}
                    <span class="text-slate-400 mr-1">└</span>
                  {/if}
                  {cat.name}
                </td>
                <td class="px-4 py-3 text-slate-500">{cat.parent?.name ?? '—'}</td>
                <td class="px-4 py-3 text-slate-500 max-w-xs truncate">{cat.description ?? '—'}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex items-center justify-center w-6 h-6 bg-slate-100 rounded-full text-xs font-semibold text-slate-600">
                    {cat.items_count ?? 0}
                  </span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button onclick={() => openEdit(cat)}
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition cursor-pointer"
                      title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </button>
                    <button onclick={() => delTarget = cat}
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
      {/if}
    </div>
  </div>
</AppLayout>

<!-- Modal crear/editar -->
{#if showModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-800">
          {editTarget ? 'Editar categoría' : 'Nueva categoría'}
        </h2>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>
      <div class="p-5 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre <span class="text-red-500">*</span>
          </label>
          <input bind:value={form.name} type="text" placeholder="Ej: Electrónicos"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Categoría padre</label>
          <select bind:value={form.parent_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Sin padre (raíz)</option>
            {#each rootCategories as cat}
              <option value={cat.id}>{cat.name}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Descripción</label>
          <textarea bind:value={form.description} rows="2" placeholder="Descripción opcional…"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none">
          </textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 px-5 py-4 border-t border-slate-100">
        <button onclick={closeModal} class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800 cursor-pointer">
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

<!-- Modal confirmar eliminar -->
{#if delTarget}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 text-center">
      <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="mdi mdi-trash-can-outline text-2xl text-red-500"></i>
      </div>
      <h3 class="font-semibold text-slate-800 mb-2">¿Eliminar categoría?</h3>
      <p class="text-sm text-slate-500 mb-6">
        Se eliminará <strong>{delTarget.name}</strong>. Sus subcategorías subirán al nivel padre.
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
