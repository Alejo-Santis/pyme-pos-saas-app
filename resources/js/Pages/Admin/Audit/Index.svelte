<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router } from '@inertiajs/svelte'

  let { logs = { data: [] }, filters = {}, modules = [], events = [] } = $props()

  let search = $state(filters.search ?? '')
  let module = $state(filters.module ?? '')
  let event = $state(filters.event ?? '')
  let expanded = $state(null)

  function applyFilters() {
    router.get('/admin/audit', { search, module, event }, { preserveScroll: true })
  }

  function clearFilters() {
    search = ''
    module = ''
    event = ''
    router.get('/admin/audit', {}, { replace: true })
  }

  function shortId(id) {
    return id ? `${id}`.slice(0, 8) : '—'
  }

  function pretty(value) {
    return JSON.stringify(value ?? {}, null, 2)
  }

  const eventLabel = {
    created: 'Creado',
    updated: 'Actualizado',
    deleted: 'Eliminado',
    login: 'Login',
    logout: 'Logout',
    password_updated: 'Contraseña',
    status_toggled: 'Estado',
    plan_updated: 'Plan',
    subscription_updated: 'Suscripción',
    trial_extended: 'Trial',
    tenant_status_updated: 'Estado tenant',
    domain_updated: 'Dominio',
    notification_sent: 'Notificación',
    technical_action_run: 'Acción técnica',
    impersonation_created: 'Impersonación',
    impersonation_consumed: 'Ingreso soporte',
  }
</script>

<AdminLayout>
  <div class="space-y-5">
    <div>
      <h2 class="text-xl font-bold text-slate-800">Auditoría</h2>
      <p class="text-slate-500 text-sm">Historial de acciones realizadas en el portal super admin</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
          <label for="audit-search" class="block text-xs font-medium text-slate-600 mb-1">Buscar</label>
          <div class="relative">
            <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input
              id="audit-search"
              type="text"
              bind:value={search}
              placeholder="Admin, evento o ID..."
              onkeydown={(e) => e.key === 'Enter' && applyFilters()}
              class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
            />
          </div>
        </div>

        <div>
          <label for="audit-module" class="block text-xs font-medium text-slate-600 mb-1">Módulo</label>
          <select id="audit-module" bind:value={module} class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Todos</option>
            {#each modules as item}
              <option value={item}>{item}</option>
            {/each}
          </select>
        </div>

        <div>
          <label for="audit-event" class="block text-xs font-medium text-slate-600 mb-1">Evento</label>
          <select id="audit-event" bind:value={event} class="border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Todos</option>
            {#each events as item}
              <option value={item}>{eventLabel[item] ?? item}</option>
            {/each}
          </select>
        </div>

        <button onclick={applyFilters} class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition">
          Filtrar
        </button>

        {#if filters.search || filters.module || filters.event}
          <button onclick={clearFilters} class="text-slate-500 hover:text-slate-700 text-sm transition">
            Limpiar
          </button>
        {/if}
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-100">
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Fecha</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Admin</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Acción</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">Registro</th>
              <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-4 py-3">IP</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {#if logs.data.length === 0}
              <tr>
                <td colspan="6" class="text-center py-12 text-slate-400">
                  <i class="mdi mdi-history text-3xl block mb-2"></i>
                  Sin eventos de auditoría
                </td>
              </tr>
            {:else}
              {#each logs.data as log}
                <tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                  <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{log.created_at}</td>
                  <td class="px-4 py-3">
                    <p class="font-medium text-slate-700">{log.admin_name}</p>
                    <p class="text-xs text-slate-400">{log.admin_email ?? '—'}</p>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold bg-blue-50 text-blue-700">
                      {eventLabel[log.event] ?? log.event}
                    </span>
                    <p class="text-xs text-slate-400 mt-1">{log.module}</p>
                  </td>
                  <td class="px-4 py-3">
                    <p class="text-slate-600">{log.auditable_type || '—'}</p>
                    <p class="text-xs text-slate-400 font-mono">{shortId(log.auditable_id)}</p>
                  </td>
                  <td class="px-4 py-3 text-slate-500 text-xs">{log.ip_address ?? '—'}</td>
                  <td class="px-4 py-3 text-right">
                    <button onclick={() => expanded = expanded === log.id ? null : log.id} class="text-primary hover:text-primary-dark transition" title="Ver detalle">
                      <i class="mdi {expanded === log.id ? 'mdi-chevron-up' : 'mdi-chevron-down'} text-xl"></i>
                    </button>
                  </td>
                </tr>
                {#if expanded === log.id}
                  <tr class="bg-slate-50 border-b border-slate-100">
                    <td colspan="6" class="px-4 py-4">
                      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div>
                          <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Antes</p>
                          <pre class="max-h-52 overflow-auto rounded-lg bg-white border border-slate-200 p-3 text-xs text-slate-600">{pretty(log.old_values)}</pre>
                        </div>
                        <div>
                          <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Después</p>
                          <pre class="max-h-52 overflow-auto rounded-lg bg-white border border-slate-200 p-3 text-xs text-slate-600">{pretty(log.new_values)}</pre>
                        </div>
                        <div>
                          <p class="text-xs font-semibold text-slate-500 uppercase mb-2">Metadata</p>
                          <pre class="max-h-52 overflow-auto rounded-lg bg-white border border-slate-200 p-3 text-xs text-slate-600">{pretty(log.metadata)}</pre>
                        </div>
                      </div>
                    </td>
                  </tr>
                {/if}
              {/each}
            {/if}
          </tbody>
        </table>
      </div>

      {#if logs.last_page > 1}
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <p class="text-xs text-slate-500">Mostrando {logs.from}–{logs.to} de {logs.total}</p>
          <div class="flex gap-1">
            {#each logs.links as link}
              {#if link.url}
                <a href={link.url} class="px-3 py-1 rounded text-xs transition {link.active ? 'bg-primary text-white font-medium' : 'text-slate-600 hover:bg-slate-100'}">
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
