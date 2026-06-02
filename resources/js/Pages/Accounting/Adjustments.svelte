<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'

  let { vouchers = { data: [], links: [], meta: {} }, summary = {}, filters = {} } = $props()

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    date_to: filters.date_to ?? new Date().toISOString().slice(0, 10),
    status: filters.status ?? 'active',
  })
  let expanded = $state({})
  let showReverseModal = $state(false)
  let selectedVoucher = $state(null)
  let reverseForm = $state({
    issue_date: new Date().toISOString().slice(0, 10),
    reason: '',
  })

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  const num = (n) => Number(n ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 0 })
  const opLabel = (type) => Number(type) === 99 ? 'Reverso de ajuste' : 'Ajuste manual'

  function search() {
    router.get('/accounting/adjustments', form, { preserveState: true, replace: true })
  }

  function toggle(id) {
    expanded = { ...expanded, [id]: !expanded[id] }
  }

  function openReverse(voucher) {
    selectedVoucher = voucher
    reverseForm = {
      issue_date: new Date().toISOString().slice(0, 10),
      reason: `Reversión del ajuste ${voucher.internal_code}.`,
    }
    showReverseModal = true
  }

  function closeReverse() {
    showReverseModal = false
    selectedVoucher = null
  }

  function submitReverse() {
    router.post(`/accounting/adjustments/${selectedVoucher.id}/reverse`, reverseForm, {
      preserveScroll: true,
      onSuccess: closeReverse,
    })
  }
</script>

<AppLayout>
  <div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Ajustes Contables</h1>
        <p class="mt-0.5 text-sm text-slate-500">Historial de ajustes manuales y comprobantes de reversión</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ExportButtons baseUrl="/accounting/adjustments/export" params={{ date_from: form.date_from, date_to: form.date_to, status: form.status }} />
        <a href="/accounting/differences"
          class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 shadow-sm hover:bg-slate-50">
          <i class="mdi mdi-alert-decagram-outline text-base"></i>
          Diferencias
        </a>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-[160px_160px_180px_auto] md:items-end">
        <div>
          <label for="adjustments-date-from" class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
          <input id="adjustments-date-from" type="date" bind:value={form.date_from}
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label for="adjustments-date-to" class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
          <input id="adjustments-date-to" type="date" bind:value={form.date_to}
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
        </div>
        <div>
          <label for="adjustments-status" class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
          <select id="adjustments-status" bind:value={form.status}
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none">
            <option value="active">Activos</option>
            <option value="reversed">Reversados</option>
            <option value="reversal">Sólo reversos</option>
            <option value="all">Todos</option>
          </select>
        </div>
        <button onclick={search}
          class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark">
          Filtrar
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="mb-1 text-xs text-slate-500">Comprobantes</p>
        <p class="text-2xl font-bold text-slate-800">{num(summary.total_vouchers)}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="mb-1 text-xs text-slate-500">Débitos</p>
        <p class="text-2xl font-bold text-blue-700">{fmt(summary.total_debit)}</p>
      </div>
      <div class="rounded-xl border border-slate-200 bg-white p-4">
        <p class="mb-1 text-xs text-slate-500">Créditos</p>
        <p class="text-2xl font-bold text-emerald-700">{fmt(summary.total_credit)}</p>
      </div>
    </div>

    <div class="space-y-2">
      {#each vouchers.data as voucher}
        {@const isReversal = Number(voucher.type_document_operation_id) === 99}
        {@const isReversed = Boolean(voucher.reversed_at)}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
          <div class="flex flex-col gap-3 px-5 py-3.5 md:flex-row md:items-center md:justify-between">
            <button onclick={() => toggle(voucher.id)}
              class="flex min-w-0 flex-1 items-center gap-4 text-left">
              <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg {isReversal ? 'bg-slate-100' : 'bg-amber-50'}">
                <i class="mdi {isReversal ? 'mdi-file-restore-outline text-slate-600' : 'mdi-file-edit-outline text-amber-700'} text-lg"></i>
              </div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold text-slate-800">{voucher.internal_code}</p>
                  <span class="rounded-full px-2 py-0.5 text-xs font-medium {isReversed ? 'bg-slate-100 text-slate-600' : isReversal ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-800'}">
                    {isReversed ? 'Reversado' : opLabel(voucher.type_document_operation_id)}
                  </span>
                </div>
                <p class="truncate text-xs text-slate-500">
                  {voucher.issue_date} · Ref. {voucher.document?.internal_code ?? 'Sin documento'} · {voucher.notes ?? 'Sin observación'}
                </p>
                {#if voucher.reversal}
                  <p class="mt-0.5 text-xs text-slate-400">Reversado con {voucher.reversal.internal_code} el {voucher.reversal.issue_date}</p>
                {/if}
              </div>
            </button>

            <div class="flex items-center justify-between gap-4 md:justify-end">
              <div class="text-right text-sm">
                <p class="text-xs text-slate-400">Débito</p>
                <p class="font-semibold text-blue-700 tabular-nums">{fmt(voucher.debit)}</p>
              </div>
              <div class="text-right text-sm">
                <p class="text-xs text-slate-400">Crédito</p>
                <p class="font-semibold text-emerald-700 tabular-nums">{fmt(voucher.credit)}</p>
              </div>
              {#if !isReversal && !isReversed && !voucher.annulled}
                <button onclick={() => openReverse(voucher)}
                  class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100">
                  Reversar
                </button>
              {/if}
              <i class="mdi text-lg text-slate-400 {expanded[voucher.id] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
            </div>
          </div>

          {#if expanded[voucher.id]}
            <div class="border-t border-slate-100">
              <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500">
                  <tr>
                    <th class="px-5 py-2 text-left font-medium">Cuenta</th>
                    <th class="px-4 py-2 text-right font-medium">Débito</th>
                    <th class="px-5 py-2 text-right font-medium">Crédito</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  {#each voucher.lines ?? [] as line}
                    <tr class="hover:bg-slate-50/60">
                      <td class="px-5 py-2.5">
                        <span class="mr-2 font-mono text-slate-700">{line.accountable_id}</span>
                        <span class="text-slate-400">{line.document_number ?? ''}</span>
                      </td>
                      <td class="px-4 py-2.5 text-right tabular-nums {Number(line.debit) > 0 ? 'font-medium text-blue-700' : 'text-slate-300'}">
                        {Number(line.debit) > 0 ? fmt(line.debit) : '-'}
                      </td>
                      <td class="px-5 py-2.5 text-right tabular-nums {Number(line.credit) > 0 ? 'font-medium text-emerald-700' : 'text-slate-300'}">
                        {Number(line.credit) > 0 ? fmt(line.credit) : '-'}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </section>
      {/each}

      {#if vouchers.data.length === 0}
        <div class="rounded-xl border border-slate-200 bg-white py-16 text-center shadow-sm">
          <i class="mdi mdi-file-restore-outline mb-2 block text-5xl text-slate-200"></i>
          <p class="text-sm text-slate-400">Sin ajustes contables con los filtros actuales</p>
        </div>
      {/if}
    </div>

    {#if vouchers.meta?.last_page > 1}
      <div class="flex justify-center gap-1">
        {#each vouchers.links as link}
          {#if link.url}
            <button onclick={() => router.visit(link.url)}
              class="rounded-lg border px-3 py-1.5 text-sm {link.active ? 'border-primary bg-primary text-white' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}">
              {@html link.label}
            </button>
          {/if}
        {/each}
      </div>
    {/if}

    {#if showReverseModal}
      <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/45 px-4 py-6">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-xl">
          <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
            <div>
              <h2 class="text-base font-semibold text-slate-800">Reversar ajuste</h2>
              <p class="mt-0.5 text-xs text-slate-500">{selectedVoucher?.internal_code} · {fmt(selectedVoucher?.total)}</p>
            </div>
            <button onclick={closeReverse} aria-label="Cerrar reversión" title="Cerrar reversión"
              class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
              <i class="mdi mdi-close text-xl"></i>
            </button>
          </div>

          <div class="space-y-4 px-5 py-4">
            <div>
              <label for="reverse-date" class="mb-1 block text-xs font-medium text-slate-500">Fecha del reverso</label>
              <input id="reverse-date" type="date" bind:value={reverseForm.issue_date}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none" />
            </div>
            <div>
              <label for="reverse-reason" class="mb-1 block text-xs font-medium text-slate-500">Motivo</label>
              <textarea id="reverse-reason" rows="4" bind:value={reverseForm.reason}
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary focus:outline-none"></textarea>
            </div>
          </div>

          <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:justify-end">
            <button onclick={closeReverse}
              class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
              Cancelar
            </button>
            <button onclick={submitReverse}
              class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-medium text-white hover:bg-rose-800">
              Crear reverso
            </button>
          </div>
        </div>
      </div>
    {/if}
  </div>
</AppLayout>
