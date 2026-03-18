<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { movements = [], accountDetail = null, accounts = [], filters = {} } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  let form = $state({
    date_from:    filters.date_from    ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0,10),
    date_to:      filters.date_to      ?? new Date().toISOString().slice(0,10),
    account_code: filters.account_code ?? '',
  })

  function search() {
    router.get('/accounting/ledger', form, { preserveState: true, replace: true })
  }

  // Saldo acumulado en el detalle
  const detailWithBalance = $derived(() => {
    if (!accountDetail) return []
    let acc = 0
    return accountDetail.map(row => {
      acc += Number(row.debit) - Number(row.credit)
      return { ...row, running_balance: acc }
    })
  })

  const classColors = {
    1: 'text-blue-700', 2: 'text-red-600', 3: 'text-purple-700',
    4: 'text-emerald-700', 5: 'text-orange-600', 6: 'text-amber-700',
  }
</script>

<AppLayout>
  <div class="space-y-5">

    <div>
      <h1 class="text-xl font-bold text-slate-800">Libro Mayor</h1>
      <p class="text-sm text-slate-500 mt-0.5">Movimientos y saldos por cuenta contable</p>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="text-xs font-medium text-slate-500 block mb-1">Desde</label>
          <input type="date" bind:value={form.date_from}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 block mb-1">Hasta</label>
          <input type="date" bind:value={form.date_to}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <div>
          <label class="text-xs font-medium text-slate-500 block mb-1">Cuenta (ver detalle)</label>
          <select bind:value={form.account_code}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
            <option value="">Todas las cuentas</option>
            {#each accounts as acc}
              <option value={acc.code}>{acc.code} — {acc.name}</option>
            {/each}
          </select>
        </div>
        <div class="flex items-end">
          <button onclick={search}
            class="w-full px-4 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer">
            Filtrar
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla resumen de cuentas -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700">Resumen por cuenta</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Código</th>
              <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Cuenta</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Comprobantes</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-32">Total Débito</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-32">Total Crédito</th>
              <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500 w-32">Saldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each movements as row}
              {@const color = classColors[row.class] ?? 'text-slate-700'}
              <tr class="hover:bg-slate-50/50 transition {form.account_code === row.code ? 'bg-primary/5' : ''}">
                <td class="px-5 py-3">
                  <button onclick={() => { form.account_code = row.code; search() }}
                    class="font-mono text-xs text-primary hover:underline cursor-pointer">
                    {row.code}
                  </button>
                </td>
                <td class="px-4 py-3 text-slate-700 text-sm">{row.name}</td>
                <td class="px-4 py-3 text-right text-slate-500 tabular-nums text-xs">{row.voucher_count}</td>
                <td class="px-4 py-3 text-right tabular-nums text-blue-700 font-medium">${fmt(row.total_debit)}</td>
                <td class="px-4 py-3 text-right tabular-nums text-emerald-700 font-medium">${fmt(row.total_credit)}</td>
                <td class="px-5 py-3 text-right tabular-nums font-bold {color}">
                  {Number(row.balance) >= 0 ? '' : '-'}${fmt(Math.abs(row.balance))}
                </td>
              </tr>
            {/each}
            {#if movements.length === 0}
              <tr>
                <td colspan="6" class="py-12 text-center text-sm text-slate-400">
                  Sin movimientos en el período seleccionado
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>
    </div>

    <!-- Detalle de cuenta seleccionada -->
    {#if accountDetail && form.account_code}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 bg-indigo-50 border-b border-indigo-100">
          <h2 class="text-sm font-semibold text-indigo-800">
            Detalle cuenta: <span class="font-mono">{form.account_code}</span>
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr>
                <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Fecha</th>
                <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Comprobante</th>
                <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Referencia</th>
                <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Débito</th>
                <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Crédito</th>
                <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500 w-32">Saldo acum.</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each detailWithBalance() as row}
                <tr class="hover:bg-slate-50/50">
                  <td class="px-5 py-2.5 text-slate-500 text-xs tabular-nums">{row.issue_date}</td>
                  <td class="px-4 py-2.5 font-medium text-slate-700 text-xs">{row.internal_code ?? '—'}</td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs">{row.document_number ?? '—'}</td>
                  <td class="px-4 py-2.5 text-right tabular-nums {row.debit > 0 ? 'text-blue-700 font-medium' : 'text-slate-300'}">
                    {row.debit > 0 ? '$' + fmt(row.debit) : '—'}
                  </td>
                  <td class="px-4 py-2.5 text-right tabular-nums {row.credit > 0 ? 'text-emerald-700 font-medium' : 'text-slate-300'}">
                    {row.credit > 0 ? '$' + fmt(row.credit) : '—'}
                  </td>
                  <td class="px-5 py-2.5 text-right tabular-nums font-semibold text-slate-700">
                    {row.running_balance >= 0 ? '' : '-'}${fmt(Math.abs(row.running_balance))}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    {/if}

  </div>
</AppLayout>
