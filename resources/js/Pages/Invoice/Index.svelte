<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { documents, filters = {}, documentTypes = [] } = $props()

  let search      = $state(filters.search                    ?? '')
  let type        = $state(filters.type_document_operation_id ?? '')
  let status      = $state(filters.status                    ?? '')
  let date_from   = $state(filters.date_from                 ?? '')
  let date_to     = $state(filters.date_to                   ?? '')

  let searchTimeout

  function applyFilters() {
    router.get('/invoices', {
      search, type_document_operation_id: type, status, date_from, date_to,
    }, { preserveScroll: true, replace: true })
  }

  function onSearch() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 400)
  }

  function deleteDoc(id) {
    if (!confirm('¿Eliminar este documento? Solo se pueden eliminar borradores.')) return
    router.delete(`/invoices/${id}`, { preserveScroll: true })
  }

  const typeMap = Object.fromEntries(documentTypes.map(t => [t.id, t.name]))

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Facturación</h1>
        <p class="text-sm text-slate-500 mt-0.5">Documentos electrónicos DIAN</p>
      </div>
      <a use:inertia href="/invoices/create"
        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
        <i class="mdi mdi-plus text-base"></i>
        Nueva factura
      </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
      <div class="flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-52">
          <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <input bind:value={search} oninput={onSearch} type="text"
            placeholder="Buscar por número o tercero…"
            class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <select bind:value={type} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-44">
          <option value="">Todos los tipos</option>
          {#each documentTypes as dt}
            <option value={dt.id}>{dt.name}</option>
          {/each}
        </select>

        <select bind:value={status} onchange={applyFilters}
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary min-w-36">
          <option value="">Todos los estados</option>
          <option value="draft">Borrador</option>
          <option value="electronic">Enviado DIAN</option>
          <option value="paid">Pagado</option>
          <option value="pending">Pendiente pago</option>
        </select>

        <input bind:value={date_from} onchange={applyFilters} type="date"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        <input bind:value={date_to} onchange={applyFilters} type="date"
          class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
      {#if documents.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
          <i class="mdi mdi-file-document-outline text-4xl mb-3"></i>
          <p class="text-sm">No se encontraron documentos</p>
          <a use:inertia href="/invoices/create" class="mt-3 text-sm text-primary hover:underline">
            Crear la primera factura
          </a>
        </div>
      {:else}
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Documento</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Tipo</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Tercero</th>
              <th class="text-left px-4 py-3 font-semibold text-slate-600">Fecha</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Total</th>
              <th class="text-center px-4 py-3 font-semibold text-slate-600">Estado</th>
              <th class="text-right px-4 py-3 font-semibold text-slate-600">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each documents.data as doc}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3">
                  <a use:inertia href="/invoices/{doc.id}"
                    class="font-mono font-semibold text-primary hover:underline text-sm">
                    {doc.prefix ?? ''}{doc.number ?? '—'}
                  </a>
                  {#if doc.internal_code}
                    <p class="text-xs text-slate-400">{doc.internal_code}</p>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600 text-xs">
                  {typeMap[doc.type_document_operation_id] ?? '—'}
                </td>
                <td class="px-4 py-3">
                  {#if doc.third_party}
                    <p class="font-medium text-slate-700 text-sm">{doc.third_party.name}</p>
                    <p class="text-xs text-slate-400">{doc.third_party.identification_number}</p>
                  {:else}
                    <span class="text-slate-400">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-600 text-sm">{doc.issue_date ?? '—'}</td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800">
                  ${fmt(doc.total)}
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex flex-col items-center gap-1">
                    {#if doc.annulled}
                      <span class="text-xs bg-red-50 text-red-700 border border-red-200 rounded-full px-2 py-0.5 font-medium">Anulado</span>
                    {:else if doc.electronic}
                      <span class="text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5 font-medium">
                        <i class="mdi mdi-check-circle text-xs"></i> DIAN
                      </span>
                    {:else}
                      <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 rounded-full px-2 py-0.5 font-medium">Borrador</span>
                    {/if}
                    {#if doc.paid}
                      <span class="text-xs bg-blue-50 text-blue-700 border border-blue-200 rounded-full px-2 py-0.5 font-medium">Pagado</span>
                    {/if}
                  </div>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a use:inertia href="/invoices/{doc.id}"
                      class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition"
                      title="Ver">
                      <i class="mdi mdi-eye-outline text-base"></i>
                    </a>
                    {#if !doc.electronic && !doc.annulled}
                      <a use:inertia href="/invoices/{doc.id}/edit"
                        class="p-1.5 text-slate-400 hover:text-primary hover:bg-primary/5 rounded transition"
                        title="Editar">
                        <i class="mdi mdi-pencil-outline text-base"></i>
                      </a>
                      <button onclick={() => deleteDoc(doc.id)}
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

        <!-- Paginación -->
        {#if documents.last_page > 1}
          <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50">
            <p class="text-xs text-slate-500">
              Mostrando {documents.from}–{documents.to} de {documents.total} documentos
            </p>
            <div class="flex gap-1">
              {#each documents.links as link}
                {#if link.url}
                  <a use:inertia href={link.url}
                    class="px-3 py-1.5 text-xs rounded border transition
                      {link.active ? 'bg-primary text-white border-primary' : 'bg-white text-slate-600 border-slate-200 hover:border-primary hover:text-primary'}">
                    {@html link.label}
                  </a>
                {:else}
                  <span class="px-3 py-1.5 text-xs rounded border border-slate-200 text-slate-300">{@html link.label}</span>
                {/if}
              {/each}
            </div>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</AppLayout>
