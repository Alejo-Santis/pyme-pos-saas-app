<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { logs, filters = {} } = $props()

  let search = $state(filters.search ?? '')
  let event  = $state(filters.event  ?? '')
  let module = $state(filters.module ?? '')

  function applyFilters() {
    router.get('/audit/activity', { search, event, module }, { preserveState: true, replace: true })
  }

  function clearFilters() {
    search = ''; event = ''; module = ''
    router.get('/audit/activity', {}, { preserveState: false })
  }

  const eventLabels = {
    created:  { label: 'Creado',    cls: 'bg-emerald-100 text-emerald-700' },
    updated:  { label: 'Editado',   cls: 'bg-blue-100 text-blue-700'       },
    deleted:  { label: 'Eliminado', cls: 'bg-red-100 text-red-700'         },
    login:    { label: 'Login',     cls: 'bg-violet-100 text-violet-700'   },
    logout:   { label: 'Logout',    cls: 'bg-slate-100 text-slate-600'     },
    export:   { label: 'Exportado', cls: 'bg-amber-100 text-amber-700'     },
    retry:    { label: 'Reintento', cls: 'bg-orange-100 text-orange-700'   },
  }

  function badgeFor(ev) {
    return eventLabels[ev] ?? { label: ev, cls: 'bg-slate-100 text-slate-600' }
  }

  function fmtDate(dt) {
    if (!dt) return '—'
    return new Date(dt).toLocaleString('es-CO', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit',
    })
  }

  const moduleIcon = {
    Invoice:   'mdi-file-document-outline',
    POS:       'mdi-point-of-sale',
    Inventory: 'mdi-package-variant-closed',
    Auth:      'mdi-account-lock-outline',
    Cash:      'mdi-bank-outline',
    Purchases: 'mdi-cart-outline',
    Payroll:   'mdi-account-hard-hat-outline',
    Config:    'mdi-cog-outline',
  }
</script>

<AppLayout>
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-slate-800 text-xl font-bold flex items-center gap-2">
        <i class="mdi mdi-shield-check-outline text-primary text-2xl"></i>
        Log de Actividad
      </h1>
      <p class="text-slate-400 text-sm mt-0.5">Historial completo de acciones realizadas en el sistema</p>
    </div>
    <a use:inertia href="/audit/api-logs"
       class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 shadow-sm transition">
      <i class="mdi mdi-api text-slate-400 text-base"></i>
      Ver logs DIAN
    </a>
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-48">
        <label class="block text-xs font-semibold text-slate-500 mb-1">Buscar</label>
        <input
          type="text"
          bind:value={search}
          onkeydown={(e) => e.key === 'Enter' && applyFilters()}
          placeholder="Acción, usuario, módulo..."
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Evento</label>
        <select
          bind:value={event}
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Todos</option>
          <option value="created">Creado</option>
          <option value="updated">Editado</option>
          <option value="deleted">Eliminado</option>
          <option value="login">Login</option>
          <option value="logout">Logout</option>
          <option value="export">Exportado</option>
          <option value="retry">Reintento</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Módulo</label>
        <select
          bind:value={module}
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary"
        >
          <option value="">Todos</option>
          {#each ['Invoice','POS','Inventory','Auth','Cash','Purchases','Payroll','Config'] as m}
            <option value={m}>{m}</option>
          {/each}
        </select>
      </div>
      <button onclick={applyFilters}
        class="flex items-center gap-1.5 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary/90 transition cursor-pointer">
        <i class="mdi mdi-magnify text-base"></i> Filtrar
      </button>
      {#if search || event || module}
        <button onclick={clearFilters}
          class="flex items-center gap-1.5 bg-slate-100 text-slate-600 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-slate-200 transition cursor-pointer">
          <i class="mdi mdi-close text-base"></i> Limpiar
        </button>
      {/if}
    </div>
  </div>

  <!-- Tabla -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    {#if logs.data.length === 0}
      <div class="py-16 text-center">
        <i class="mdi mdi-shield-off-outline text-slate-300 text-5xl block mb-3"></i>
        <p class="text-slate-400 text-sm">Sin registros de actividad</p>
      </div>
    {:else}
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Fecha</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Usuario</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Evento</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Acción</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Módulo</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            {#each logs.data as log}
              {@const badge = badgeFor(log.event)}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{fmtDate(log.created_at)}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <div class="w-6 h-6 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
                      <span class="text-primary text-[10px] font-bold">
                        {(log.user_name ?? 'S')[0].toUpperCase()}
                      </span>
                    </div>
                    <span class="text-slate-700 font-medium text-xs">{log.user_name ?? 'Sistema'}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs font-semibold px-2 py-0.5 rounded-full {badge.cls}">{badge.label}</span>
                </td>
                <td class="px-4 py-3 text-slate-700 max-w-xs truncate">{log.action}</td>
                <td class="px-4 py-3">
                  {#if log.module}
                    <span class="flex items-center gap-1 text-slate-500 text-xs">
                      <i class="mdi {moduleIcon[log.module] ?? 'mdi-circle-outline'} text-sm"></i>
                      {log.module}
                    </span>
                  {:else}
                    <span class="text-slate-300">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-400 text-xs font-mono">{log.ip_address ?? '—'}</td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      {#if logs.last_page > 1}
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-100">
          <span class="text-xs text-slate-400">
            Mostrando {logs.from}–{logs.to} de {logs.total} registros
          </span>
          <div class="flex gap-1">
            {#each logs.links as link}
              {#if link.url}
                <a use:inertia href={link.url}
                   class="px-3 py-1 rounded-lg text-xs font-semibold transition
                     {link.active
                       ? 'bg-primary text-white'
                       : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                  {@html link.label}
                </a>
              {:else}
                <span class="px-3 py-1 rounded-lg text-xs text-slate-300">{@html link.label}</span>
              {/if}
            {/each}
          </div>
        </div>
      {/if}
    {/if}
  </div>
</AppLayout>
