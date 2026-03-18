<script>
  import { router } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';

  let { runs, filters, stats } = $props();

  let year = $state(filters.year ?? new Date().getFullYear());
  let status = $state(filters.status ?? '');

  function doFilter() {
    router.get('/payroll/runs', { year, status }, { preserveState: true, replace: true });
  }

  const fmt = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);
  const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

  const statusConfig = {
    draft:     { label: 'Borrador',  color: 'bg-yellow-100 text-yellow-700' },
    approved:  { label: 'Aprobada',  color: 'bg-blue-100 text-blue-700' },
    paid:      { label: 'Pagada',    color: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Anulada',   color: 'bg-red-100 text-red-700' },
  };
</script>

<AppLayout title="Liquidaciones de Nómina">
  <div class="p-6 space-y-5">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Nómina</h1>
        <p class="text-sm text-slate-500 mt-0.5">Liquidaciones de nómina por período</p>
      </div>
      <div class="flex gap-2">
        <a href="/payroll/novelties"
           class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition">
          <i class="mdi mdi-bell-outline"></i> Novedades
        </a>
        <a href="/payroll/employees"
           class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition">
          <i class="mdi mdi-account-group"></i> Empleados
        </a>
        <a href="/payroll/runs/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
          <i class="mdi mdi-calculator"></i> Nueva Liquidación
        </a>
      </div>
    </div>

    <!-- Stats rápidos -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Empleados activos</p>
        <p class="text-2xl font-bold text-blue-600 mt-1">{stats.total_employees}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Liquidaciones pendientes</p>
        <p class="text-2xl font-bold text-yellow-500 mt-1">{stats.pending_runs}</p>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
      <select bind:value={year} onchange={doFilter}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        {#each [2026, 2025, 2024] as y}
          <option value={y}>{y}</option>
        {/each}
      </select>
      <select bind:value={status} onchange={doFilter}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        <option value="">Todos los estados</option>
        <option value="draft">Borrador</option>
        <option value="approved">Aprobada</option>
        <option value="paid">Pagada</option>
        <option value="cancelled">Anulada</option>
      </select>
    </div>

    <!-- Lista de liquidaciones -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      {#if runs.data.length === 0}
        <div class="text-center py-16 text-slate-400">
          <i class="mdi mdi-calculator-variant text-5xl block mb-3"></i>
          <p class="font-medium">No hay liquidaciones para este período</p>
          <a href="/payroll/runs/create" class="text-blue-600 text-sm hover:underline mt-1 inline-block">Crear primera liquidación</a>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
              <tr>
                <th class="px-4 py-3 font-semibold">Liquidación</th>
                <th class="px-4 py-3 font-semibold">Período</th>
                <th class="px-4 py-3 font-semibold text-right">Devengado</th>
                <th class="px-4 py-3 font-semibold text-right">Deducciones</th>
                <th class="px-4 py-3 font-semibold text-right">Neto a pagar</th>
                <th class="px-4 py-3 font-semibold text-right">Costo total</th>
                <th class="px-4 py-3 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each runs.data as run}
                {@const sc = statusConfig[run.status] ?? statusConfig.draft}
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{run.name}</p>
                    <p class="text-xs text-slate-400">Por {run.created_by?.name ?? '—'}</p>
                  </td>
                  <td class="px-4 py-3 text-slate-600 text-xs">
                    {fmtDate(run.period_start)} — {fmtDate(run.period_end)}
                  </td>
                  <td class="px-4 py-3 text-right font-medium text-slate-700">{fmt(run.total_earned)}</td>
                  <td class="px-4 py-3 text-right text-red-600">{fmt(run.total_deductions)}</td>
                  <td class="px-4 py-3 text-right font-bold text-green-700">{fmt(run.total_net)}</td>
                  <td class="px-4 py-3 text-right text-slate-600">{fmt(run.total_employer_cost)}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {sc.color}">{sc.label}</span>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <a href="/payroll/runs/{run.id}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ver →</a>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        {#if runs.last_page > 1}
          <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
            <span>{runs.from}–{runs.to} de {runs.total}</span>
            <div class="flex gap-1">
              {#each runs.links as link}
                {#if link.url}
                  <a href={link.url} class="px-3 py-1 rounded border text-xs {link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50'}" innerHTML={link.label}></a>
                {:else}
                  <span class="px-3 py-1 rounded border border-slate-100 text-xs text-slate-300" innerHTML={link.label}></span>
                {/if}
              {/each}
            </div>
          </div>
        {/if}
      {/if}
    </div>

  </div>
</AppLayout>
