<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router, inertia } from '@inertiajs/svelte'

  let { tenants = { data: [] }, plans = [], filters = {} } = $props()

  let search  = $state(filters.search  ?? '')
  let status  = $state(filters.status  ?? '')
  let plan_id = $state(filters.plan_id ?? '')

  function applyFilters() {
    router.get('/admin/tenants', { search, status, plan_id }, { preserveScroll: true })
  }

  function clearFilters() {
    search = ''; status = ''; plan_id = ''
    router.get('/admin/tenants')
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
</script>

<AdminLayout>
  <div class="space-y-5">

    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-800">Empresas</h2>
        <p class="text-slate-500 text-sm">Total: {tenants.total ?? 0} empresas registradas</p>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
      <div class="flex flex-wrap gap-3 items-end">

        <div class="flex-1 min-w-48">
          <label class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <div class="relative">
            <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input
              type="text"
              bind:value={search}
              placeholder="Nombre o email..."
              onkeydown={(e) => e.key === 'Enter' && applyFilters()}
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Estado</label>
          <select
            bind:value={status}
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            <option value="">Todos</option>
            <option value="trial">Trial</option>
            <option value="active">Activo</option>
            <option value="suspended">Suspendido</option>
            <option value="cancelled">Cancelado</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Plan</label>
          <select
            bind:value={plan_id}
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            <option value="">Todos</option>
            {#each plans as plan}
              <option value={plan.id}>{plan.name}</option>
            {/each}
          </select>
        </div>

        <button
          onclick={applyFilters}
          class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
        >
          Filtrar
        </button>

        {#if filters.search || filters.status || filters.plan_id}
          <button onclick={clearFilters} class="text-slate-500 hover:text-slate-700 text-sm transition">
            Limpiar
          </button>
        {/if}
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Empresa</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Dominio</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Plan</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Estado</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Trial hasta</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Registrado</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {#if tenants.data.length === 0}
              <tr>
                <td colspan="7" class="text-center py-12 text-slate-400">
                  <i class="mdi mdi-domain-off text-3xl block mb-2"></i>
                  No hay empresas registradas
                </td>
              </tr>
            {:else}
              {#each tenants.data as tenant}
                <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                        <span class="text-primary text-xs font-bold">
                          {tenant.name?.charAt(0)?.toUpperCase()}
                        </span>
                      </div>
                      <div>
                        <p class="font-medium text-slate-800">{tenant.name}</p>
                        <p class="text-xs text-slate-400">{tenant.email}</p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-slate-600 text-xs font-mono">{tenant.domain}</td>
                  <td class="px-4 py-3 text-slate-600">{tenant.plan}</td>
                  <td class="px-4 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-medium {statusColor[tenant.status] ?? 'bg-slate-100 text-slate-600'}">
                      {statusLabel[tenant.status] ?? tenant.status}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{tenant.trial_ends_at ?? '—'}</td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{tenant.created_at}</td>
                  <td class="px-4 py-3">
                    <a
                      use:inertia
                      href="/admin/tenants/{tenant.id}"
                      class="text-primary hover:text-primary-dark transition"
                      title="Ver detalle"
                    >
                      <i class="mdi mdi-chevron-right text-xl"></i>
                    </a>
                  </td>
                </tr>
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      {#if tenants.last_page > 1}
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">
            Mostrando {tenants.from}–{tenants.to} de {tenants.total}
          </p>
          <div class="flex gap-1">
            {#each tenants.links as link}
              {#if link.url}
                <a
                  use:inertia
                  href={link.url}
                  class="px-3 py-1 rounded text-xs transition
                    {link.active
                      ? 'bg-primary text-white font-medium'
                      : 'text-slate-600 hover:bg-slate-100'}"
                >
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

  </div>
</AdminLayout>
