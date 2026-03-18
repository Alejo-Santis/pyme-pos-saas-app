<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'

  let { summary = {}, detail = [], filters = {} } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const fmtSigned = (n) => {
    const v = Number(n ?? 0)
    return (v >= 0 ? '' : '-') + '$' + fmt(Math.abs(v))
  }

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0,10),
    date_to:   filters.date_to   ?? new Date().toISOString().slice(0,10),
  })

  function search() {
    router.get('/accounting/income-statement', form, { preserveState: true, replace: true })
  }

  const utilidadNeta = $derived(Number(summary.utilidad_neta ?? 0))
  const esUtilidad   = $derived(utilidadNeta >= 0)

  let expandedGroups = $state({})
  function toggleGroup(code) { expandedGroups = { ...expandedGroups, [code]: !expandedGroups[code] } }
</script>

<AppLayout>
  <div class="space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Estado de Resultados</h1>
        <p class="text-sm text-slate-500 mt-0.5">Ingresos, costos y gastos del período · NIIF PYMES</p>
      </div>
      <ExportButtons baseUrl="/accounting/income-statement/export" params={{ date_from: form.date_from, date_to: form.date_to }} />
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 flex items-end gap-3">
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Resumen ejecutivo -->
      <div class="space-y-3">

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-3">
          <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Resumen ejecutivo</h3>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
              <span class="text-slate-600">Ingresos operacionales</span>
              <span class="font-semibold text-emerald-700 tabular-nums">${fmt(summary.ingresos_operacionales)}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
              <span class="text-slate-500 ml-3">(-) Costo de ventas</span>
              <span class="text-red-600 tabular-nums">-${fmt(summary.costo_ventas)}</span>
            </div>
            <div class="flex justify-between items-center py-2 bg-blue-50 rounded-lg px-3 font-semibold">
              <span class="text-blue-800">Utilidad bruta</span>
              <span class="text-blue-800 tabular-nums">${fmt(summary.utilidad_bruta)}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
              <span class="text-slate-500 ml-3">(-) Gastos operacionales</span>
              <span class="text-red-600 tabular-nums">-${fmt(summary.gastos_operacionales)}</span>
            </div>
            <div class="flex justify-between items-center py-2 bg-indigo-50 rounded-lg px-3 font-semibold">
              <span class="text-indigo-800">Utilidad operativa</span>
              <span class="text-indigo-800 tabular-nums">${fmt(summary.utilidad_operativa)}</span>
            </div>
            {#if Number(summary.ingresos_no_operacionales) !== 0 || Number(summary.gastos_no_operacionales) !== 0}
              <div class="flex justify-between items-center py-1.5 border-b border-slate-100">
                <span class="text-slate-500 ml-3">(+/-) No operacional</span>
                <span class="tabular-nums text-slate-600">
                  {fmtSigned(Number(summary.ingresos_no_operacionales) - Number(summary.gastos_no_operacionales))}
                </span>
              </div>
            {/if}
          </div>

          <!-- Resultado final -->
          <div class="pt-3 border-t border-slate-200">
            <div class="rounded-xl p-4 text-center {esUtilidad ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200'}">
              <p class="text-xs font-medium {esUtilidad ? 'text-emerald-600' : 'text-red-600'} mb-1">
                {esUtilidad ? 'UTILIDAD NETA' : 'PÉRDIDA NETA'}
              </p>
              <p class="text-2xl font-bold {esUtilidad ? 'text-emerald-700' : 'text-red-700'} tabular-nums">
                ${fmt(Math.abs(utilidadNeta))}
              </p>
            </div>
          </div>
        </div>

        <!-- Margen de utilidad -->
        {#if Number(summary.ingresos_operacionales) > 0}
          {@const margen = (utilidadNeta / Number(summary.ingresos_operacionales)) * 100}
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <p class="text-xs text-slate-500 mb-2">Margen neto</p>
            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full {margen >= 0 ? 'bg-emerald-500' : 'bg-red-500'} transition-all"
                style="width: {Math.min(Math.abs(margen), 100)}%"></div>
            </div>
            <p class="text-sm font-bold mt-1.5 {margen >= 0 ? 'text-emerald-700' : 'text-red-700'}">
              {margen.toFixed(1)}%
            </p>
          </div>
        {/if}

      </div>

      <!-- Detalle por cuentas -->
      <div class="lg:col-span-2 space-y-3">
        {#each detail as section}
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between
              {section.class === 4 ? 'bg-emerald-50' : 'bg-red-50'}">
              <span class="text-sm font-bold {section.class === 4 ? 'text-emerald-800' : 'text-red-800'}">
                {section.class_name}
              </span>
              <span class="text-sm font-bold tabular-nums {section.class === 4 ? 'text-emerald-800' : 'text-red-800'}">
                ${fmt(section.total)}
              </span>
            </div>
            {#each section.groups as group}
              <div>
                <button onclick={() => toggleGroup(group.code)}
                  class="w-full flex items-center justify-between px-5 py-2.5 hover:bg-slate-50 transition cursor-pointer text-left">
                  <span class="text-sm font-semibold text-slate-700">{group.name}</span>
                  <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-slate-700 tabular-nums">${fmt(group.total)}</span>
                    <i class="mdi text-slate-400 {expandedGroups[group.code] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
                  </div>
                </button>
                {#if expandedGroups[group.code]}
                  {#each group.accounts as acc}
                    <div class="flex items-center justify-between px-8 py-2 border-t border-slate-50 hover:bg-slate-50/50">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-slate-400">{acc.code}</span>
                        <span class="text-sm text-slate-600">{acc.name}</span>
                      </div>
                      <span class="text-sm tabular-nums text-slate-700">${fmt(acc.balance)}</span>
                    </div>
                  {/each}
                {/if}
              </div>
            {/each}
          </div>
        {/each}

        {#if detail.length === 0}
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm py-16 text-center">
            <i class="mdi mdi-chart-line text-5xl text-slate-200 block mb-3"></i>
            <p class="text-slate-400 text-sm">Sin movimientos en el período seleccionado</p>
          </div>
        {/if}
      </div>

    </div>
  </div>
</AppLayout>
