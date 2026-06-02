<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    movements = { data: [], links: [], meta: {} },
    summary = {},
    byDocument = [],
    commercialReconciliation = [],
    thirdParties = [],
    accounts = [],
    filters = {},
  } = $props()

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    date_to: filters.date_to ?? new Date().toISOString().slice(0, 10),
    third_party_id: filters.third_party_id ?? '',
    account_code: filters.account_code ?? '',
    document_number: filters.document_number ?? '',
  })

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  const num = (n) => Number(n ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 0 })

  const opTypes = {
    1: 'Factura',
    5: 'Documento soporte',
    13: 'Recibo de caja',
    14: 'Compra / egreso',
    91: 'Nota crédito',
    92: 'Nota débito',
  }

  const sideLabels = {
    receivable: 'Cliente',
    payable: 'Proveedor',
  }

  const movementsWithBalance = $derived(() => {
    let balance = 0
    return movements.data.map((row) => {
      balance += Number(row.debit ?? 0) - Number(row.credit ?? 0)
      return { ...row, running_balance: balance }
    })
  })

  function search() {
    router.get('/accounting/auxiliary', form, { preserveState: true, replace: true })
  }

  function clear() {
    form = {
      date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
      date_to: new Date().toISOString().slice(0, 10),
      third_party_id: '',
      account_code: '',
      document_number: '',
    }
    router.get('/accounting/auxiliary', {}, { preserveState: true, replace: true })
  }
</script>

<AppLayout>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Auxiliar por Tercero</h1>
        <p class="text-sm text-slate-500 mt-0.5">Movimientos contables por cuenta, tercero y documento</p>
      </div>
      <a href="/accounting/ledger"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50">
        <i class="mdi mdi-book-multiple-outline text-base"></i>
        Libro mayor
      </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="grid grid-cols-1 gap-3 lg:grid-cols-[150px_150px_1fr_1fr_1fr_auto_auto] lg:items-end">
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Desde</label>
          <input type="date" bind:value={form.date_from}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Hasta</label>
          <input type="date" bind:value={form.date_to}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Tercero</label>
          <select bind:value={form.third_party_id}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
            <option value="">Todos</option>
            {#each thirdParties as third}
              <option value={third.id}>{third.name} · {third.identification_number}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Cuenta</label>
          <select bind:value={form.account_code}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
            <option value="">Todas</option>
            {#each accounts as acc}
              <option value={acc.code}>{acc.code} · {acc.name}</option>
            {/each}
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-500 mb-1">Documento</label>
          <input type="search" bind:value={form.document_number} placeholder="FV-0001, RC-..."
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <button onclick={search}
          class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark">
          Filtrar
        </button>
        <button onclick={clear}
          class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
          Limpiar
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Líneas</p>
        <p class="text-2xl font-bold text-slate-800">{num(summary.lines)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Débitos</p>
        <p class="text-2xl font-bold text-blue-700">{fmt(summary.debit)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Créditos</p>
        <p class="text-2xl font-bold text-emerald-700">{fmt(summary.credit)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Saldo neto</p>
        <p class="text-2xl font-bold {Number(summary.balance) >= 0 ? 'text-slate-800' : 'text-rose-700'}">{fmt(summary.balance)}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
      <section class="xl:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-slate-700">Movimientos</h2>
          <span class="text-xs text-slate-400">{movements.meta?.total ?? movements.data.length} registros</span>
        </div>

        {#if movements.data.length === 0}
          <div class="py-16 text-center text-slate-400 text-sm">
            <i class="mdi mdi-file-tree-outline text-4xl block mb-2"></i>
            Sin movimientos con los filtros actuales
          </div>
        {:else}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                  <th class="px-4 py-2.5">Fecha</th>
                  <th class="px-4 py-2.5">Cuenta</th>
                  <th class="px-4 py-2.5">Tercero / Doc.</th>
                  <th class="px-4 py-2.5">Comprobante</th>
                  <th class="px-4 py-2.5 text-right">Débito</th>
                  <th class="px-4 py-2.5 text-right">Crédito</th>
                  <th class="px-4 py-2.5 text-right">Saldo acum.</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each movementsWithBalance() as row}
                  <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-500 tabular-nums">{row.issue_date}</td>
                    <td class="px-4 py-3">
                      <p class="font-mono text-xs font-semibold text-slate-700">{row.account_code}</p>
                      <p class="text-xs text-slate-400 max-w-[180px] truncate">{row.account_name}</p>
                    </td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-slate-700 max-w-[220px] truncate">{row.third_party}</p>
                      <p class="text-xs text-slate-400">{row.document_number ?? 'Sin documento'}</p>
                    </td>
                    <td class="px-4 py-3">
                      <p class="font-medium text-slate-700">{row.voucher_code}</p>
                      <p class="text-xs text-slate-400">{opTypes[row.type_document_operation_id] ?? `Operación ${row.type_document_operation_id}`}</p>
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums {row.debit > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'}">
                      {row.debit > 0 ? fmt(row.debit) : '—'}
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums {row.credit > 0 ? 'text-emerald-700 font-medium' : 'text-slate-300'}">
                      {row.credit > 0 ? fmt(row.credit) : '—'}
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums font-semibold {row.running_balance >= 0 ? 'text-slate-800' : 'text-rose-700'}">
                      {fmt(row.running_balance)}
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        {/if}

        {#if movements.meta?.last_page > 1}
          <div class="border-t border-slate-100 px-5 py-3 flex flex-wrap justify-center gap-1">
            {#each movements.links as link}
              {#if link.url}
                <button onclick={() => router.visit(link.url)}
                  class="px-3 py-1.5 rounded-lg border text-sm transition
                    {link.active ? 'bg-primary text-white border-primary' : 'border-slate-200 hover:bg-slate-50 text-slate-600'}">
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
            <h2 class="text-sm font-semibold text-slate-700">Resumen por documento</h2>
          </div>
          {#if byDocument.length === 0}
            <p class="text-center text-sm text-slate-400 py-10">Sin documentos</p>
          {:else}
            <ul class="divide-y divide-slate-100">
              {#each byDocument as row}
                <li class="px-5 py-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold text-slate-700">{row.document_number ?? 'Sin documento'}</p>
                      <p class="text-xs text-slate-400 truncate">{row.third_party}</p>
                    </div>
                    <p class="text-sm font-bold tabular-nums {Number(row.balance) >= 0 ? 'text-slate-800' : 'text-rose-700'}">{fmt(row.balance)}</p>
                  </div>
                  <p class="mt-1 text-xs text-slate-400">{row.lines} líneas · Db {fmt(row.debit)} · Cr {fmt(row.credit)}</p>
                </li>
              {/each}
            </ul>
          {/if}
        </section>

        <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-slate-100">
            <h2 class="text-sm font-semibold text-slate-700">Conciliación comercial</h2>
          </div>
          {#if commercialReconciliation.length === 0}
            <p class="text-center text-sm text-slate-400 py-10">Sin saldos comerciales pendientes</p>
          {:else}
            <ul class="divide-y divide-slate-100">
              {#each commercialReconciliation as row}
                <li class="px-5 py-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <a href={`/invoices/${row.id}`} class="truncate text-sm font-semibold text-slate-700 hover:text-blue-700">{row.internal_code}</a>
                      <p class="text-xs text-slate-400 truncate">{sideLabels[row.side]} · {row.third_party}</p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {Math.abs(Number(row.difference)) < 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}">
                      {Math.abs(Number(row.difference)) < 1 ? 'Cuadra' : 'Diferencia'}
                    </span>
                  </div>
                  <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                    <div>
                      <p class="text-slate-400">Comercial</p>
                      <p class="font-semibold text-slate-700 tabular-nums">{fmt(row.commercial_balance)}</p>
                    </div>
                    <div>
                      <p class="text-slate-400">Contable</p>
                      <p class="font-semibold text-slate-700 tabular-nums">{fmt(row.accounting_balance)}</p>
                    </div>
                    <div>
                      <p class="text-slate-400">Dif.</p>
                      <p class="font-semibold tabular-nums {Math.abs(Number(row.difference)) < 1 ? 'text-emerald-700' : 'text-rose-700'}">{fmt(row.difference)}</p>
                    </div>
                  </div>
                </li>
              {/each}
            </ul>
          {/if}
        </section>
      </aside>
    </div>
  </div>
</AppLayout>
