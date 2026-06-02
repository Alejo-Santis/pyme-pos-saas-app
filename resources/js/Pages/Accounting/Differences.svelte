<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    rows = [],
    summary = {},
    thirdParties = [],
    accounts = [],
    filters = {},
  } = $props()

  let form = $state({
    third_party_id: filters.third_party_id ?? '',
    side: filters.side ?? 'all',
    only_differences: filters.only_differences ?? true,
  })
  let showAdjustModal = $state(false)
  let selectedRow = $state(null)
  let adjustment = $state({
    document_id: '',
    side: 'receivable',
    amount: '',
    target_account_code: '',
    counterpart_account_code: '',
    direction: 'increase',
    issue_date: new Date().toISOString().slice(0, 10),
    reason: '',
  })

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  const num = (n) => Number(n ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 0 })

  const sideLabels = {
    all: 'Todos',
    receivable: 'Clientes',
    payable: 'Proveedores',
  }

  const docTypes = {
    1: 'Factura',
    5: 'Documento soporte',
    14: 'Compra',
    92: 'Nota débito',
  }

  const accountLabel = (account) => `${account.code} · ${account.name}`
  const findAccount = (exactCode, prefixes = []) =>
    accounts.find((account) => account.code === exactCode)?.code
      ?? accounts.find((account) => prefixes.some((prefix) => String(account.code).startsWith(prefix)))?.code
      ?? ''

  function suggestedTarget(side) {
    return side === 'payable'
      ? findAccount('22050101', ['22'])
      : findAccount('13050501', ['13'])
  }

  function suggestedCounterpart(direction) {
    return direction === 'increase'
      ? findAccount('42959501', ['42', '4'])
      : findAccount('51959501', ['51', '5'])
  }

  function search() {
    router.get('/accounting/differences', form, { preserveState: true, replace: true })
  }

  function clear() {
    form = { third_party_id: '', side: 'all', only_differences: true }
    router.get('/accounting/differences', {}, { preserveState: true, replace: true })
  }

  function regenerate(row) {
    router.post('/accounting/audit/regenerate', {
      source_type: 'document',
      source_id: row.id,
    }, { preserveScroll: true })
  }

  function openAuxiliary(row) {
    const params = new URLSearchParams({
      document_number: row.internal_code,
      third_party_id: row.third_party_id ?? '',
    })
    router.visit(`/accounting/auxiliary?${params.toString()}`)
  }

  function openAdjustment(row) {
    const difference = Number(row.difference ?? 0)
    const direction = difference > 0 ? 'increase' : 'decrease'

    selectedRow = row
    adjustment = {
      document_id: row.id,
      side: row.side,
      amount: Math.abs(difference).toFixed(2),
      target_account_code: suggestedTarget(row.side),
      counterpart_account_code: suggestedCounterpart(direction),
      direction,
      issue_date: new Date().toISOString().slice(0, 10),
      reason: `Ajuste por diferencia entre saldo comercial y contable de ${row.internal_code}.`,
    }
    showAdjustModal = true
  }

  function closeAdjustment() {
    showAdjustModal = false
    selectedRow = null
  }

  function submitAdjustment() {
    router.post('/accounting/differences/adjust', adjustment, {
      preserveScroll: true,
      onSuccess: closeAdjustment,
    })
  }
</script>

<AppLayout>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Diferencias Contables</h1>
        <p class="text-sm text-slate-500 mt-0.5">Comparación entre saldos comerciales y saldos contables</p>
      </div>
      <a href="/accounting/auxiliary"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50">
        <i class="mdi mdi-file-tree-outline text-base"></i>
        Auxiliar
      </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
      <div class="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_180px_auto_auto_auto] lg:items-end">
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
          <label class="block text-xs font-medium text-slate-500 mb-1">Tipo</label>
          <select bind:value={form.side}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary">
            {#each Object.entries(sideLabels) as [key, label]}
              <option value={key}>{label}</option>
            {/each}
          </select>
        </div>
        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
          <input type="checkbox" bind:checked={form.only_differences} class="rounded border-slate-300" />
          Solo diferencias
        </label>
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
        <p class="text-xs text-slate-500 mb-1">Documentos</p>
        <p class="text-2xl font-bold text-slate-800">{num(summary.documents)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Saldo comercial</p>
        <p class="text-2xl font-bold text-slate-800">{fmt(summary.commercial_balance)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Saldo contable</p>
        <p class="text-2xl font-bold text-slate-800">{fmt(summary.accounting_balance)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500 mb-1">Diferencia abs.</p>
        <p class="text-2xl font-bold text-rose-700">{fmt(summary.absolute_difference)}</p>
      </div>
    </div>

    <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700">Documentos revisados</h2>
        <span class="text-xs text-slate-400">{rows.length} registros</span>
      </div>

      {#if rows.length === 0}
        <div class="py-16 text-center text-slate-400 text-sm">
          <i class="mdi mdi-check-decagram-outline text-4xl block mb-2"></i>
          Sin diferencias con los filtros actuales
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr class="text-xs font-semibold text-slate-500 uppercase tracking-wide text-left">
                <th class="px-4 py-2.5">Documento</th>
                <th class="px-4 py-2.5">Tercero</th>
                <th class="px-4 py-2.5 text-right">Comercial</th>
                <th class="px-4 py-2.5 text-right">Contable</th>
                <th class="px-4 py-2.5 text-right">Diferencia</th>
                <th class="px-4 py-2.5">Recibos/Egresos</th>
                <th class="px-4 py-2.5 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each rows as row}
                {@const isBalanced = Math.abs(Number(row.difference)) < 1}
                <tr class="hover:bg-slate-50">
                  <td class="px-4 py-3">
                    <a href={`/invoices/${row.id}`} class="font-semibold text-slate-800 hover:text-blue-700">{row.internal_code}</a>
                    <p class="text-xs text-slate-400">{docTypes[row.type_document_operation_id] ?? `Operación ${row.type_document_operation_id}`} · {row.issue_date}</p>
                  </td>
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-700 max-w-[240px] truncate">{row.third_party}</p>
                    <p class="text-xs text-slate-400">{sideLabels[row.side] ?? row.side} · {row.identification_number ?? 'Sin identificación'}</p>
                  </td>
                  <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-700">{fmt(row.commercial_balance)}</td>
                  <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-700">{fmt(row.accounting_balance)}</td>
                  <td class="px-4 py-3 text-right">
                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums {isBalanced ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}">
                      {fmt(row.difference)}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    {#if row.receipts?.length}
                      <div class="space-y-1">
                        {#each row.receipts as receipt}
                          <p class="text-xs text-slate-500">
                            <span class="font-medium text-slate-700">{receipt.internal_code}</span>
                            · {receipt.issue_date} · {fmt(receipt.amount)}
                          </p>
                        {/each}
                      </div>
                    {:else}
                      <span class="text-xs text-slate-400">Sin cruces registrados</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex items-center gap-1">
                      <button onclick={() => openAuxiliary(row)}
                        class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        Auxiliar
                      </button>
                      {#if !isBalanced}
                        <button onclick={() => openAdjustment(row)}
                          class="rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-100">
                          Ajustar
                        </button>
                      {/if}
                      <button onclick={() => regenerate(row)}
                        class="rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-700">
                        Regenerar
                      </button>
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </section>

    {#if showAdjustModal}
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 px-4 py-6">
        <div class="w-full max-w-2xl rounded-xl bg-white shadow-xl">
          <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Ajuste contable controlado</h2>
              <p class="mt-0.5 text-xs text-slate-500">
                {selectedRow?.internal_code} · diferencia {fmt(selectedRow?.difference)}
              </p>
            </div>
            <button onclick={closeAdjustment} aria-label="Cerrar ajuste" title="Cerrar ajuste"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
              <i class="mdi mdi-close text-xl"></i>
            </button>
          </div>

          <div class="grid grid-cols-1 gap-4 px-5 py-4 md:grid-cols-2">
            <div>
              <label for="adjustment-direction" class="mb-1 block text-xs font-medium text-slate-500">Movimiento</label>
              <select id="adjustment-direction" bind:value={adjustment.direction}
                onchange={() => adjustment.counterpart_account_code = suggestedCounterpart(adjustment.direction)}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="increase">Aumentar saldo contable</option>
                <option value="decrease">Disminuir saldo contable</option>
              </select>
            </div>
            <div>
              <label for="adjustment-amount" class="mb-1 block text-xs font-medium text-slate-500">Valor</label>
              <input id="adjustment-amount" type="number" step="0.01" min="0.01" bind:value={adjustment.amount}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label for="adjustment-target-account" class="mb-1 block text-xs font-medium text-slate-500">Cuenta cartera/proveedor</label>
              <select id="adjustment-target-account" bind:value={adjustment.target_account_code}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">Seleccionar cuenta</option>
                {#each accounts as account}
                  <option value={account.code}>{accountLabel(account)}</option>
                {/each}
              </select>
            </div>
            <div>
              <label for="adjustment-counterpart-account" class="mb-1 block text-xs font-medium text-slate-500">Contrapartida</label>
              <select id="adjustment-counterpart-account" bind:value={adjustment.counterpart_account_code}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
                <option value="">Seleccionar cuenta</option>
                {#each accounts as account}
                  <option value={account.code}>{accountLabel(account)}</option>
                {/each}
              </select>
            </div>
            <div>
              <label for="adjustment-date" class="mb-1 block text-xs font-medium text-slate-500">Fecha</label>
              <input id="adjustment-date" type="date" bind:value={adjustment.issue_date}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label for="adjustment-side" class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
              <input id="adjustment-side" readonly value={sideLabels[adjustment.side] ?? adjustment.side}
                class="w-full rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm text-slate-500" />
            </div>
            <div class="md:col-span-2">
              <label for="adjustment-reason" class="mb-1 block text-xs font-medium text-slate-500">Motivo</label>
              <textarea id="adjustment-reason" bind:value={adjustment.reason} rows="3"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>
          </div>

          <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end">
            <button onclick={closeAdjustment}
              class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Cancelar
            </button>
            <button onclick={submitAdjustment}
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
              Crear comprobante
            </button>
          </div>
        </div>
      </div>
    {/if}
  </div>
</AppLayout>
