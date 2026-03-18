<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'

  let { vouchers = { data: [], links: [], meta: {} }, totals = {}, filters = {} } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10),
    date_to:   filters.date_to   ?? new Date().toISOString().slice(0,10),
    type:      filters.type      ?? '',
  })

  function search() {
    router.get('/accounting/journal', form, { preserveState: true, replace: true })
  }

  const opTypes = {
    '1': 'Factura Venta', '14': 'Compra', '91': 'Nota Crédito', '92': 'Nota Débito',
  }

  let expanded = $state({})
  function toggle(id) { expanded = { ...expanded, [id]: !expanded[id] } }
</script>

<AppLayout>
  <div class="space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Libro Diario</h1>
        <p class="text-sm text-slate-500 mt-0.5">Comprobantes contables generados automáticamente</p>
      </div>
      <ExportButtons baseUrl="/accounting/journal/export" params={{ date_from: form.date_from, date_to: form.date_to, type: form.type }} />
    </div>

    <!-- Filtros + Totales -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div>
          <label class="text-xs font-medium text-slate-500 block mb-1">Desde</label>
          <input type="date" bind:value={form.date_from}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 block mb-1">Hasta</label>
          <input type="date" bind:value={form.date_to}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
        </div>
        <div class="flex items-end gap-2">
          <select bind:value={form.type}
            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30">
            <option value="">Todos los tipos</option>
            {#each Object.entries(opTypes) as [k, v]}
              <option value={k}>{v}</option>
            {/each}
          </select>
          <button onclick={search}
            class="px-4 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer">
            Filtrar
          </button>
        </div>
      </div>

      <!-- Totales del período -->
      <div class="grid grid-cols-3 gap-4 pt-3 border-t border-slate-100">
        <div class="text-center">
          <p class="text-xs text-slate-500">Comprobantes</p>
          <p class="text-lg font-bold text-slate-800">{totals?.total_vouchers ?? 0}</p>
        </div>
        <div class="text-center">
          <p class="text-xs text-slate-500">Total Débitos</p>
          <p class="text-lg font-bold text-blue-700">${fmt(totals?.total_debit)}</p>
        </div>
        <div class="text-center">
          <p class="text-xs text-slate-500">Total Créditos</p>
          <p class="text-lg font-bold text-emerald-700">${fmt(totals?.total_credit)}</p>
        </div>
      </div>
    </div>

    <!-- Lista de comprobantes -->
    <div class="space-y-2">
      {#each vouchers.data as v}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <!-- Cabecera del comprobante -->
          <button onclick={() => toggle(v.id)}
            class="w-full flex items-center justify-between px-5 py-3.5 hover:bg-slate-50 transition cursor-pointer text-left">
            <div class="flex items-center gap-4">
              <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                <i class="mdi mdi-file-document-outline text-primary text-sm"></i>
              </div>
              <div>
                <p class="font-semibold text-slate-800 text-sm">{v.internal_code ?? 'COMP-' + v.id.slice(0,8)}</p>
                <p class="text-xs text-slate-500">
                  {v.issue_date} ·
                  {opTypes[String(v.type_document_operation_id)] ?? 'Operación ' + v.type_document_operation_id}
                  {#if v.document}· Ref: {v.document.internal_code ?? v.document.number}{/if}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-6 text-sm">
              <div class="text-right">
                <p class="text-xs text-slate-400">Débito</p>
                <p class="font-semibold text-blue-700 tabular-nums">${fmt(v.debit)}</p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-400">Crédito</p>
                <p class="font-semibold text-emerald-700 tabular-nums">${fmt(v.credit)}</p>
              </div>
              <i class="mdi text-slate-400 text-lg {expanded[v.id] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
            </div>
          </button>

          <!-- Líneas del comprobante (expandible) -->
          {#if expanded[v.id]}
            <div class="border-t border-slate-100">
              <table class="w-full text-xs">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="text-left px-5 py-2 font-medium text-slate-500">Cuenta PUC</th>
                    <th class="text-right px-4 py-2 font-medium text-slate-500 w-32">Débito</th>
                    <th class="text-right px-5 py-2 font-medium text-slate-500 w-32">Crédito</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  {#each v.lines ?? [] as line}
                    <tr class="hover:bg-slate-50/50">
                      <td class="px-5 py-2.5">
                        <span class="font-mono text-slate-600 mr-2">{line.accountable_id}</span>
                        <span class="text-slate-500">{line.document_number ?? ''}</span>
                      </td>
                      <td class="px-4 py-2.5 text-right tabular-nums {line.debit > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'}">
                        {line.debit > 0 ? '$' + fmt(line.debit) : '—'}
                      </td>
                      <td class="px-5 py-2.5 text-right tabular-nums {line.credit > 0 ? 'text-emerald-700 font-medium' : 'text-slate-300'}">
                        {line.credit > 0 ? '$' + fmt(line.credit) : '—'}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>
      {/each}

      {#if vouchers.data.length === 0}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-16 text-center">
          <i class="mdi mdi-book-open-outline text-5xl text-slate-200 block mb-3"></i>
          <p class="text-slate-400 text-sm">Sin comprobantes en el período seleccionado</p>
        </div>
      {/if}
    </div>

    <!-- Paginación -->
    {#if vouchers.meta?.last_page > 1}
      <div class="flex justify-center gap-1">
        {#each vouchers.links as link}
          {#if link.url}
            <button onclick={() => router.visit(link.url)}
              class="px-3 py-1.5 rounded-lg border text-sm transition cursor-pointer
                {link.active ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:bg-slate-50 text-slate-600'}">
              {@html link.label}
            </button>
          {/if}
        {/each}
      </div>
    {/if}

  </div>
</AppLayout>
