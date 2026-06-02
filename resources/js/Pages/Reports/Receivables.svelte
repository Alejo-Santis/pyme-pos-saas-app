<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import { router } from '@inertiajs/svelte'

  let {
    documents = { data: [], links: [], meta: {} },
    summary = {},
    customers = [],
    thirdParties = [],
    filters = {},
  } = $props()

  let asOf = $state(filters.as_of ?? new Date().toISOString().slice(0, 10))
  let thirdPartyId = $state(filters.third_party_id ?? '')
  let status = $state(filters.status ?? 'all')
  let search = $state(filters.search ?? '')

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  const num = (n) => Number(n ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 0 })

  const statusLabels = {
    all: 'Todos',
    current: 'Al día',
    due: 'Por vencer',
    overdue: 'Vencidos',
  }

  const docTypes = {
    1: 'Factura',
    92: 'Nota débito',
  }

  const statusClass = {
    current: 'bg-emerald-50 text-emerald-700',
    due: 'bg-amber-50 text-amber-700',
    overdue: 'bg-rose-50 text-rose-700',
  }

  const aging = $derived([
    ['0-30 días', summary.bucket_0_30, 'bg-amber-400'],
    ['31-60 días', summary.bucket_31_60, 'bg-orange-400'],
    ['61-90 días', summary.bucket_61_90, 'bg-rose-400'],
    ['+90 días', summary.bucket_over_90, 'bg-red-600'],
  ])

  const maxBucket = $derived(Math.max(...aging.map((b) => Number(b[1] ?? 0)), 1))

  function apply() {
    router.get('/reports/receivables', {
      as_of: asOf,
      third_party_id: thirdPartyId || undefined,
      status: status === 'all' ? undefined : status,
      search: search || undefined,
    }, { preserveScroll: true, replace: true })
  }

  function clear() {
    asOf = new Date().toISOString().slice(0, 10)
    thirdPartyId = ''
    status = 'all'
    search = ''
    router.get('/reports/receivables', {}, { preserveScroll: true, replace: true })
  }

  function collect(row) {
    const params = new URLSearchParams({
      type: 'cash',
      third_party_id: row.third_party_id,
      document_id: row.id,
      amount: row.balance,
    })

    router.visit(`/cash/receipts?${params.toString()}`)
  }
</script>

<AppLayout>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Cartera Clientes</h1>
        <p class="text-sm text-slate-500 mt-0.5">Facturas y notas débito pendientes por recaudar</p>
      </div>
      <a href="/cash/receipts?type=cash"
        class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
        <i class="mdi mdi-receipt-text-plus-outline text-base"></i>
        Nuevo recibo
      </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-[160px_1fr_180px_1fr_auto_auto] md:items-end">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Corte</label>
          <input type="date" bind:value={asOf}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Cliente</label>
          <select bind:value={thirdPartyId}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Todos</option>
            {#each thirdParties as third}
              <option value={third.id}>{third.name} · {third.identification_number}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
          <select bind:value={status}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            {#each Object.entries(statusLabels) as [key, label]}
              <option value={key}>{label}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <input type="search" bind:value={search} placeholder="Factura, cliente o NIT"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <button onclick={apply}
          class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
          Filtrar
        </button>
        <button onclick={clear}
          class="px-4 py-2 border border-slate-200 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition">
          Limpiar
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Saldo cartera</p>
        <p class="text-2xl font-bold text-slate-800">{fmt(summary.total_balance)}</p>
        <p class="text-xs text-slate-400 mt-1">{num(summary.total_documents)} documentos</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Al día</p>
        <p class="text-2xl font-bold text-emerald-700">{fmt(summary.current)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Por vencer</p>
        <p class="text-2xl font-bold text-amber-600">{fmt(summary.due)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Vencida</p>
        <p class="text-2xl font-bold text-rose-700">{fmt(summary.overdue)}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
      <section class="xl:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Documentos pendientes</h2>
          <span class="text-xs text-slate-400">{documents.meta?.total ?? documents.data.length} registros</span>
        </div>

        {#if documents.data.length === 0}
          <div class="py-16 text-center text-slate-400 text-sm">
            <i class="mdi mdi-account-cash-outline text-4xl block mb-2"></i>
            Sin cartera pendiente con los filtros actuales
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                  <th class="px-4 py-2.5">Documento</th>
                  <th class="px-4 py-2.5">Cliente</th>
                  <th class="px-4 py-2.5">Vence</th>
                  <th class="px-4 py-2.5 text-right">Total</th>
                  <th class="px-4 py-2.5 text-right">Abonado</th>
                  <th class="px-4 py-2.5 text-right">Saldo</th>
                  <th class="px-4 py-2.5 text-right">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each documents.data as row}
                  <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                      <a href={`/invoices/${row.id}`} class="font-semibold text-slate-800 hover:text-blue-700">
                        {row.internal_code}
                      </a>
                      <p class="text-xs text-slate-400">{docTypes[row.type_document_operation_id] ?? 'Documento'} · {row.issue_date}</p>
                    </td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-slate-700">{row.third_party}</p>
                      <p class="text-xs text-slate-400">{row.identification_number ?? 'Sin identificación'}</p>
                    </td>
                    <td class="px-4 py-3">
                      <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {statusClass[row.status]}">
                        {statusLabels[row.status]}
                      </span>
                      <p class="text-xs text-slate-400 mt-1">
                        {row.due_date}
                        {#if row.days_overdue > 0} · {row.days_overdue} días{/if}
                      </p>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-600">{fmt(row.total)}</td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-500">{fmt(row.paid_amount)}</td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold text-slate-800">{fmt(row.balance)}</td>
                    <td class="px-4 py-3 text-right">
                      <button onclick={() => collect(row)}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                        <i class="mdi mdi-cash-plus text-sm"></i>
                        Abonar
                      </button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}

        {#if documents.meta?.last_page > 1}
          <div class="border-t border-slate-100 px-5 py-3 flex flex-wrap justify-center gap-1">
            {#each documents.links as link}
              {#if link.url}
                <button onclick={() => router.visit(link.url)}
                  class="px-3 py-1.5 rounded-lg border text-sm transition
                    {link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50 text-slate-600'}">
                  {@html link.label}
                </button>
              {/if}
            {/each}
          </div>
        {/if}
      </section>

      <aside class="space-y-5">
        <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Edad de cartera</h2>
          </div>
          <div class="p-5 space-y-4">
            {#each aging as bucket}
              <div>
                <div class="flex items-center justify-between text-xs mb-1">
                  <span class="font-medium text-slate-600">{bucket[0]}</span>
                  <span class="tabular-nums text-slate-500">{fmt(bucket[1])}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100">
                  <div class="h-2 rounded-full {bucket[2]}" style="width: {Math.round((Number(bucket[1] ?? 0) / maxBucket) * 100)}%"></div>
                </div>
              </div>
            {/each}
          </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Clientes con mayor saldo</h2>
          </div>
          {#if customers.length === 0}
            <p class="text-center text-sm text-slate-400 py-10">Sin saldos pendientes</p>
          {:else}
            <ul class="divide-y divide-slate-100">
              {#each customers as customer}
                <li class="px-5 py-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold text-slate-700">{customer.name}</p>
                      <p class="text-xs text-slate-400">{customer.documents} docs · mora máx. {customer.max_days_overdue} días</p>
                    </div>
                    <p class="text-sm font-bold text-slate-800 tabular-nums">{fmt(customer.balance)}</p>
                  </div>
                  {#if customer.credit_usage !== null}
                    <div class="mt-2">
                      <div class="flex justify-between text-[11px] text-slate-400 mb-1">
                        <span>Cupo usado</span>
                        <span>{customer.credit_usage}%</span>
                      </div>
                      <div class="h-1.5 rounded-full bg-slate-100">
                        <div class="h-1.5 rounded-full bg-blue-500" style="width: {Math.min(customer.credit_usage, 100)}%"></div>
                      </div>
                    </div>
                  {/if}
                </li>
              {/each}
            </ul>
          {/if}
        </section>
      </aside>
    </div>
  </div>
</AppLayout>
