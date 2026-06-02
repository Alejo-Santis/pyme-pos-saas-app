<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    filters = {},
    summary = {},
    unbalanced = [],
    documents = [],
    cashReceipts = [],
    paymentReceipts = [],
  } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    date_to: filters.date_to ?? new Date().toISOString().slice(0, 10),
  })

  function search() {
    router.get('/accounting/audit', form, { preserveState: true, replace: true })
  }

  function regenerate(row) {
    if (!row.source_type || !row.id) return

    router.post('/accounting/audit/regenerate', {
      source_type: row.source_type,
      source_id: row.id,
    }, { preserveScroll: true })
  }

  const opTypes = {
    1: 'Factura de venta',
    13: 'Recibo de caja',
    14: 'Compra / egreso',
    91: 'Nota crédito',
    92: 'Nota débito',
  }

  const sections = $derived([
    {
      key: 'documents',
      title: 'Documentos sin comprobante',
      icon: 'mdi-file-document-alert-outline',
      count: summary.documents ?? 0,
      rows: documents,
    },
    {
      key: 'cash',
      title: 'Recibos sin asiento',
      icon: 'mdi-cash-plus',
      count: summary.cash_receipts ?? 0,
      rows: cashReceipts,
    },
    {
      key: 'payments',
      title: 'Egresos sin asiento',
      icon: 'mdi-cash-minus',
      count: summary.payment_receipts ?? 0,
      rows: paymentReceipts,
    },
  ])
</script>

<AppLayout>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Auditoría Contable</h1>
        <p class="text-sm text-slate-500 mt-0.5">Pendientes de contabilización y comprobantes descuadrados</p>
      </div>
      <a href="/accounting/journal"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50">
        <i class="mdi mdi-book-open-outline text-base"></i>
        Libro diario
      </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto]">
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
        <div class="flex items-end">
          <button onclick={search}
            class="w-full md:w-auto px-4 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer">
            Filtrar
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500">Descuadrados</p>
        <p class="mt-1 text-2xl font-bold text-rose-700">{summary.unbalanced ?? 0}</p>
      </div>
      {#each sections as section}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
          <p class="text-xs font-medium text-slate-500">{section.title}</p>
          <p class="mt-1 text-2xl font-bold text-slate-800">{section.count}</p>
        </div>
      {/each}
    </div>

    <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3.5">
        <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center">
          <i class="mdi mdi-scale-unbalanced text-rose-600 text-base"></i>
        </div>
        <div>
          <h2 class="text-sm font-semibold text-slate-800">Comprobantes descuadrados</h2>
          <p class="text-xs text-slate-500">Máximo 50 resultados del período</p>
        </div>
      </div>

      {#if unbalanced.length}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
              <tr>
                <th class="px-5 py-2 text-left font-medium">Comprobante</th>
                <th class="px-4 py-2 text-left font-medium">Fecha</th>
                <th class="px-4 py-2 text-right font-medium">Débito</th>
                <th class="px-4 py-2 text-right font-medium">Crédito</th>
                <th class="px-4 py-2 text-right font-medium">Diferencia</th>
                <th class="px-5 py-2 text-right font-medium">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each unbalanced as row}
                <tr class="hover:bg-slate-50/60">
                  <td class="px-5 py-3 font-medium text-slate-700">{row.internal_code}</td>
                  <td class="px-4 py-3 text-slate-500">{row.issue_date}</td>
                  <td class="px-4 py-3 text-right tabular-nums text-blue-700">${fmt(row.debit)}</td>
                  <td class="px-4 py-3 text-right tabular-nums text-emerald-700">${fmt(row.credit)}</td>
                  <td class="px-4 py-3 text-right tabular-nums font-semibold text-rose-700">${fmt(row.difference)}</td>
                  <td class="px-5 py-3 text-right">
                    {#if row.source_id}
                      <button onclick={() => regenerate({ id: row.source_id, source_type: row.source_type })}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                        <i class="mdi mdi-refresh text-sm"></i>
                        Regenerar
                      </button>
                    {:else}
                      <span class="text-xs text-slate-400">Manual</span>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {:else}
        <div class="px-5 py-10 text-center text-sm text-slate-400">Sin comprobantes descuadrados</div>
      {/if}
    </section>

    {#each sections as section}
      <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-3.5">
          <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
            <i class="mdi {section.icon} text-primary text-base"></i>
          </div>
          <div>
            <h2 class="text-sm font-semibold text-slate-800">{section.title}</h2>
            <p class="text-xs text-slate-500">Máximo 50 resultados del período</p>
          </div>
        </div>

        {#if section.rows.length}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                  <th class="px-5 py-2 text-left font-medium">Documento</th>
                  <th class="px-4 py-2 text-left font-medium">Tercero</th>
                  <th class="px-4 py-2 text-left font-medium">Tipo</th>
                  <th class="px-4 py-2 text-left font-medium">Fecha</th>
                  <th class="px-4 py-2 text-right font-medium">Total</th>
                  <th class="px-5 py-2 text-right font-medium">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each section.rows as row}
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-3 font-medium text-slate-700">{row.internal_code}</td>
                    <td class="px-4 py-3 text-slate-500">{row.third_party}</td>
                    <td class="px-4 py-3 text-slate-500">{opTypes[row.type_document_operation_id] ?? `Operación ${row.type_document_operation_id}`}</td>
                    <td class="px-4 py-3 text-slate-500">{row.issue_date}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-700">${fmt(row.total)}</td>
                    <td class="px-5 py-3 text-right">
                      <button onclick={() => regenerate(row)}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                        <i class="mdi mdi-refresh text-sm"></i>
                        Generar
                      </button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {:else}
          <div class="px-5 py-10 text-center text-sm text-slate-400">Sin pendientes</div>
        {/if}
      </section>
    {/each}
  </div>
</AppLayout>
