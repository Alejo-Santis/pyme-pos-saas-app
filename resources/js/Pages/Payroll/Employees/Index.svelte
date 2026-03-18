<script>
  import { router } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';
  import ImportModal from '@/Components/UI/ImportModal.svelte';

  let { employees, filters } = $props();

  let search = $state(filters.search ?? '');
  let stateFilter = $state(filters.state ?? '');
  let searchTimeout = null;

  function doSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
      router.get('/payroll/employees', { search, state: stateFilter }, { preserveState: true, replace: true });
    }, 350);
  }

  function fmt(v) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);
  }

  const genderLabel = { 1: 'M', 2: 'F', 3: 'Otro' };

  let showImport = $state(false);
</script>

<AppLayout title="Empleados">
  <div class="p-6 space-y-5">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Empleados</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gestión del personal activo e inactivo</p>
      </div>
      <div class="flex items-center gap-2">
        <button onclick={() => showImport = true}
          class="inline-flex items-center gap-2 px-3 py-2 border border-slate-200 text-slate-600 text-sm rounded-lg hover:bg-slate-50 transition cursor-pointer">
          <i class="mdi mdi-file-import"></i> Importar
        </button>
        <a href="/payroll/employees/create"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
          <i class="mdi mdi-account-plus"></i> Nuevo Empleado
        </a>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
      <div class="relative flex-1 min-w-[220px]">
        <i class="mdi mdi-magnify absolute left-3 top-2.5 text-slate-400"></i>
        <input
          type="text"
          placeholder="Buscar por nombre o cédula..."
          bind:value={search}
          oninput={doSearch}
          class="w-full pl-9 pr-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
        />
      </div>
      <select
        bind:value={stateFilter}
        onchange={doSearch}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none"
      >
        <option value="">Todos los estados</option>
        <option value="1">Activos</option>
        <option value="0">Inactivos</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      {#if employees.data.length === 0}
        <div class="text-center py-16 text-slate-400">
          <i class="mdi mdi-account-group text-5xl block mb-3"></i>
          <p class="font-medium">No hay empleados registrados</p>
          <a href="/payroll/employees/create" class="text-blue-600 text-sm hover:underline mt-1 inline-block">Crear el primero</a>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
              <tr>
                <th class="px-4 py-3 font-semibold">Empleado</th>
                <th class="px-4 py-3 font-semibold">Identificación</th>
                <th class="px-4 py-3 font-semibold">Cargo</th>
                <th class="px-4 py-3 font-semibold">Salario</th>
                <th class="px-4 py-3 font-semibold">Período</th>
                <th class="px-4 py-3 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each employees.data as emp}
                {@const contract = emp.active_contract}
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm">
                        {emp.first_name[0]}{emp.last_name[0]}
                      </div>
                      <div>
                        <p class="font-medium text-slate-800">{emp.first_name} {emp.last_name}</p>
                        <p class="text-xs text-slate-400">{emp.email ?? ''}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-600">{emp.document_type} {emp.identification_number}</td>
                  <td class="px-4 py-3 text-slate-600">{contract?.job_title ?? '—'}</td>
                  <td class="px-4 py-3 font-medium text-slate-800">{contract ? fmt(contract.salary) : '—'}</td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{contract?.payroll_period_id === 1 ? 'Mensual' : contract?.payroll_period_id === 2 ? 'Quincenal' : '—'}</td>
                  <td class="px-4 py-3">
                    {#if emp.state}
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        <i class="mdi mdi-circle text-[8px]"></i> Activo
                      </span>
                    {:else}
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">
                        <i class="mdi mdi-circle text-[8px]"></i> Inactivo
                      </span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <a href="/payroll/employees/{emp.id}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Ver →</a>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        {#if employees.last_page > 1}
          <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
            <span>Mostrando {employees.from}–{employees.to} de {employees.total}</span>
            <div class="flex gap-1">
              {#each employees.links as link}
                {#if link.url}
                  <a href={link.url}
                     class="px-3 py-1 rounded border text-xs {link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50'}"
                     innerHTML={link.label}></a>
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

<ImportModal
  bind:open={showImport}
  uploadUrl="/payroll/employees/import"
  templateUrl="/payroll/employees/import/template"
  title="Importar Empleados"
  columns={['tipo_documento','numero_documento','primer_nombre','segundo_nombre','primer_apellido','segundo_apellido','email','telefono','ciudad','departamento','fecha_nacimiento','genero','tipo_contrato','cargo','salario','salario_integral','auxilio_transporte','clase_arl','fecha_inicio','eps','afp','arl','ccf']}
/>
