<script>
  import { page, router, useForm } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { mailbox, filters } = $props()

  let search = $state(filters.search ?? '')
  let statusFilter = $state(filters.status ?? '')
  let showUpload = $state(false)

  const form = useForm({ file: null })

  const statuses = [
    { value: '',          label: 'Todos' },
    { value: 'pending',   label: 'Pendientes' },
    { value: 'processed', label: 'Procesados' },
  ]

  function applyFilters() {
    router.get('/tax-mailbox', { search, status: statusFilter }, { preserveState: true, replace: true })
  }

  function clearFilters() {
    search = ''
    statusFilter = ''
    router.get('/tax-mailbox', {}, { replace: true })
  }

  function onFileChange(e) {
    form.file = e.target.files[0] ?? null
  }

  function upload() {
    form.post('/tax-mailbox', {
      forceFormData: true,
      onSuccess: () => { showUpload = false; form.reset() },
    })
  }

  function destroy(item) {
    if (!confirm(`¿Eliminar el documento de "${item.business_name_provider ?? item.subject ?? 'proveedor sin nombre'}" del buzón?`)) return
    router.delete(`/tax-mailbox/${item.id}`)
  }

  function fmt(n) {
    if (n === null || n === undefined) return '—'
    return Number(n).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
  }
</script>

<AppLayout title="Buzón Tributario">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Buzón Tributario</h1>
      <p class="text-sm text-slate-500 mt-0.5">Documentos electrónicos recibidos de proveedores</p>
    </div>
    <button onclick={() => showUpload = true}
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
      <i class="mdi mdi-upload"></i>
      Cargar XML
    </button>
  </div>

  <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3 mb-6 flex gap-2">
    <i class="mdi mdi-information-outline text-base mt-0.5"></i>
    <span>
      La recepción automática desde la DIAN aún no está activa en este piloto. Mientras tanto puedes
      cargar aquí el XML UBL que te llegue por correo de tus proveedores para tenerlo centralizado.
    </span>
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-48">
      <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
      <input bind:value={search}
             onkeydown={(e) => e.key === 'Enter' && applyFilters()}
             type="text" placeholder="Proveedor, NIT o CUFE..."
             class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
    </div>
    <div class="min-w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
      <select bind:value={statusFilter}
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        {#each statuses as s}
          <option value={s.value}>{s.label}</option>
        {/each}
      </select>
    </div>
    <button onclick={applyFilters}
            class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg transition-colors">
      <i class="mdi mdi-magnify mr-1"></i> Buscar
    </button>
    {#if search || statusFilter}
      <button onclick={clearFilters}
              class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
        Limpiar
      </button>
    {/if}
  </div>

  <!-- Tabla -->
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Proveedor</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">NIT</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Fecha</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Total</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Estado</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        {#each mailbox.data as item}
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3 font-medium text-slate-800">{item.business_name_provider ?? item.subject ?? 'Sin nombre'}</td>
            <td class="px-4 py-3 text-slate-600">{item.identification_number_provider ?? '—'}</td>
            <td class="px-4 py-3 text-slate-600">{fmtDate(item.date)}</td>
            <td class="px-4 py-3 text-right font-medium text-slate-800">{fmt(item.tax_inclusive_amount)}</td>
            <td class="px-4 py-3 text-center">
              {#if item.document_id}
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Procesado</span>
              {:else}
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pendiente</span>
              {/if}
            </td>
            <td class="px-4 py-3 text-center space-x-3">
              <a href="/tax-mailbox/{item.id}" class="text-blue-600 hover:text-blue-800 font-medium">Ver</a>
              {#if item.xml_file_name}
                <a href="/tax-mailbox/{item.id}/download" class="text-slate-500 hover:text-slate-700 font-medium">Descargar</a>
              {/if}
              {#if !item.document_id}
                <button onclick={() => destroy(item)} class="text-red-600 hover:text-red-800 font-medium">Eliminar</button>
              {/if}
            </td>
          </tr>
        {:else}
          <tr>
            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
              <i class="mdi mdi-email-outline text-4xl block mb-2 opacity-50"></i>
              El buzón tributario está vacío
            </td>
          </tr>
        {/each}
      </tbody>
    </table>

    {#if mailbox.last_page > 1}
      <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Mostrando {mailbox.from}–{mailbox.to} de {mailbox.total}</span>
        <div class="flex gap-1">
          {#each mailbox.links as link}
            {#if link.url}
              <button onclick={() => router.get(link.url)}
                      class="px-3 py-1 rounded {link.active ? 'bg-blue-600 text-white' : 'hover:bg-slate-100'}">
                {@html link.label}
              </button>
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </div>

  {#if showUpload}
    <div class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50" onclick={() => showUpload = false}>
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6" onclick={(e) => e.stopPropagation()}>
        <h2 class="text-lg font-bold text-slate-800 mb-1">Cargar XML del buzón</h2>
        <p class="text-sm text-slate-500 mb-4">Sube el archivo XML UBL 2.1 de la factura recibida.</p>

        <input type="file" accept=".xml" onchange={onFileChange}
               class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm mb-2"/>
        {#if $form.errors.file}
          <p class="text-sm text-red-600 mb-2">{$form.errors.file}</p>
        {/if}

        <div class="flex justify-end gap-2 mt-4">
          <button onclick={() => showUpload = false} class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={upload} disabled={$form.processing || !$form.file}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            {$form.processing ? 'Cargando...' : 'Cargar'}
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
