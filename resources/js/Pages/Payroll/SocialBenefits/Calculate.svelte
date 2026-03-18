<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { employees, types, preview, filters, smmlv } = $props()

  let employeeId = $state(filters.employee_id ?? '')
  let type       = $state(filters.type ?? '')
  let dateFrom   = $state(filters.date_from ?? '')
  let dateTo     = $state(filters.date_to ?? '')
  let saving     = $state(false)

  function calculate() {
    if (!employeeId || !type || !dateFrom || !dateTo) return
    router.get('/payroll/benefits/calculate', {
      employee_id: employeeId,
      type, date_from: dateFrom, date_to: dateTo,
    }, { preserveState: true })
  }

  function save() {
    if (!preview) return
    saving = true
    router.post('/payroll/benefits', preview, {
      onFinish: () => { saving = false }
    })
  }

  function fmt(v) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0)
  }
</script>

<AppLayout title="Liquidar Prestación">
  <div class="p-6 space-y-5 max-w-2xl">

    <div class="flex items-center gap-3">
      <a href="/payroll/benefits" class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Liquidar Prestación Social</h1>
        <p class="text-sm text-slate-500 mt-0.5">Calcula prima, cesantías, intereses o vacaciones</p>
      </div>
    </div>

    <!-- Formulario de cálculo -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Parámetros</h2>

      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Empleado</label>
          <select bind:value={employeeId}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500">
            <option value="">Seleccionar empleado...</option>
            {#each employees as emp}
              <option value={emp.id}>{emp.last_name}, {emp.first_name}</option>
            {/each}
          </select>
        </div>

        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de prestación</label>
          <select bind:value={type}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500">
            <option value="">Seleccionar tipo...</option>
            {#each Object.entries(types) as [k, v]}
              <option value={k}>{v}</option>
            {/each}
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha desde</label>
          <input type="date" bind:value={dateFrom}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Fecha hasta</label>
          <input type="date" bind:value={dateTo}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500" />
        </div>
      </div>

      <button onclick={calculate}
        class="w-full py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer">
        <i class="mdi mdi-calculator mr-2"></i>Calcular
      </button>
    </div>

    <!-- Resultado del cálculo -->
    {#if preview}
      <div class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Resultado</h2>

        <div class="grid grid-cols-2 gap-3 text-sm">
          <div class="bg-slate-50 rounded-lg p-3">
            <p class="text-xs text-slate-500">Prestación</p>
            <p class="font-medium text-slate-800">{preview.label}</p>
          </div>
          <div class="bg-slate-50 rounded-lg p-3">
            <p class="text-xs text-slate-500">Días trabajados</p>
            <p class="font-medium text-slate-800">{preview.days_worked} días</p>
          </div>
          <div class="bg-slate-50 rounded-lg p-3">
            <p class="text-xs text-slate-500">Salario base</p>
            <p class="font-medium text-slate-800">{fmt(preview.base_salary)}</p>
          </div>
          {#if preview.transport > 0}
            <div class="bg-slate-50 rounded-lg p-3">
              <p class="text-xs text-slate-500">Aux. transporte incluido</p>
              <p class="font-medium text-slate-800">{fmt(preview.transport)}</p>
            </div>
          {/if}
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center justify-between">
          <div>
            <p class="text-xs text-blue-600 font-medium uppercase tracking-wide">Valor a pagar</p>
            <p class="text-3xl font-bold text-blue-700">{fmt(preview.amount)}</p>
          </div>
          <i class="mdi mdi-cash-multiple text-4xl text-blue-300"></i>
        </div>

        <div class="text-xs text-slate-400 space-y-1">
          {#if preview.type === 'prima' || preview.type === 'cesantias'}
            <p>Fórmula: (Salario + Aux.Transporte) × días / 360</p>
          {:else if preview.type === 'intereses_cesantias'}
            <p>Fórmula: Cesantías × 12% × (días / 360)</p>
          {:else if preview.type === 'vacaciones'}
            <p>Fórmula: Salario × días / 720</p>
          {/if}
        </div>

        <button onclick={save} disabled={saving}
          class="w-full py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition cursor-pointer disabled:opacity-50">
          {saving ? 'Guardando...' : 'Guardar liquidación'}
        </button>
      </div>
    {/if}

  </div>
</AppLayout>
