<script>
  import { router, useForm } from '@inertiajs/svelte';
  import AppLayout from '@/Layouts/AppLayout.svelte';
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte';
  import ExportButtons from '@/Components/UI/ExportButtons.svelte';

  let { run, labels } = $props();

  let expandedEmployee = $state(null);
  let showCancelModal = $state(false);
  const cancelForm = useForm({ reason: '' });
  let confirmApprove = $state(false);
  let confirmMarkPaid = $state(false);

  function doApprove() {
    router.post(`/payroll/runs/${run.id}/approve`, {}, { preserveScroll: true })
  }

  function doMarkPaid() {
    router.post(`/payroll/runs/${run.id}/mark-paid`, {}, { preserveScroll: true })
  }

  function doCancel() {
    cancelForm.post(`/payroll/runs/${run.id}/cancel`, {
      preserveScroll: true,
      onSuccess: () => { showCancelModal = false }
    })
  }

  const fmt = v => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0);
  const fmtDate = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';

  const statusConfig = {
    draft:     { label: 'Borrador',  color: 'bg-yellow-100 text-yellow-700', icon: 'mdi-pencil-outline' },
    approved:  { label: 'Aprobada',  color: 'bg-blue-100 text-blue-700',    icon: 'mdi-check-circle-outline' },
    paid:      { label: 'Pagada',    color: 'bg-green-100 text-green-700',  icon: 'mdi-cash-check' },
    cancelled: { label: 'Anulada',   color: 'bg-red-100 text-red-700',      icon: 'mdi-cancel' },
  };
  const sc = statusConfig[run.status] ?? statusConfig.draft;
</script>

<AppLayout title="Liquidación {run.name}">
  <div class="p-6 space-y-5">

    <!-- Encabezado -->
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-3">
        <a href="/payroll/runs" class="text-slate-400 hover:text-slate-600">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-800">{run.name}</h1>
            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium {sc.color}">
              <i class="mdi {sc.icon}"></i> {sc.label}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            Período: {fmtDate(run.period_start)} — {fmtDate(run.period_end)}
          </p>
        </div>
      </div>

      <!-- Acciones según estado -->
      <div class="flex gap-2 items-center">
        <ExportButtons baseUrl={`/payroll/runs/${run.id}/export`} params={{}} />
        {#if run.status === 'draft'}
          <button onclick={() => confirmApprove = true}
            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
            <i class="mdi mdi-check"></i> Aprobar
          </button>
          <button onclick={() => showCancelModal = true}
            class="px-4 py-2 text-sm font-medium text-red-600 border border-red-300 rounded-lg hover:bg-red-50 transition">
            Anular
          </button>
        {:else if run.status === 'approved'}
          <button onclick={() => confirmMarkPaid = true}
            class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
            <i class="mdi mdi-cash-check"></i> Marcar pagada
          </button>
          <button onclick={() => showCancelModal = true}
            class="px-4 py-2 text-sm font-medium text-red-600 border border-red-300 rounded-lg hover:bg-red-50 transition">
            Anular
          </button>
        {/if}
      </div>
    </div>

    <!-- Resumen financiero -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Total devengado</p>
        <p class="text-xl font-bold text-slate-800 mt-1">{fmt(run.total_earned)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Total deducciones</p>
        <p class="text-xl font-bold text-red-600 mt-1">{fmt(run.total_deductions)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Neto a pagar</p>
        <p class="text-xl font-bold text-green-700 mt-1">{fmt(run.total_net)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Costo empleador</p>
        <p class="text-xl font-bold text-orange-600 mt-1">{fmt(run.total_employer_cost)}</p>
        <p class="text-xs text-slate-400 mt-0.5">incl. parafiscales</p>
      </div>
    </div>

    <!-- Detalle por empleado -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-semibold text-slate-700">Detalle por empleado ({run.details.length})</h2>
        <p class="text-xs text-slate-400">Clic en una fila para ver el desglose completo</p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50/60 text-slate-500 text-left text-xs">
            <tr>
              <th class="px-4 py-2 font-semibold">Empleado</th>
              <th class="px-4 py-2 font-semibold text-center">Días</th>
              <th class="px-4 py-2 font-semibold text-right">Salario</th>
              <th class="px-4 py-2 font-semibold text-right">Transp.</th>
              <th class="px-4 py-2 font-semibold text-right">Extras/Com.</th>
              <th class="px-4 py-2 font-semibold text-right">Devengado</th>
              <th class="px-4 py-2 font-semibold text-right">Deducciones</th>
              <th class="px-4 py-2 font-semibold text-right">Neto</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each run.details as d}
              <tr
                class="hover:bg-slate-50 cursor-pointer transition"
                onclick={() => expandedEmployee = expandedEmployee === d.id ? null : d.id}
              >
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                      {d.employee?.first_name?.[0]}{d.employee?.last_name?.[0]}
                    </div>
                    <div>
                      <p class="font-medium text-slate-800">{d.employee?.first_name} {d.employee?.last_name}</p>
                      <p class="text-xs text-slate-400">{d.contract?.job_title ?? ''}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 text-center text-slate-500">{d.worked_days}</td>
                <td class="px-4 py-3 text-right text-slate-600">{fmt(d.basic_salary)}</td>
                <td class="px-4 py-3 text-right text-slate-500">{fmt(d.transport_allowance)}</td>
                <td class="px-4 py-3 text-right text-slate-500">{fmt(d.overtime_amount + d.commissions + d.bonuses)}</td>
                <td class="px-4 py-3 text-right font-medium text-slate-700">{fmt(d.total_earned)}</td>
                <td class="px-4 py-3 text-right text-red-500">({fmt(d.total_deductions)})</td>
                <td class="px-4 py-3 text-right font-bold text-green-700">{fmt(d.net_pay)}</td>
                <td class="px-4 py-3 text-center">
                  <i class="mdi {expandedEmployee === d.id ? 'mdi-chevron-up' : 'mdi-chevron-down'} text-slate-400"></i>
                </td>
              </tr>

              <!-- Desglose expandido -->
              {#if expandedEmployee === d.id}
                <tr>
                  <td colspan="9" class="bg-slate-50 px-6 py-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">

                      <!-- Devengados -->
                      <div>
                        <p class="font-semibold text-slate-600 mb-2 flex items-center gap-1">
                          <i class="mdi mdi-plus-circle-outline text-green-500"></i> DEVENGADOS
                        </p>
                        <div class="space-y-1">
                          <div class="flex justify-between"><span class="text-slate-500">Salario básico</span><span>{fmt(d.basic_salary)}</span></div>
                          {#if d.transport_allowance > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Auxilio transporte</span><span>{fmt(d.transport_allowance)}</span></div>
                          {/if}
                          {#if d.overtime_amount > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Horas extra / recargos</span><span>{fmt(d.overtime_amount)}</span></div>
                          {/if}
                          {#if d.commissions > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Comisiones</span><span>{fmt(d.commissions)}</span></div>
                          {/if}
                          {#if d.bonuses > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Bonificaciones</span><span>{fmt(d.bonuses)}</span></div>
                          {/if}
                          {#if d.vacation_amount > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Vacaciones</span><span>{fmt(d.vacation_amount)}</span></div>
                          {/if}
                          {#if d.disability_amount > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Incapacidad (empresa)</span><span>{fmt(d.disability_amount)}</span></div>
                          {/if}
                          <div class="flex justify-between font-semibold border-t border-slate-200 pt-1 mt-1">
                            <span>Total devengado</span><span class="text-green-700">{fmt(d.total_earned)}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Deducciones empleado -->
                      <div>
                        <p class="font-semibold text-slate-600 mb-2 flex items-center gap-1">
                          <i class="mdi mdi-minus-circle-outline text-red-500"></i> DEDUCCIONES EMPLEADO
                        </p>
                        <div class="space-y-1">
                          <div class="flex justify-between"><span class="text-slate-500">Salud (4%)</span><span class="text-red-500">({fmt(d.health_employee)})</span></div>
                          <div class="flex justify-between"><span class="text-slate-500">Pensión (4%)</span><span class="text-red-500">({fmt(d.pension_employee)})</span></div>
                          {#if d.solidarity_fund > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Fondo solidaridad</span><span class="text-red-500">({fmt(d.solidarity_fund)})</span></div>
                          {/if}
                          {#if d.income_tax_withholding > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Retención fuente</span><span class="text-red-500">({fmt(d.income_tax_withholding)})</span></div>
                          {/if}
                          {#if d.voluntary_health_deduction > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Salud voluntaria</span><span class="text-red-500">({fmt(d.voluntary_health_deduction)})</span></div>
                          {/if}
                          {#if d.voluntary_pension_deduction > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Pensión voluntaria</span><span class="text-red-500">({fmt(d.voluntary_pension_deduction)})</span></div>
                          {/if}
                          {#if d.loans_deduction > 0}
                            <div class="flex justify-between"><span class="text-slate-500">Libranzas</span><span class="text-red-500">({fmt(d.loans_deduction)})</span></div>
                          {/if}
                          <div class="flex justify-between font-semibold border-t border-slate-200 pt-1 mt-1">
                            <span>Total deducciones</span><span class="text-red-600">({fmt(d.total_deductions)})</span>
                          </div>
                          <div class="flex justify-between font-bold text-sm border-t-2 border-slate-300 pt-1 mt-1">
                            <span>NETO A PAGAR</span><span class="text-green-700">{fmt(d.net_pay)}</span>
                          </div>
                        </div>
                      </div>

                      <!-- Costo empleador -->
                      <div>
                        <p class="font-semibold text-slate-600 mb-2 flex items-center gap-1">
                          <i class="mdi mdi-office-building-outline text-orange-500"></i> APORTES EMPLEADOR
                        </p>
                        <div class="space-y-1">
                          <div class="flex justify-between"><span class="text-slate-500">Salud (8.5%)</span><span>{fmt(d.health_employer)}</span></div>
                          <div class="flex justify-between"><span class="text-slate-500">Pensión (12%)</span><span>{fmt(d.pension_employer)}</span></div>
                          <div class="flex justify-between"><span class="text-slate-500">ARL</span><span>{fmt(d.arl_employer)}</span></div>
                          <div class="flex justify-between"><span class="text-slate-500">CCF (4%)</span><span>{fmt(d.ccf_employer)}</span></div>
                          {#if d.sena_employer > 0}
                            <div class="flex justify-between"><span class="text-slate-500">SENA (2%)</span><span>{fmt(d.sena_employer)}</span></div>
                          {/if}
                          {#if d.icbf_employer > 0}
                            <div class="flex justify-between"><span class="text-slate-500">ICBF (3%)</span><span>{fmt(d.icbf_employer)}</span></div>
                          {/if}
                          <div class="flex justify-between font-semibold border-t border-slate-200 pt-1 mt-1">
                            <span>Costo total empresa</span><span class="text-orange-600">{fmt(d.total_employer_cost)}</span>
                          </div>
                        </div>
                      </div>

                    </div>
                  </td>
                </tr>
              {/if}
            {/each}
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Modal anular -->
  {#if showCancelModal}
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-slate-800">Anular liquidación</h3>
        <p class="text-sm text-slate-500">Las novedades incluidas quedarán disponibles para la siguiente liquidación.</p>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Motivo de anulación</label>
          <textarea bind:value={$cancelForm.reason} rows="3"
            class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            placeholder="Opcional"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button onclick={() => showCancelModal = false}
            class="px-4 py-2 text-sm text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50">
            Cancelar
          </button>
          <button onclick={doCancel} disabled={$cancelForm.processing}
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-60">
            {$cancelForm.processing ? 'Anulando...' : 'Confirmar anulación'}
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>

<ConfirmModal
  bind:open={confirmApprove}
  title="Aprobar liquidación"
  message="¿Aprobar esta liquidación?"
  confirmLabel="Aprobar"
  danger={false}
  onConfirm={doApprove}
/>

<ConfirmModal
  bind:open={confirmMarkPaid}
  title="Marcar como pagada"
  message="¿Marcar como pagada esta liquidación?"
  confirmLabel="Marcar pagada"
  danger={false}
  onConfirm={doMarkPaid}
/>
