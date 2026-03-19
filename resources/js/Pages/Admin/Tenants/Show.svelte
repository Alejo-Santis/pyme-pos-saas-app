<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router, inertia } from '@inertiajs/svelte'

  let { tenant = {}, subscriptions = [], plans = [] } = $props()

  let selectedStatus = $state(tenant.status)
  let selectedPlan   = $state(tenant.plan_id)
  let confirmDelete  = $state(false)

  function updateStatus() {
    router.patch(`/admin/tenants/${tenant.id}/status`, { status: selectedStatus }, { preserveScroll: true })
  }

  function updatePlan() {
    router.patch(`/admin/tenants/${tenant.id}/plan`, { plan_id: selectedPlan }, { preserveScroll: true })
  }

  const statusColor = {
    active:    'bg-emerald-100 text-emerald-700',
    trial:     'bg-blue-100 text-blue-700',
    suspended: 'bg-amber-100 text-amber-700',
    cancelled: 'bg-red-100 text-red-700',
  }

  const statusLabel = {
    active:    'Activo',
    trial:     'Trial',
    suspended: 'Suspendido',
    cancelled: 'Cancelado',
  }

  const subStatusColor = {
    active:   'text-emerald-600',
    trial:    'text-blue-600',
    past_due: 'text-amber-600',
    cancelled:'text-red-600',
    expired:  'text-slate-400',
  }
</script>

<AdminLayout>
  <div class="space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-4">
      <a use:inertia href="/admin/tenants" class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div class="flex-1">
        <h2 class="text-xl font-bold text-slate-800">{tenant.name}</h2>
        <p class="text-slate-500 text-sm">{tenant.email} · {tenant.domain}</p>
      </div>
      <span class="text-sm px-3 py-1 rounded-full font-medium {statusColor[tenant.status] ?? 'bg-slate-100 text-slate-600'}">
        {statusLabel[tenant.status] ?? tenant.status}
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Col izquierda: info + acciones -->
      <div class="space-y-4">

        <!-- Info general -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Información general</h3>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500">ID</dt>
              <dd class="text-slate-700 font-mono text-xs">{tenant.id?.substring(0,8)}...</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Dominio</dt>
              <dd class="text-slate-700 font-mono text-xs">{tenant.domain}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Plan actual</dt>
              <dd class="text-slate-700 font-medium">{tenant.plan}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Trial hasta</dt>
              <dd class="text-slate-700">{tenant.trial_ends_at ?? '—'}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Registrado</dt>
              <dd class="text-slate-700">{tenant.created_at}</dd>
            </div>
          </dl>
        </div>

        <!-- Cambiar estado -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Cambiar estado</h3>
          <select
            bind:value={selectedStatus}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            <option value="trial">Trial</option>
            <option value="active">Activo</option>
            <option value="suspended">Suspendido</option>
            <option value="cancelled">Cancelado</option>
          </select>
          <button
            onclick={updateStatus}
            class="w-full bg-primary text-white py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
          >
            Actualizar estado
          </button>
        </div>

        <!-- Cambiar plan -->
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Cambiar plan</h3>
          <select
            bind:value={selectedPlan}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            {#each plans as plan}
              <option value={plan.id}>{plan.name} — ${plan.price_monthly}/mes</option>
            {/each}
          </select>
          <button
            onclick={updatePlan}
            class="w-full bg-slate-700 text-white py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition"
          >
            Cambiar plan
          </button>
        </div>

      </div>

      <!-- Col derecha: historial suscripciones -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Historial de suscripciones</h3>

          {#if subscriptions.length === 0}
            <p class="text-slate-400 text-sm text-center py-8">Sin suscripciones registradas</p>
          {:else}
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-slate-50">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Plan</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Estado</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Precio</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Inicio</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Fin</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Trial hasta</th>
                  </tr>
                </thead>
                <tbody>
                  {#each subscriptions as sub}
                    <tr class="border-t border-slate-50 hover:bg-slate-50 transition-colors">
                      <td class="px-3 py-2.5 font-medium text-slate-700">{sub.plan}</td>
                      <td class="px-3 py-2.5">
                        <span class="text-xs font-semibold capitalize {subStatusColor[sub.status] ?? 'text-slate-500'}">
                          {sub.status}
                        </span>
                      </td>
                      <td class="px-3 py-2.5 text-slate-600">
                        {sub.price ? `$${sub.price}` : '—'}
                      </td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.starts_at ?? '—'}</td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.ends_at ?? '—'}</td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.trial_ends ?? '—'}</td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>
      </div>

    </div>
  </div>
</AdminLayout>
