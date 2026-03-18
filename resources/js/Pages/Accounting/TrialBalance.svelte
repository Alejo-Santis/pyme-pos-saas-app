<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'

  let { byClass = [], totalDebit = 0, totalCredit = 0, balanced = true, filters = {} } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0,10),
    date_to:   filters.date_to   ?? new Date().toISOString().slice(0,10),
  })

  function search() {
    router.get('/accounting/trial-balance', form, { preserveState: true, replace: true })
  }

  // Clases de balance (1,2,3) vs resultados (4,5,6)
  const balanceClasses  = [1, 2, 3]
  const resultClasses   = [4, 5, 6]

  const balanceSection  = $derived(byClass.filter(c => balanceClasses.includes(c.class)))
  const resultSection   = $derived(byClass.filter(c => resultClasses.includes(c.class)))
</script>

<AppLayout>
  <div class="space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Balance de Prueba</h1>
        <p class="text-sm text-slate-500 mt-0.5">Comprobación de cuadre contable por período</p>
      </div>
      <div class="flex items-center gap-3">
        <ExportButtons baseUrl="/accounting/trial-balance/export" params={{ date_from: form.date_from, date_to: form.date_to }} />
        {#if !balanced}
          <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-xl">
            <i class="mdi mdi-alert-circle-outline"></i>
            Asientos descuadrados
          </div>
        {:else}
          <div class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-xl">
            <i class="mdi mdi-check-circle-outline"></i>
            Cuadre correcto
          </div>
        {/if}
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
      <div class="flex items-end gap-3">
        <div class="flex-1">
          <label class="text-xs font-medium text-slate-500 block mb-1">Desde</label>
          <input type="date" bind:value={form.date_from}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <div class="flex-1">
          <label class="text-xs font-medium text-slate-500 block mb-1">Hasta</label>
          <input type="date" bind:value={form.date_to}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
        </div>
        <button onclick={search}
          class="px-5 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer">
          Generar
        </button>
      </div>
    </div>

    <!-- Tabla principal -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-700 text-white">
          <tr>
            <th class="text-left px-5 py-3 font-semibold text-xs uppercase tracking-wide">Cuenta</th>
            <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wide">Nombre</th>
            <th class="text-right px-4 py-3 font-semibold text-xs uppercase tracking-wide w-36">Débito</th>
            <th class="text-right px-5 py-3 font-semibold text-xs uppercase tracking-wide w-36">Crédito</th>
          </tr>
        </thead>
        <tbody>
          {#each byClass as section}
            <!-- Encabezado de clase -->
            <tr class="bg-slate-100">
              <td colspan="4" class="px-5 py-2 font-bold text-slate-700 text-xs uppercase tracking-wide">
                {section.class} — {section.class_name}
              </td>
            </tr>
            <!-- Cuentas de la clase -->
            {#each section.accounts as acc}
              <tr class="hover:bg-slate-50/50 border-b border-slate-50">
                <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{acc.code}</td>
                <td class="px-4 py-2.5 text-slate-700">{acc.name}</td>
                <td class="px-4 py-2.5 text-right tabular-nums {acc.total_debit > 0 ? 'text-blue-700' : 'text-slate-300'}">
                  {acc.total_debit > 0 ? '$' + fmt(acc.total_debit) : '—'}
                </td>
                <td class="px-5 py-2.5 text-right tabular-nums {acc.total_credit > 0 ? 'text-emerald-700' : 'text-slate-300'}">
                  {acc.total_credit > 0 ? '$' + fmt(acc.total_credit) : '—'}
                </td>
              </tr>
            {/each}
            <!-- Subtotal de clase -->
            <tr class="bg-slate-50 border-b border-slate-200">
              <td colspan="2" class="px-5 py-2 text-xs font-semibold text-slate-600 text-right">
                Subtotal {section.class_name}
              </td>
              <td class="px-4 py-2 text-right font-bold tabular-nums text-blue-800">${fmt(section.total_debit)}</td>
              <td class="px-5 py-2 text-right font-bold tabular-nums text-emerald-800">${fmt(section.total_credit)}</td>
            </tr>
          {/each}
        </tbody>
        <tfoot class="bg-slate-800 text-white">
          <tr>
            <td colspan="2" class="px-5 py-3 font-bold text-sm">TOTALES DE COMPROBACIÓN</td>
            <td class="px-4 py-3 text-right font-bold tabular-nums text-blue-300 text-sm">${fmt(totalDebit)}</td>
            <td class="px-5 py-3 text-right font-bold tabular-nums text-emerald-300 text-sm">${fmt(totalCredit)}</td>
          </tr>
        </tfoot>
      </table>
    </div>

  </div>
</AppLayout>
