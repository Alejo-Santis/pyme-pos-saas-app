<script>
  import { useForm } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';

  let { employee, contract, typeContracts, typePeriods, typeWorkers } = $props();

  const isEdit = !!employee;

  const form = useForm({
    // Datos personales
    document_type:          employee?.document_type          ?? 'CC',
    identification_number:  employee?.identification_number  ?? '',
    first_name:             employee?.first_name             ?? '',
    middle_name:            employee?.middle_name            ?? '',
    last_name:              employee?.last_name              ?? '',
    second_lastname:        employee?.second_lastname        ?? '',
    email:                  employee?.email                  ?? '',
    phone:                  employee?.phone                  ?? '',
    address:                employee?.address                ?? '',
    city:                   employee?.city                   ?? '',
    department:             employee?.department             ?? '',
    birthdate:              employee?.birthdate              ?? '',
    blood_type:             employee?.blood_type             ?? '',
    gender:                 employee?.gender                 ?? 1,
    marital_status_id:      employee?.marital_status_id      ?? '',
    emergency_contact:      employee?.emergency_contact      ?? '',
    emergency_phone:        employee?.emergency_phone        ?? '',
    // Contrato
    type_contract_id:           contract?.type_contract_id           ?? 2,
    type_worker_id:             contract?.type_worker_id             ?? 1,
    payroll_period_id:          contract?.payroll_period_id          ?? 1,
    job_title:                  contract?.job_title                  ?? '',
    cost_center:                contract?.cost_center                ?? '',
    arl_risk_class:             contract?.arl_risk_class             ?? 1,
    salary:                     contract?.salary                     ?? '',
    is_comprehensive_salary:    contract?.is_comprehensive_salary    ?? false,
    has_transport_allowance:    contract?.has_transport_allowance    ?? true,
    voluntary_health_amount:    contract?.voluntary_health_amount    ?? 0,
    voluntary_pension_amount:   contract?.voluntary_pension_amount   ?? 0,
    eps_name:                   contract?.eps_name                   ?? '',
    afp_name:                   contract?.afp_name                   ?? '',
    arl_name:                   contract?.arl_name                   ?? '',
    ccf_name:                   contract?.ccf_name                   ?? '',
    has_income_tax_withholding: contract?.has_income_tax_withholding ?? false,
    income_tax_withholding_pct: contract?.income_tax_withholding_pct ?? 0,
    start_date:                 contract?.start_date                 ?? '',
    end_date:                   contract?.end_date                   ?? '',
    trial_end_date:             contract?.trial_end_date             ?? '',
  });

  function submit() {
    if (isEdit) {
      form.put(`/payroll/employees/${employee.id}`);
    } else {
      form.post('/payroll/employees');
    }
  }

  // SMMLV 2025 para mostrar advertencia
  const SMMLV = 1423500;
  const fmtCOP = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);

  const docTypes = ['CC', 'CE', 'PA', 'TI', 'NIT', 'PT'];
  const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
  const arlRiskClasses = [
    { value: 1, label: 'Clase I — 0.522% (oficina, administrativo)' },
    { value: 2, label: 'Clase II — 1.044% (comercio, agricultura ligera)' },
    { value: 3, label: 'Clase III — 2.436% (manufactura, bodega)' },
    { value: 4, label: 'Clase IV — 4.350% (minería, construcción)' },
    { value: 5, label: 'Clase V — 6.960% (alto riesgo, explosivos)' },
  ];
</script>

<AppLayout title="{isEdit ? 'Editar' : 'Nuevo'} Empleado">
  <div class="p-6 max-w-5xl mx-auto space-y-6">

    <!-- Encabezado -->
    <div class="flex items-center gap-3">
      <a href="/payroll/employees" class="text-slate-400 hover:text-slate-600">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">{isEdit ? 'Editar Empleado' : 'Nuevo Empleado'}</h1>
        <p class="text-sm text-slate-500">Complete los datos personales y del contrato</p>
      </div>
    </div>

    <form onsubmit={(e) => { e.preventDefault(); submit() }} class="space-y-6">

      <!-- ── Datos personales ───────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
          <h2 class="font-semibold text-slate-700 flex items-center gap-2">
            <i class="mdi mdi-account text-blue-500"></i> Datos Personales
          </h2>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de documento *</label>
            <select bind:value={$form.document_type}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
              {#each docTypes as dt}
                <option value={dt}>{dt}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Número de identificación *</label>
            <input type="text" bind:value={$form.identification_number}
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                     {$form.errors.identification_number ? 'border-red-400' : 'border-slate-300'}" />
            {#if $form.errors.identification_number}
              <p class="text-red-500 text-xs mt-1">{$form.errors.identification_number}</p>
            {/if}
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de nacimiento</label>
            <input type="date" bind:value={$form.birthdate}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Primer nombre *</label>
            <input type="text" bind:value={$form.first_name}
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                     {$form.errors.first_name ? 'border-red-400' : 'border-slate-300'}" />
            {#if $form.errors.first_name}
              <p class="text-red-500 text-xs mt-1">{$form.errors.first_name}</p>
            {/if}
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Segundo nombre</label>
            <input type="text" bind:value={$form.middle_name}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Primer apellido *</label>
            <input type="text" bind:value={$form.last_name}
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                     {$form.errors.last_name ? 'border-red-400' : 'border-slate-300'}" />
            {#if $form.errors.last_name}
              <p class="text-red-500 text-xs mt-1">{$form.errors.last_name}</p>
            {/if}
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Segundo apellido</label>
            <input type="text" bind:value={$form.second_lastname}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Género *</label>
            <select bind:value={$form.gender}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              <option value={1}>Masculino</option>
              <option value={2}>Femenino</option>
              <option value={3}>Otro</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de sangre</label>
            <select bind:value={$form.blood_type}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              <option value="">— Seleccionar —</option>
              {#each bloodTypes as bt}
                <option value={bt}>{bt}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
            <input type="email" bind:value={$form.email}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Teléfono</label>
            <input type="text" bind:value={$form.phone}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Ciudad</label>
            <input type="text" bind:value={$form.city}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Dirección</label>
            <input type="text" bind:value={$form.address}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Contacto de emergencia</label>
            <input type="text" bind:value={$form.emergency_contact}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tel. emergencia</label>
            <input type="text" bind:value={$form.emergency_phone}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

        </div>
      </div>

      <!-- ── Contrato laboral ──────────────────────────────────── -->
      {#if !isEdit}
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
          <h2 class="font-semibold text-slate-700 flex items-center gap-2">
            <i class="mdi mdi-file-document-outline text-blue-500"></i> Contrato Laboral
          </h2>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de contrato *</label>
            <select bind:value={$form.type_contract_id}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              {#each typeContracts as tc}
                <option value={tc.id}>{tc.name}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de trabajador *</label>
            <select bind:value={$form.type_worker_id}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              {#each typeWorkers as tw}
                <option value={tw.id}>{tw.name}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Período de pago *</label>
            <select bind:value={$form.payroll_period_id}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              {#each typePeriods as tp}
                <option value={tp.id}>{tp.name}</option>
              {/each}
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-slate-600 mb-1">Cargo *</label>
            <input type="text" bind:value={$form.job_title} placeholder="Ej: Auxiliar Contable"
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                     {$form.errors.job_title ? 'border-red-400' : 'border-slate-300'}" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Centro de costos</label>
            <input type="text" bind:value={$form.cost_center}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <!-- Salario -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Salario básico mensual *</label>
            <div class="relative">
              <span class="absolute left-3 top-2 text-slate-400 text-sm">$</span>
              <input type="number" bind:value={$form.salary} min={SMMLV}
                class="w-full pl-7 pr-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                       {$form.errors.salary ? 'border-red-400' : 'border-slate-300'}" />
            </div>
            {#if $form.errors.salary}
              <p class="text-red-500 text-xs mt-1">{$form.errors.salary}</p>
            {:else}
              <p class="text-xs text-slate-400 mt-1">SMMLV 2025: {fmtCOP(SMMLV)}</p>
            {/if}
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Clase de riesgo ARL *</label>
            <select bind:value={$form.arl_risk_class}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
              {#each arlRiskClasses as arc}
                <option value={arc.value}>{arc.label}</option>
              {/each}
            </select>
          </div>

          <!-- Opciones booleanas -->
          <div class="md:col-span-3 flex flex-wrap gap-5">
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
              <input type="checkbox" bind:checked={$form.has_transport_allowance}
                class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
              Tiene auxilio de transporte
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
              <input type="checkbox" bind:checked={$form.is_comprehensive_salary}
                class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
              Salario integral (≥ 13 SMMLV)
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
              <input type="checkbox" bind:checked={$form.has_income_tax_withholding}
                class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
              Aplicar retención en la fuente
            </label>
          </div>

          <!-- Fechas contrato -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de inicio *</label>
            <input type="date" bind:value={$form.start_date}
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500
                     {$form.errors.start_date ? 'border-red-400' : 'border-slate-300'}" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fecha de fin <span class="text-slate-400">(vacío = indefinido)</span></label>
            <input type="date" bind:value={$form.end_date}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Fin período de prueba</label>
            <input type="date" bind:value={$form.trial_end_date}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>

          <!-- Entidades seguridad social -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">EPS (salud)</label>
            <input type="text" bind:value={$form.eps_name} placeholder="Ej: Nueva EPS"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">AFP (pensión)</label>
            <input type="text" bind:value={$form.afp_name} placeholder="Ej: Porvenir"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">ARL</label>
            <input type="text" bind:value={$form.arl_name} placeholder="Ej: Sura"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Caja de compensación</label>
            <input type="text" bind:value={$form.ccf_name} placeholder="Ej: Compensar"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
          </div>
        </div>
      </div>
      {/if}

      <!-- Botones -->
      <div class="flex justify-end gap-3">
        <a href="/payroll/employees"
           class="px-5 py-2 text-sm font-medium text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50 transition">
          Cancelar
        </a>
        <button type="submit" disabled={$form.processing}
          class="px-6 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
          {$form.processing ? 'Guardando...' : (isEdit ? 'Guardar cambios' : 'Crear empleado')}
        </button>
      </div>

    </form>
  </div>
</AppLayout>
