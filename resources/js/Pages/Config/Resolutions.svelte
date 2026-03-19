<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import { router, page } from '@inertiajs/svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let { resolutions, company, documentTypes } = $props()

  let flash  = $derived($page.props.flash  ?? {})
  let errors = $derived($page.props.errors ?? {})

  // Modal crear/editar
  let showModal  = $state(false)
  let editTarget = $state(null)   // null = crear, objeto = editar

  const emptyForm = () => ({
    type_document_id:           '',
    type_document_operation_id: '',
    resolution:                 '',
    resolution_date:            '',
    prefix:                     '',
    from:                       1,
    to:                         '',
    date_from:                  '',
    date_to:                    '',
    is_active:                  true,
  })

  let form = $state(emptyForm())

  function openCreate() {
    editTarget = null
    form = emptyForm()
    showModal = true
  }

  function openEdit(r) {
    editTarget = r
    form = {
      type_document_id:           r.type_document_id,
      type_document_operation_id: r.type_document_operation_id,
      resolution:                 r.resolution,
      resolution_date:            r.resolution_date,
      prefix:                     r.prefix ?? '',
      from:                       r.from,
      to:                         r.to,
      date_from:                  r.date_from,
      date_to:                    r.date_to,
      is_active:                  r.is_active,
    }
    showModal = true
  }

  function closeModal() { showModal = false }

  function syncDocType() {
    const found = documentTypes.find(d => d.id == form.type_document_id)
    if (found) form.type_document_operation_id = found.operation_id
  }

  let saving = $state(false)
  function save() {
    saving = true
    if (editTarget) {
      router.put(`/config/resolutions/${editTarget.id}`, form, {
        preserveScroll: true,
        onSuccess: () => { showModal = false },
        onFinish:  () => { saving = false },
      })
    } else {
      router.post('/config/resolutions', form, {
        preserveScroll: true,
        onSuccess: () => { showModal = false; form = emptyForm() },
        onFinish:  () => { saving = false },
      })
    }
  }

  function toggleActive(r) {
    router.patch(`/config/resolutions/${r.id}/toggle`, {}, { preserveScroll: true })
  }

  let confirmDelete = $state({ open: false, resolution: null })

  function remove(r) {
    confirmDelete = { open: true, resolution: r }
  }

  const typeLabel = (opId) => documentTypes.find(d => d.operation_id == opId)?.name ?? `Tipo ${opId}`

  function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('es-CO')
  }

  const usedPct = (r) => r.to > r.from
    ? Math.round(((r.current_number - r.from + 1) / (r.to - r.from + 1)) * 100)
    : 0
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Resoluciones DIAN</h1>
        <p class="text-sm text-slate-500 mt-0.5">Numeración autorizada por la DIAN para cada tipo de documento</p>
      </div>
      <button
        onclick={openCreate}
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer"
      >
        <i class="mdi mdi-plus"></i> Nueva Resolución
      </button>
    </div>

    <!-- Flash -->
    {#if flash.success}
      <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        <i class="mdi mdi-check-circle-outline"></i> {flash.success}
      </div>
    {/if}

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      {#if resolutions.length === 0}
        <div class="text-center py-16">
          <i class="mdi mdi-file-certificate-outline text-5xl text-slate-300 block mb-3"></i>
          <p class="text-slate-500 text-sm">No hay resoluciones registradas.</p>
          <button onclick={openCreate} class="mt-3 text-blue-600 text-sm underline cursor-pointer">Agregar la primera</button>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
              <th class="px-4 py-3">Resolución</th>
              <th class="px-4 py-3">Tipo</th>
              <th class="px-4 py-3">Prefijo</th>
              <th class="px-4 py-3">Rango</th>
              <th class="px-4 py-3">Progreso</th>
              <th class="px-4 py-3">Vigencia</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each resolutions as r}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 font-mono font-medium text-slate-800">{r.resolution}</td>
                <td class="px-4 py-3 text-slate-600">{typeLabel(r.type_document_operation_id)}</td>
                <td class="px-4 py-3">
                  {#if r.prefix}
                    <span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs font-mono font-medium">{r.prefix}</span>
                  {:else}
                    <span class="text-slate-400">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600 font-mono text-xs">
                  {r.from} – {r.to}
                  <span class="text-slate-400 ml-1">(actual: {r.current_number})</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                      <div
                        class="h-full rounded-full {usedPct(r) > 80 ? 'bg-red-500' : usedPct(r) > 50 ? 'bg-amber-500' : 'bg-green-500'}"
                        style="width: {usedPct(r)}%"
                      ></div>
                    </div>
                    <span class="text-xs text-slate-500">{usedPct(r)}%</span>
                  </div>
                </td>
                <td class="px-4 py-3 text-xs text-slate-500">
                  {formatDate(r.date_from)} – {formatDate(r.date_to)}
                </td>
                <td class="px-4 py-3">
                  <button
                    onclick={() => toggleActive(r)}
                    class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium cursor-pointer
                      {r.is_active
                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                        : 'bg-slate-100 text-slate-500 hover:bg-slate-200'}"
                  >
                    <i class="mdi {r.is_active ? 'mdi-check-circle' : 'mdi-circle-outline'} text-sm"></i>
                    {r.is_active ? 'Activa' : 'Inactiva'}
                  </button>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button onclick={() => openEdit(r)} class="text-slate-400 hover:text-blue-600 transition cursor-pointer" title="Editar">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </button>
                    <button onclick={() => remove(r)} class="text-slate-400 hover:text-red-500 transition cursor-pointer" title="Eliminar">
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

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar resolución"
  message={confirmDelete.resolution ? `¿Eliminar la resolución "${confirmDelete.resolution.resolution}"?` : ''}
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete(`/config/resolutions/${confirmDelete.resolution.id}`, { preserveScroll: true })}
/>

<!-- ── Modal crear / editar ──────────────────────────────────────────── -->
{#if showModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick={closeModal}></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h2 class="text-base font-semibold text-slate-800">
          {editTarget ? 'Editar Resolución' : 'Nueva Resolución'}
        </h2>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
          <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de Documento *</label>
          <select
            bind:value={form.type_document_id}
            onchange={syncDocType}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
          >
            <option value="">Seleccionar…</option>
            {#each documentTypes as dt}
              <option value={dt.id}>{dt.name}</option>
            {/each}
          </select>
          {#if errors.type_document_id}<p class="text-red-500 text-xs mt-1">{errors.type_document_id}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Número de Resolución *</label>
          <input bind:value={form.resolution} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono" placeholder="18764000001234" />
          {#if errors.resolution}<p class="text-red-500 text-xs mt-1">{errors.resolution}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de Resolución *</label>
          <input bind:value={form.resolution_date} type="date" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
          {#if errors.resolution_date}<p class="text-red-500 text-xs mt-1">{errors.resolution_date}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Prefijo</label>
          <input bind:value={form.prefix} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono uppercase" placeholder="FEV" maxlength="10" />
          <p class="text-xs text-slate-400 mt-0.5">Dejar vacío si no tiene prefijo</p>
        </div>

        <div></div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Número Inicial (Desde) *</label>
          <input bind:value={form.from} type="number" min="1" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
          {#if errors.from}<p class="text-red-500 text-xs mt-1">{errors.from}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Número Final (Hasta) *</label>
          <input bind:value={form.to} type="number" min="1" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
          {#if errors.to}<p class="text-red-500 text-xs mt-1">{errors.to}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Vigencia Desde *</label>
          <input bind:value={form.date_from} type="date" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
          {#if errors.date_from}<p class="text-red-500 text-xs mt-1">{errors.date_from}</p>{/if}
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Vigencia Hasta *</label>
          <input bind:value={form.date_to} type="date" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" />
          {#if errors.date_to}<p class="text-red-500 text-xs mt-1">{errors.date_to}</p>{/if}
        </div>

        <div class="md:col-span-2">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" bind:checked={form.is_active} class="w-4 h-4 text-blue-600 rounded border-slate-300" />
            <span class="text-sm text-slate-700 font-medium">Resolución activa</span>
          </label>
        </div>

      </div>

      <div class="flex justify-end gap-3 px-6 py-4 border-t border-slate-200">
        <button onclick={closeModal} class="px-4 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          Cancelar
        </button>
        <button
          onclick={save}
          disabled={saving}
          class="flex items-center gap-2 px-5 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60 transition cursor-pointer"
        >
          <i class="mdi {saving ? 'mdi-loading mdi-spin' : 'mdi-content-save-outline'}"></i>
          {saving ? 'Guardando…' : 'Guardar'}
        </button>
      </div>

    </div>
  </div>
{/if}
