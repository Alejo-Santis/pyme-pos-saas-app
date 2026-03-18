<script>
  import { useForm, router } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';

  let { periodStart, periodEnd, preview, smmlv, transportAllowance } = $props();

  const form = useForm({
    name:         `Nómina ${new Date(periodStart + 'T00:00:00').toLocaleDateString('es-CO', { month: 'long', year: 'numeric' })}`,
    period_start: periodStart,
    period_end:   periodEnd,
    notes:        '',
  });

  let localStart = $state(periodStart);
  let localEnd   = $state(periodEnd);
  let previewData = $state(preview);
  let loadingPreview = $state(false);

  function refreshPreview() {
    if (!localStart || !localEnd) return;
    loadingPreview = true;
    router.get('/payroll/runs/create', { period_start: localStart, period_end: localEnd },
      { preserveState: true, replace: true, onSuccess: () => { loadingPreview = false; } }
    );
  }

  function submit() {
    form.period_start = localStart;
    form.period_end   = localEnd;
    form.post('/payroll/runs');
  }

  const fmt = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);
  const pct = (part, total) => total > 0 ? ((part / total) * 100).toFixed(1) + '%' : '0%';
</script>

<AppLayout title="Nueva Liquidación de Nómina">
  <div class="p-6 max-w-6xl mx-auto space-y-6">

    <div class="flex items-center gap-3">
      <a href="/payroll/runs" class="text-slate-400 hover:text-slate-600">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Nueva Liquidación de Nómina</h1>
        <p class="text-sm text-slate-500">El sistema calculará automáticamente todos los aportes según la ley colombiana</p>
      </div>
    </div>

    <!-- Período -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <h2 class="font-semibold text-slate-700 mb-4 flex items-center gap-2">
        <i class="mdi mdi-calendar-range text-blue-500"></i> Período de liquidación
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Nombre de la liquidación</label>
          <input type="text" bind:value={$form.name}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Fecha inicio</label>
          <input type="date" bind:value={localStart} onblur={refreshPreview}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Fecha fin</label>
          <input type="date" bind:value={localEnd} onblur={refreshPreview}
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs font-medium text-slate-600 mb-1">Observaciones</label>
          <input type="text" bind:value={$form.notes} placeholder="Opcional"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
        </div>
      </div>
    </div>

    <!-- Parámetros legales vigentes -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex flex-wrap gap-6 text-sm">
      <div>
        <span class="text-blue-600 font-semibold">SMMLV 2025:</span>
        <span class="text-blue-800 ml-1">{fmt(smmlv)}</span>
      </div>
      <div>
        <span class="text-blue-600 font-semibold">Auxilio transporte:</span>
        <span class="text-blue-800 ml-1">{fmt(transportAllowance)}</span>
      </div>
      <div>
        <span class="text-blue-600 font-semibold">Salud empleado:</span>
        <span class="text-blue-800 ml-1">4% / empleador: 8.5%</span>
      </div>
      <div>
        <span class="text-blue-600 font-semibold">Pensión empleado:</span>
        <span class="text-blue-800 ml-1">4% / empleador: 12%</span>
      </div>
      <div>
        <span class="text-blue-600 font-semibold">CCF:</span>
        <span class="text-blue-800 ml-1">4%</span>
      </div>
    </div>

    <!-- Vista previa por empleado -->
    {#if previewData && previewData.employees.length > 0}
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-700 flex items-center gap-2">
            <i class="mdi mdi-eye-outline text-blue-500"></i>
            Vista previa — {previewData.employees.length} empleado{previewData.employees.length !== 1 ? 's' : ''}
          </h2>
          {#if loadingPreview}
            <span class="text-xs text-slate-400"><i class="mdi mdi-loading mdi-spin"></i> Recalculando...</span>
          {/if}
        </div>

        <!-- Totales globales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-0 border-b border-slate-100">
          <div class="px-5 py-4 border-r border-slate-100">
            <p class="text-xs text-slate-500">Total devengado</p>
            <p class="text-xl font-bold text-slate-800 mt-0.5">{fmt(previewData.totals.total_earned)}</p>
          </div>
          <div class="px-5 py-4 border-r border-slate-100">
            <p class="text-xs text-slate-500">Total deducciones</p>
            <p class="text-xl font-bold text-red-600 mt-0.5">{fmt(previewData.totals.total_deductions)}</p>
          </div>
          <div class="px-5 py-4 border-r border-slate-100">
            <p class="text-xs text-slate-500">Neto a pagar</p>
            <p class="text-xl font-bold text-green-700 mt-0.5">{fmt(previewData.totals.total_net)}</p>
          </div>
          <div class="px-5 py-4">
            <p class="text-xs text-slate-500">Costo total empleador</p>
            <p class="text-xl font-bold text-orange-600 mt-0.5">{fmt(previewData.totals.total_employer_cost)}</p>
          </div>
        </div>

        <!-- Tabla por empleado -->
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500 text-left">
              <tr>
                <th class="px-4 py-2 font-semibold">Empleado</th>
                <th class="px-4 py-2 font-semibold text-right">Días</th>
                <th class="px-4 py-2 font-semibold text-right">Salario</th>
                <th class="px-4 py-2 font-semibold text-right">Transp.</th>
                <th class="px-4 py-2 font-semibold text-right">Extras</th>
                <th class="px-4 py-2 font-semibold text-right">Devengado</th>
                <th class="px-4 py-2 font-semibold text-right">Salud</th>
                <th class="px-4 py-2 font-semibold text-right">Pensión</th>
                <th class="px-4 py-2 font-semibold text-right">Ret.Fte</th>
                <th class="px-4 py-2 font-semibold text-right">Neto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              {#each previewData.employees as emp}
                <tr class="hover:bg-slate-50">
                  <td class="px-4 py-2 font-medium text-slate-700">{emp.employee_name}</td>
                  <td class="px-4 py-2 text-right text-slate-500">{emp.worked_days}</td>
                  <td class="px-4 py-2 text-right text-slate-600">{fmt(emp.basic_salary)}</td>
                  <td class="px-4 py-2 text-right text-slate-500">{fmt(emp.transport_allowance)}</td>
                  <td class="px-4 py-2 text-right text-slate-500">{fmt(emp.overtime_amount + emp.commissions + emp.bonuses)}</td>
                  <td class="px-4 py-2 text-right font-medium text-slate-700">{fmt(emp.total_earned)}</td>
                  <td class="px-4 py-2 text-right text-red-500">({fmt(emp.health_employee)})</td>
                  <td class="px-4 py-2 text-right text-red-500">({fmt(emp.pension_employee)})</td>
                  <td class="px-4 py-2 text-right text-red-500">({fmt(emp.income_tax_withholding)})</td>
                  <td class="px-4 py-2 text-right font-bold text-green-700">{fmt(emp.net_pay)}</td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    {:else if previewData && previewData.employees.length === 0}
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-700 text-sm">
        <i class="mdi mdi-alert-outline mr-2"></i>
        No hay empleados con contrato activo en el período seleccionado.
        <a href="/payroll/employees/create" class="underline ml-1">Crear empleado</a>
      </div>
    {/if}

    <!-- Errores -->
    {#if $form.errors.period_start}
      <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm">
        <i class="mdi mdi-alert-circle mr-2"></i>{$form.errors.period_start}
      </div>
    {/if}

    <!-- Botones -->
    <div class="flex justify-end gap-3">
      <a href="/payroll/runs"
         class="px-5 py-2 text-sm font-medium text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
        Cancelar
      </a>
      <button
        type="button"
        onclick={submit}
        disabled={$form.processing || !previewData?.employees?.length}
        class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-60 flex items-center gap-2">
        <i class="mdi mdi-calculator"></i>
        {$form.processing ? 'Procesando...' : 'Procesar Liquidación'}
      </button>
    </div>

  </div>
</AppLayout>
