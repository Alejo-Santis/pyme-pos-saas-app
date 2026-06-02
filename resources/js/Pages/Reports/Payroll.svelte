<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'
  import { router } from '@inertiajs/svelte'

  let { runs = [], detail = null, yearTotals, filters, years = [] } = $props()

  let year    = $state(filters.year ?? new Date().getFullYear())
  let status  = $state(filters.status ?? '')
  let runId   = $state(filters.run_id ?? '')

  function apply() {
    router.get('/reports/payroll', { year, status, run_id: runId || undefined }, { preserveScroll: true, replace: true })
  }

  function selectRun(id) {
    runId = id
    apply()
  }

  const fmt = (n) => Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })

  const statusCls = (s) => ({
    draft:     'bg-slate-100 text-slate-600',
    approved:  'bg-blue-50 text-blue-700',
    paid:      'bg-emerald-50 text-emerald-700',
    cancelled: 'bg-rose-50 text-rose-600',
  }[s] ?? 'bg-slate-100 text-slate-600')

  const nesCls = (s) => ({
    sent:       'bg-emerald-100 text-emerald-700',
    processing: 'bg-amber-100 text-amber-700',
    partial:    'bg-orange-100 text-orange-700',
    failed:     'bg-rose-100 text-rose-700',
  }[s] ?? '')
</script>

<AppLayout>
  <div class="space-y-5">

    <!-- Cabecera -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Reporte de Nómina</h1>
        <p class="text-sm text-slate-500 mt-0.5">Liquidaciones, devengados, deducciones y aportes patronales</p>
      </div>
      {#if detail}
        <ExportButtons baseUrl="/reports/payroll/export" params={{ run_id: detail.run.id }} />
      {/if}
    </div>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-3 items-end rounded-xl border border-slate-200 bg-white p-4">
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Año</label>
        <select bind:value={year} class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          {#each years as y}<option value={y}>{y}</option>{/each}
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
        <select bind:value={status} class="rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="">Todos</option>
          <option value="draft">Borrador</option>
          <option value="approved">Aprobada</option>
          <option value="paid">Pagada</option>
          <option value="cancelled">Anulada</option>
        </select>
      </div>
      <button onclick={apply} class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
        <i class="mdi mdi-magnify mr-1"></i>Buscar
      </button>
    </div>

    <!-- Totales anuales -->
    {#if yearTotals}
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        {#each [
          { label: 'Total Devengado', value: yearTotals.total_earned, cls: 'text-slate-800' },
          { label: 'Total Deducciones', value: yearTotals.total_deductions, cls: 'text-rose-700' },
          { label: 'Total Neto Pagado', value: yearTotals.total_net, cls: 'text-emerald-700' },
          { label: 'Costo Patronal Total', value: yearTotals.total_employer_cost, cls: 'text-blue-700' },
        ] as card}
          <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-500">{card.label}</p>
            <p class="mt-1 text-lg font-bold {card.cls}">$ {fmt(card.value)}</p>
          </div>
        {/each}
      </div>
    {/if}

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
      <!-- Lista de liquidaciones -->
      <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-3">
          <p class="text-sm font-semibold text-slate-700">Liquidaciones {year}</p>
        </div>
        {#if runs.length === 0}
          <p class="p-6 text-center text-sm text-slate-400">No hay liquidaciones para este período.</p>
        {:else}
          <ul class="divide-y divide-slate-100">
            {#each runs as run}
              <li>
                <button
                  onclick={() => selectRun(run.id)}
                  class="w-full text-left px-4 py-3 hover:bg-slate-50 transition-colors {run.id === runId ? 'bg-blue-50' : ''}"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-slate-800 truncate">{run.name}</p>
                      <p class="text-xs text-slate-500 mt-0.5">{run.period_start} → {run.period_end}</p>
                      <p class="text-xs text-slate-500">{run.employee_count} empleados</p>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                      <span class="rounded-full px-2 py-0.5 text-xs font-medium {statusCls(run.status)}">{run.status_label}</span>
                      {#if run.nes_status}
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {nesCls(run.nes_status)}">NES: {run.nes_status}</span>
                      {/if}
                    </div>
                  </div>
                  <div class="mt-2 flex gap-3 text-xs text-slate-600">
                    <span>Neto: <strong>$ {fmt(run.total_net)}</strong></span>
                    <span>Patronal: <strong>$ {fmt(run.total_employer_cost)}</strong></span>
                  </div>
                </button>
              </li>
            {/each}
          </ul>
        {/if}
      </div>

      <!-- Detalle por empleado -->
      <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white overflow-hidden">
        {#if !detail}
          <div class="flex h-64 items-center justify-center text-sm text-slate-400">
            <div class="text-center">
              <i class="mdi mdi-file-chart-outline text-4xl text-slate-300"></i>
              <p class="mt-2">Selecciona una liquidación para ver el detalle</p>
            </div>
          </div>
        {:else}
          <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-700">{detail.run.name}</p>
              <p class="text-xs text-slate-500">{detail.run.period_start} → {detail.run.period_end} · {detail.run.status_label}</p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead class="bg-slate-50 text-slate-600">
                <tr>
                  <th class="px-3 py-2 text-left font-medium">Empleado</th>
                  <th class="px-3 py-2 text-right font-medium">Devengado</th>
                  <th class="px-3 py-2 text-right font-medium">Deducciones</th>
                  <th class="px-3 py-2 text-right font-medium">Neto</th>
                  <th class="px-3 py-2 text-right font-medium">Costo Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each detail.employees as emp}
                  <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2">
                      <p class="font-medium text-slate-800">{emp.employee_name}</p>
                      <p class="text-slate-400">{emp.identification} · {emp.worked_days} días</p>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums text-slate-700">$ {fmt(emp.total_earned)}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-rose-600">$ {fmt(emp.total_deductions)}</td>
                    <td class="px-3 py-2 text-right tabular-nums font-semibold text-emerald-700">$ {fmt(emp.net_pay)}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-blue-700">$ {fmt(emp.total_employer_cost)}</td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-slate-50 font-semibold">
                <tr>
                  <td class="px-3 py-2 text-slate-700">Totales</td>
                  <td class="px-3 py-2 text-right text-slate-700 tabular-nums">
                    $ {fmt(detail.employees.reduce((s, e) => s + e.total_earned, 0))}
                  </td>
                  <td class="px-3 py-2 text-right text-rose-600 tabular-nums">
                    $ {fmt(detail.employees.reduce((s, e) => s + e.total_deductions, 0))}
                  </td>
                  <td class="px-3 py-2 text-right text-emerald-700 tabular-nums">
                    $ {fmt(detail.employees.reduce((s, e) => s + e.net_pay, 0))}
                  </td>
                  <td class="px-3 py-2 text-right text-blue-700 tabular-nums">
                    $ {fmt(detail.employees.reduce((s, e) => s + e.total_employer_cost, 0))}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AppLayout>
