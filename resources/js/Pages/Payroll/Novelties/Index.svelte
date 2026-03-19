<script>
  import { useForm, router } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte';

  let { novelties, employees, overtimeTypes, disabilityTypes, filters } = $props();

  let showModal = $state(false);
  let employeeFilter = $state(filters.employee_id ?? '');
  let typeFilter = $state(filters.type ?? '');
  let pendingOnly = $state(filters.pending === '1');

  const form = useForm({
    employee_id:        '',
    type:               'overtime',
    overtime_type_id:   '',
    overtime_hours:     '',
    disability_type_id: '',
    disability_days:    '',
    vacation_days:      '',
    unpaid_leave_days:  '',
    amount:             '',
    date_from:          new Date().toISOString().split('T')[0],
    date_to:            '',
    description:        '',
  });

  function doFilter() {
    router.get('/payroll/novelties', {
      employee_id: employeeFilter,
      type: typeFilter,
      pending: pendingOnly ? '1' : '',
    }, { preserveState: true, replace: true });
  }

  function submitNovelty() {
    form.post('/payroll/novelties', {
      onSuccess: () => { showModal = false; form.reset(); }
    });
  }

  let confirmDelete = $state({ open: false, id: null });

  function deleteNovelty(id) {
    confirmDelete = { open: true, id };
  }

  const fmt = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);
  const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short' }) : '—';

  const typeLabels = {
    overtime:     'Hora extra / Recargo',
    disability:   'Incapacidad',
    unpaid_leave: 'Permiso no remunerado',
    commission:   'Comisión',
    bonus:        'Bonificación',
    loan:         'Libranza',
    vacation:     'Vacaciones',
    other:        'Otro',
  };

  const typeColors = {
    overtime:     'bg-blue-100 text-blue-700',
    disability:   'bg-orange-100 text-orange-700',
    unpaid_leave: 'bg-slate-100 text-slate-600',
    commission:   'bg-green-100 text-green-700',
    bonus:        'bg-purple-100 text-purple-700',
    loan:         'bg-red-100 text-red-700',
    vacation:     'bg-teal-100 text-teal-700',
    other:        'bg-gray-100 text-gray-600',
  };
</script>

<AppLayout title="Novedades de Nómina">
  <div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Novedades</h1>
        <p class="text-sm text-slate-500">Horas extra, incapacidades, comisiones y otras novedades</p>
      </div>
      <div class="flex gap-2">
        <a href="/payroll/runs" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition">
          <i class="mdi mdi-calculator mr-1"></i> Liquidaciones
        </a>
        <button onclick={() => showModal = true}
          class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
          <i class="mdi mdi-plus"></i> Nueva novedad
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3 items-center">
      <select bind:value={employeeFilter} onchange={doFilter}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        <option value="">Todos los empleados</option>
        {#each employees as emp}
          <option value={emp.id}>{emp.first_name} {emp.last_name} — {emp.identification_number}</option>
        {/each}
      </select>
      <select bind:value={typeFilter} onchange={doFilter}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        <option value="">Todos los tipos</option>
        {#each Object.entries(typeLabels) as [k, v]}
          <option value={k}>{v}</option>
        {/each}
      </select>
      <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
        <input type="checkbox" bind:checked={pendingOnly} onchange={doFilter}
          class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
        Solo pendientes
      </label>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      {#if novelties.data.length === 0}
        <div class="text-center py-16 text-slate-400">
          <i class="mdi mdi-bell-off text-5xl block mb-3"></i>
          <p class="font-medium">No hay novedades registradas</p>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
              <tr>
                <th class="px-4 py-3 font-semibold">Empleado</th>
                <th class="px-4 py-3 font-semibold">Tipo</th>
                <th class="px-4 py-3 font-semibold">Detalle</th>
                <th class="px-4 py-3 font-semibold">Fecha</th>
                <th class="px-4 py-3 font-semibold text-right">Valor</th>
                <th class="px-4 py-3 font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each novelties.data as nov}
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{nov.employee?.first_name} {nov.employee?.last_name}</p>
                    <p class="text-xs text-slate-400">{nov.contract?.job_title ?? ''}</p>
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {typeColors[nov.type] ?? ''}">
                      {typeLabels[nov.type] ?? nov.type}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-slate-600">
                    {#if nov.type === 'overtime' && nov.overtime_hours}
                      {nov.overtime_hours}h
                    {:else if nov.type === 'disability' && nov.disability_days}
                      {nov.disability_days} días
                    {:else if nov.type === 'vacation' && nov.vacation_days}
                      {nov.vacation_days} días
                    {:else if nov.type === 'unpaid_leave' && nov.unpaid_leave_days}
                      {nov.unpaid_leave_days} días
                    {:else}
                      {nov.description ?? '—'}
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{fmtDate(nov.date_from)}{nov.date_to ? ` → ${fmtDate(nov.date_to)}` : ''}</td>
                  <td class="px-4 py-3 text-right font-medium">{nov.amount > 0 ? fmt(nov.amount) : '—'}</td>
                  <td class="px-4 py-3">
                    {#if nov.is_processed}
                      <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Liquidada</span>
                    {:else}
                      <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700">Pendiente</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-right">
                    {#if !nov.is_processed}
                      <button onclick={() => deleteNovelty(nov.id)}
                        class="text-red-400 hover:text-red-600 text-xs">
                        <i class="mdi mdi-delete-outline"></i>
                      </button>
                    {/if}
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        {#if novelties.last_page > 1}
          <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
            <span>{novelties.from}–{novelties.to} de {novelties.total}</span>
            <div class="flex gap-1">
              {#each novelties.links as link}
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

  <!-- Modal nueva novedad -->
  {#if showModal}
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-bold text-slate-800">Nueva novedad</h3>
          <button onclick={() => showModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Empleado *</label>
            <select bind:value={$form.employee_id}
              class="w-full px-3 py-2 text-sm border rounded-lg outline-none focus:border-blue-500 {$form.errors.employee_id ? 'border-red-400' : 'border-slate-300'}">
              <option value="">— Seleccionar —</option>
              {#each employees as emp}
                <option value={emp.id}>{emp.first_name} {emp.last_name}</option>
              {/each}
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de novedad *</label>
            <select bind:value={$form.type}
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500">
              {#each Object.entries(typeLabels) as [k, v]}
                <option value={k}>{v}</option>
              {/each}
            </select>
          </div>

          <!-- Campos dinámicos según tipo -->
          {#if $form.type === 'overtime'}
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de hora extra *</label>
                <select bind:value={$form.overtime_type_id}
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500">
                  <option value="">— Seleccionar —</option>
                  {#each overtimeTypes as ot}
                    <option value={ot.id}>{ot.name}</option>
                  {/each}
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Horas *</label>
                <input type="number" step="0.5" min="0.5" bind:value={$form.overtime_hours}
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
              </div>
            </div>

          {:else if $form.type === 'disability'}
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Tipo incapacidad *</label>
                <select bind:value={$form.disability_type_id}
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500">
                  <option value="">— Seleccionar —</option>
                  {#each disabilityTypes as dt}
                    <option value={dt.id}>{dt.name}</option>
                  {/each}
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Días *</label>
                <input type="number" min="1" bind:value={$form.disability_days}
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
              </div>
            </div>

          {:else if $form.type === 'vacation'}
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Días de vacaciones *</label>
              <input type="number" min="1" bind:value={$form.vacation_days}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
            </div>

          {:else if $form.type === 'unpaid_leave'}
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Días de permiso *</label>
              <input type="number" min="1" bind:value={$form.unpaid_leave_days}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
            </div>

          {:else}
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Valor</label>
              <div class="relative">
                <span class="absolute left-3 top-2 text-slate-400 text-sm">$</span>
                <input type="number" min="0" bind:value={$form.amount}
                  class="w-full pl-7 pr-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
              </div>
            </div>
          {/if}

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Fecha desde *</label>
              <input type="date" bind:value={$form.date_from}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Fecha hasta</label>
              <input type="date" bind:value={$form.date_to}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Descripción / observaciones</label>
            <textarea bind:value={$form.description} rows="2"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 resize-none"></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button onclick={() => showModal = false}
            class="px-4 py-2 text-sm text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50">
            Cancelar
          </button>
          <button onclick={submitNovelty} disabled={$form.processing}
            class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-60">
            {$form.processing ? 'Guardando...' : 'Guardar novedad'}
          </button>
        </div>

      </div>
    </div>
  {/if}
</AppLayout>

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar novedad"
  message="¿Eliminar esta novedad?"
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete(`/payroll/novelties/${confirmDelete.id}`)}
/>
