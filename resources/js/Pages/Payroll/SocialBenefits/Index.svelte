<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let { benefits, employees, filters, types } = $props()

  let employeeFilter = $state(filters.employee_id ?? '')
  let typeFilter     = $state(filters.type ?? '')
  let yearFilter     = $state(filters.year ?? '')
  let paidFilter     = $state(filters.paid ?? '')

  let payModal   = $state(null)   // benefit being paid
  let payDate    = $state('')
  let payAmount  = $state('')

  function applyFilters() {
    router.get('/payroll/benefits', {
      employee_id: employeeFilter,
      type: typeFilter,
      year: yearFilter,
      paid: paidFilter,
    }, { preserveState: true, replace: true })
  }

  function openPayModal(benefit) {
    payModal  = benefit
    payDate   = new Date().toISOString().slice(0, 10)
    payAmount = benefit.amount
  }

  function submitPay() {
    router.post(`/payroll/benefits/${payModal.id}/pay`, {
      pay_date:    payDate,
      paid_amount: payAmount,
    }, {
      onSuccess: () => { payModal = null }
    })
  }

  let confirmDelete = $state({ open: false, id: null })

  function destroy(id) {
    confirmDelete = { open: true, id }
  }

  function fmt(v) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(v ?? 0)
  }

  const typeBadge = {
    prima:               'bg-blue-50 text-blue-700 border-blue-200',
    cesantias:           'bg-purple-50 text-purple-700 border-purple-200',
    intereses_cesantias: 'bg-amber-50 text-amber-700 border-amber-200',
    vacaciones:          'bg-green-50 text-green-700 border-green-200',
  }
</script>

<AppLayout title="Prestaciones Sociales">
  <div class="p-6 space-y-5">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Prestaciones Sociales</h1>
        <p class="text-sm text-slate-500 mt-0.5">Prima, cesantías, intereses y vacaciones</p>
      </div>
      <a href="/payroll/benefits/calculate"
         class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
        <i class="mdi mdi-calculator"></i> Liquidar prestación
      </a>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 flex flex-wrap gap-3">
      <select bind:value={employeeFilter} onchange={applyFilters}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none min-w-44">
        <option value="">Todos los empleados</option>
        {#each employees as emp}
          <option value={emp.id}>{emp.last_name}, {emp.first_name}</option>
        {/each}
      </select>
      <select bind:value={typeFilter} onchange={applyFilters}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        <option value="">Todos los tipos</option>
        {#each Object.entries(types) as [k, v]}
          <option value={k}>{v}</option>
        {/each}
      </select>
      <input bind:value={yearFilter} oninput={applyFilters} type="number" placeholder="Año"
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none w-24" />
      <select bind:value={paidFilter} onchange={applyFilters}
        class="px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-blue-500 outline-none">
        <option value="">Todos</option>
        <option value="1">Pagadas</option>
        <option value="0">Pendientes</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      {#if benefits.data.length === 0}
        <div class="text-center py-16 text-slate-400">
          <i class="mdi mdi-cash-multiple text-5xl block mb-3"></i>
          <p>No hay prestaciones liquidadas</p>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600 text-left">
              <tr>
                <th class="px-4 py-3 font-semibold">Empleado</th>
                <th class="px-4 py-3 font-semibold">Tipo</th>
                <th class="px-4 py-3 font-semibold">Año / Sem.</th>
                <th class="px-4 py-3 font-semibold">Días</th>
                <th class="px-4 py-3 font-semibold text-right">Valor</th>
                <th class="px-4 py-3 font-semibold text-center">Estado</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each benefits.data as b}
                <tr class="hover:bg-slate-50 transition">
                  <td class="px-4 py-3 font-medium text-slate-800">
                    {b.employee?.first_name ?? ''} {b.employee?.last_name ?? ''}
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-xs border rounded-full px-2 py-0.5 font-medium {typeBadge[b.type] ?? ''}">
                      {types[b.type] ?? b.type}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-slate-600">
                    {b.year}{b.semester ? ` · S${b.semester}` : ''}
                  </td>
                  <td class="px-4 py-3 text-slate-600">{b.days_worked}</td>
                  <td class="px-4 py-3 text-right font-medium text-slate-800">{fmt(b.amount)}</td>
                  <td class="px-4 py-3 text-center">
                    {#if b.is_paid}
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        <i class="mdi mdi-check-circle text-[8px]"></i> Pagada
                      </span>
                    {:else}
                      <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                        <i class="mdi mdi-clock-outline text-[8px]"></i> Pendiente
                      </span>
                    {/if}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-1">
                      {#if !b.is_paid}
                        <button onclick={() => openPayModal(b)}
                          class="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition cursor-pointer">
                          Pagar
                        </button>
                        <button onclick={() => destroy(b.id)}
                          class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer">
                          <i class="mdi mdi-trash-can-outline text-base"></i>
                        </button>
                      {:else}
                        <span class="text-xs text-slate-400">{b.pay_date ?? ''}</span>
                      {/if}
                    </div>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        {#if benefits.last_page > 1}
          <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between text-sm text-slate-500">
            <span>Mostrando {benefits.from}–{benefits.to} de {benefits.total}</span>
            <div class="flex gap-1">
              {#each benefits.links as link}
                {#if link.url}
                  <a href={link.url}
                     class="px-3 py-1 rounded border text-xs {link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-200 hover:bg-slate-50'}">
                    {@html link.label}
                  </a>
                {:else}
                  <span class="px-3 py-1 rounded border border-slate-100 text-xs text-slate-300">{@html link.label}</span>
                {/if}
              {/each}
            </div>
          </div>
        {/if}
      {/if}
    </div>
  </div>
</AppLayout>

<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar liquidación"
  message="¿Eliminar esta liquidación?"
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={() => router.delete(`/payroll/benefits/${confirmDelete.id}`, { preserveScroll: true })}
/>

<!-- Modal pago -->
{#if payModal}
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
      <h3 class="text-lg font-bold text-slate-800">Registrar Pago</h3>
      <p class="text-sm text-slate-500">{types[payModal.type]} — {payModal.employee?.first_name} {payModal.employee?.last_name}</p>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Fecha de pago</label>
          <input type="date" bind:value={payDate}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Valor pagado</label>
          <input type="number" bind:value={payAmount} step="0.01"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500" />
        </div>
      </div>

      <div class="flex gap-3 justify-end pt-2">
        <button onclick={() => payModal = null}
          class="px-4 py-2 text-sm border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          Cancelar
        </button>
        <button onclick={submitPay}
          class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition cursor-pointer">
          Confirmar pago
        </button>
      </div>
    </div>
  </div>
{/if}
