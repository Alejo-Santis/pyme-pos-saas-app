<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router, inertia } from '@inertiajs/svelte'

  let { subscriptions = { data: [] }, summary = {}, plans = [], filters = {}, recentPayments = [] } = $props()

  let search = $state(filters.search ?? '')
  let status = $state(filters.status ?? '')
  let plan_id = $state(filters.plan_id ?? '')
  let paymentModalOpen = $state(false)
  let selectedSubscription = $state(null)
  let paymentForm = $state({
    subscription_id: '',
    amount: '',
    currency: 'COP',
    payment_method: 'manual',
    reference: '',
    paid_at: '',
    notes: '',
  })

  function formatCurrency(value) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(Number(value ?? 0))
  }

  function applyFilters() {
    router.get('/admin/billing', { search, status, plan_id }, { preserveScroll: true })
  }

  function clearFilters() {
    search = ''
    status = ''
    plan_id = ''
    router.get('/admin/billing', {}, { replace: true })
  }

  function openPaymentModal(sub) {
    selectedSubscription = sub
    paymentForm = {
      subscription_id: sub.id,
      amount: sub.price ?? '',
      currency: 'COP',
      payment_method: 'manual',
      reference: '',
      paid_at: new Date().toISOString().slice(0, 16),
      notes: '',
    }
    paymentModalOpen = true
  }

  function closePaymentModal() {
    paymentModalOpen = false
    selectedSubscription = null
  }

  function submitPayment() {
    router.post('/admin/billing/payments', paymentForm, {
      preserveScroll: true,
      onSuccess: closePaymentModal,
    })
  }

  const statusColor = {
    active: 'bg-emerald-100 text-emerald-700',
    trial: 'bg-blue-100 text-blue-700',
    past_due: 'bg-amber-100 text-amber-700',
    cancelled: 'bg-red-100 text-red-700',
    expired: 'bg-slate-100 text-slate-500',
  }

  const statusLabel = {
    active: 'Activa',
    trial: 'Trial',
    past_due: 'En mora',
    cancelled: 'Cancelada',
    expired: 'Expirada',
  }
</script>

<AdminLayout>
  <div class="space-y-5">
    <div>
      <h2 class="text-xl font-bold text-slate-800">Cartera SaaS</h2>
      <p class="text-slate-500 text-sm">Suscripciones, estados y MRR estimado</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">MRR estimado</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{formatCurrency(summary.mrr)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">Cobrado mes</p>
        <p class="mt-1 text-2xl font-bold text-slate-800">{formatCurrency(summary.paid_month)}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">Activas</p>
        <p class="mt-1 text-2xl font-bold text-emerald-600">{summary.active ?? 0}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">Trial</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{summary.trial ?? 0}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">En mora</p>
        <p class="mt-1 text-2xl font-bold text-amber-600">{summary.past_due ?? 0}</p>
      </div>
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs font-semibold uppercase text-slate-400">Canceladas</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{summary.cancelled ?? 0}</p>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
          <label for="billing-search" class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <input
            id="billing-search"
            bind:value={search}
            type="text"
            placeholder="Empresa o email..."
            onkeydown={(e) => e.key === 'Enter' && applyFilters()}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          />
        </div>
        <div>
          <label for="billing-status" class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
          <select id="billing-status" bind:value={status} class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Todos</option>
            <option value="active">Activa</option>
            <option value="trial">Trial</option>
            <option value="past_due">En mora</option>
            <option value="cancelled">Cancelada</option>
            <option value="expired">Expirada</option>
          </select>
        </div>
        <div>
          <label for="billing-plan" class="block text-xs font-medium text-slate-600 mb-1">Plan</label>
          <select id="billing-plan" bind:value={plan_id} class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Todos</option>
            {#each plans as plan}
              <option value={plan.id}>{plan.name}</option>
            {/each}
          </select>
        </div>
        <button onclick={applyFilters} class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
          Filtrar
        </button>
        {#if filters.search || filters.status || filters.plan_id}
          <button onclick={clearFilters} class="text-slate-500 hover:text-slate-700 text-sm transition">Limpiar</button>
        {/if}
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Empresa</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Plan</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Estado</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Precio</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Vigencia</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {#if subscriptions.data.length === 0}
              <tr>
                <td colspan="6" class="text-center py-12 text-slate-400">
                  <i class="mdi mdi-credit-card-off-outline text-3xl block mb-2"></i>
                  Sin suscripciones
                </td>
              </tr>
            {:else}
              {#each subscriptions.data as sub}
                <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{sub.tenant_name}</p>
                    <p class="text-xs text-slate-400">{sub.tenant_email}</p>
                    <p class="text-xs text-slate-400 font-mono">{sub.tenant_domain}</p>
                  </td>
                  <td class="px-4 py-3 text-slate-600">{sub.plan}</td>
                  <td class="px-4 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold {statusColor[sub.status] ?? 'bg-slate-100 text-slate-500'}">
                      {statusLabel[sub.status] ?? sub.status}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-700">{formatCurrency(sub.price)}</p>
                    <p class="text-xs text-slate-400">{sub.billing_period === 'yearly' ? 'Anual' : 'Mensual'}</p>
                  </td>
                  <td class="px-4 py-3 text-xs text-slate-500">
                    <p>Inicio: {sub.starts_at ?? '—'}</p>
                    <p>Fin: {sub.ends_at ?? '—'}</p>
                    <p>Trial: {sub.trial_ends_at ?? '—'}</p>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <button onclick={() => openPaymentModal(sub)} class="text-emerald-600 hover:text-emerald-700 transition mr-2" title="Registrar pago">
                      <i class="mdi mdi-cash-plus text-xl"></i>
                    </button>
                    <a use:inertia href="/admin/tenants/{sub.tenant_id}" class="text-primary hover:text-primary-dark transition" title="Ver tenant">
                      <i class="mdi mdi-chevron-right text-xl"></i>
                    </a>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      {#if subscriptions.last_page > 1}
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Mostrando {subscriptions.from}–{subscriptions.to} de {subscriptions.total}</p>
          <div class="flex gap-1">
            {#each subscriptions.links as link}
              {#if link.url}
                <a use:inertia href={link.url} class="px-3 py-1 rounded text-xs transition {link.active ? 'bg-primary text-white font-medium' : 'text-slate-600 hover:bg-slate-100'}">
                  {@html link.label}
                </a>
              {:else}
                <span class="px-3 py-1 rounded text-xs text-slate-300">{@html link.label}</span>
              {/if}
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100">
        <h3 class="text-sm font-semibold text-slate-700">Pagos recientes</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-3">Empresa</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-3">Plan</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-3">Pago</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-3">Referencia</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase px-4 py-3">Fecha</th>
            </tr>
          </thead>
          <tbody>
            {#if recentPayments.length === 0}
              <tr>
                <td colspan="5" class="text-center py-8 text-slate-400">Sin pagos registrados</td>
              </tr>
            {:else}
              {#each recentPayments as payment}
                <tr class="border-b border-slate-50">
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-800">{payment.tenant_name}</p>
                    <p class="text-xs text-slate-400 font-mono">{payment.tenant_domain}</p>
                  </td>
                  <td class="px-4 py-3 text-slate-600">{payment.plan}</td>
                  <td class="px-4 py-3">
                    <p class="font-semibold text-slate-700">{formatCurrency(payment.amount)}</p>
                    <p class="text-xs text-slate-400">{payment.payment_method ?? 'manual'}</p>
                  </td>
                  <td class="px-4 py-3 text-slate-500">{payment.reference ?? '—'}</td>
                  <td class="px-4 py-3 text-slate-500">{payment.paid_at ?? '—'}</td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {#if paymentModalOpen}
    <div class="fixed inset-0 bg-slate-900/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-xl shadow-xl border border-slate-100 w-full max-w-lg">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 class="font-semibold text-slate-800">Registrar pago manual</h3>
            <p class="text-xs text-slate-400">{selectedSubscription?.tenant_name} · {selectedSubscription?.plan}</p>
          </div>
          <button onclick={closePaymentModal} class="text-slate-400 hover:text-slate-600" title="Cerrar">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>

        <form onsubmit={(e) => { e.preventDefault(); submitPayment() }} class="p-5 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="payment-amount" class="block text-xs font-medium text-slate-600 mb-1">Valor</label>
              <input id="payment-amount" bind:value={paymentForm.amount} type="number" min="0" step="0.01" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>
            <div>
              <label for="payment-currency" class="block text-xs font-medium text-slate-600 mb-1">Moneda</label>
              <input id="payment-currency" bind:value={paymentForm.currency} maxlength="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label for="payment-method" class="block text-xs font-medium text-slate-600 mb-1">Método</label>
              <select id="payment-method" bind:value={paymentForm.payment_method} class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <option value="manual">Manual</option>
                <option value="transfer">Transferencia</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
              </select>
            </div>
            <div>
              <label for="payment-date" class="block text-xs font-medium text-slate-600 mb-1">Fecha</label>
              <input id="payment-date" bind:value={paymentForm.paid_at} type="datetime-local" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
            </div>
          </div>
          <div>
            <label for="payment-reference" class="block text-xs font-medium text-slate-600 mb-1">Referencia</label>
            <input id="payment-reference" bind:value={paymentForm.reference} type="text" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" />
          </div>
          <div>
            <label for="payment-notes" class="block text-xs font-medium text-slate-600 mb-1">Notas</label>
            <textarea id="payment-notes" bind:value={paymentForm.notes} rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" onclick={closePaymentModal} class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition">Cancelar</button>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white hover:bg-primary-dark transition">Guardar pago</button>
          </div>
        </form>
      </div>
    </div>
  {/if}
</AdminLayout>
