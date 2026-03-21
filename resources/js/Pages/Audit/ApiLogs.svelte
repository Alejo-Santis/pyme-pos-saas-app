<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { logs, summary = {}, filters = {} } = $props()

  let operation  = $state(filters.operation   ?? '')
  let onlyErrors = $state(filters.only_errors ?? false)

  function applyFilters() {
    router.get('/audit/api-logs', { operation, only_errors: onlyErrors || undefined }, {
      preserveState: true, replace: true,
    })
  }

  function clearFilters() {
    operation = ''; onlyErrors = false
    router.get('/audit/api-logs', {}, { preserveState: false })
  }

  function fmtDate(dt) {
    if (!dt) return '—'
    return new Date(dt).toLocaleString('es-CO', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit',
    })
  }

  let expandedId = $state(null)
  function toggleExpand(id) {
    expandedId = expandedId === id ? null : id
  }

  function prettyJson(obj) {
    if (!obj) return '—'
    try { return JSON.stringify(obj, null, 2) } catch { return String(obj) }
  }
</script>

<AppLayout>
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-slate-800 text-xl font-bold flex items-center gap-2">
        <i class="mdi mdi-api text-primary text-2xl"></i>
        API DIAN — Nextpyme
      </h1>
      <p class="text-slate-400 text-sm mt-0.5">Registro de todas las llamadas al proveedor de facturación electrónica</p>
    </div>
    <a use:inertia href="/audit/activity"
       class="flex items-center gap-2 bg-white border border-slate-200 px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 shadow-sm transition">
      <i class="mdi mdi-shield-check-outline text-slate-400 text-base"></i>
      Log de actividad
    </a>
  </div>

  <!-- Métricas rápidas -->
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    {#each [
      { label: 'Total llamadas',  value: summary.total   ?? 0, icon: 'mdi-api',              color: 'text-blue-600',    bg: 'bg-blue-50'    },
      { label: 'Exitosas',        value: summary.success ?? 0, icon: 'mdi-check-circle',      color: 'text-emerald-600', bg: 'bg-emerald-50' },
      { label: 'Fallidas',        value: summary.failed  ?? 0, icon: 'mdi-alert-circle',      color: 'text-red-600',     bg: 'bg-red-50'     },
      { label: 'Tiempo prom. ms', value: summary.avg_ms  ?? 0, icon: 'mdi-timer-outline',     color: 'text-amber-600',   bg: 'bg-amber-50'   },
    ] as kpi}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 flex items-center gap-3">
        <div class="w-10 h-10 {kpi.bg} rounded-xl flex items-center justify-center shrink-0">
          <i class="mdi {kpi.icon} {kpi.color} text-lg"></i>
        </div>
        <div>
          <div class="text-slate-800 font-bold text-base">{kpi.value.toLocaleString('es-CO')}</div>
          <div class="text-slate-400 text-xs">{kpi.label}</div>
        </div>
      </div>
    {/each}
  </div>

  <!-- Filtros -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1">Operación</label>
        <select bind:value={operation}
          class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-primary">
          <option value="">Todas</option>
          <option value="send_invoice">send_invoice</option>
          <option value="send_credit_note">send_credit_note</option>
          <option value="send_debit_note">send_debit_note</option>
          <option value="send_support_document">send_support_document</option>
          <option value="get_status">get_status</option>
        </select>
      </div>
      <label class="flex items-center gap-2 cursor-pointer mt-4">
        <input type="checkbox" bind:checked={onlyErrors}
          class="w-4 h-4 rounded border-slate-300 accent-red-500 cursor-pointer" />
        <span class="text-sm text-slate-600 font-medium">Solo errores</span>
      </label>
      <button onclick={applyFilters}
        class="flex items-center gap-1.5 bg-primary text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-primary/90 transition cursor-pointer">
        <i class="mdi mdi-filter-outline text-base"></i> Filtrar
      </button>
      {#if operation || onlyErrors}
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
        <i class="mdi mdi-api text-slate-300 text-5xl block mb-3"></i>
        <p class="text-slate-400 text-sm">Sin llamadas registradas a la API DIAN</p>
      </div>
    {:else}
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50">
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Fecha</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Documento</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Operación</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">HTTP</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Estado</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Tiempo</th>
              <th class="text-left text-xs font-bold text-slate-500 uppercase tracking-wide px-4 py-3">Intento</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-50">
            {#each logs.data as log}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{fmtDate(log.created_at)}</td>
                <td class="px-4 py-3">
                  {#if log.document}
                    <span class="text-slate-700 text-xs font-mono font-semibold">
                      {log.document.prefix ?? ''}{log.document.number ?? '—'}
                    </span>
                  {:else}
                    <span class="text-slate-300 text-xs">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3">
                  <span class="font-mono text-xs text-slate-600">{log.operation}</span>
                </td>
                <td class="px-4 py-3">
                  {#if log.http_status}
                    <span class="text-xs font-bold font-mono
                      {log.http_status < 300 ? 'text-emerald-600' :
                       log.http_status < 500 ? 'text-amber-600' : 'text-red-600'}">
                      {log.http_status}
                    </span>
                  {:else}
                    <span class="text-slate-300 text-xs">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3">
                  {#if log.success}
                    <span class="flex items-center gap-1 text-xs font-semibold text-emerald-600">
                      <i class="mdi mdi-check-circle text-sm"></i> Exitoso
                    </span>
                  {:else}
                    <span class="flex items-center gap-1 text-xs font-semibold text-red-600">
                      <i class="mdi mdi-close-circle text-sm"></i> Fallido
                    </span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                  {log.duration_ms != null ? log.duration_ms + ' ms' : '—'}
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold
                    {log.attempt > 1 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500'}">
                    {log.attempt}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button onclick={() => toggleExpand(log.id)}
                    class="text-slate-400 hover:text-primary transition cursor-pointer"
                    title="Ver detalle">
                    <i class="mdi {expandedId === log.id ? 'mdi-chevron-up' : 'mdi-chevron-down'} text-lg"></i>
                  </button>
                </td>
              </tr>

              <!-- Fila expandida con JSON -->
              {#if expandedId === log.id}
                <tr class="bg-slate-50">
                  <td colspan="8" class="px-4 py-4">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                      {#if log.error_message}
                        <div class="lg:col-span-2 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-700">
                          <strong class="block mb-1">Error:</strong>
                          {log.error_message}
                        </div>
                      {/if}
                      <div>
                        <p class="text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Request payload</p>
                        <pre class="bg-white border border-slate-200 rounded-lg p-3 text-xs text-slate-600 overflow-auto max-h-64 font-mono whitespace-pre-wrap">{prettyJson(log.request_payload)}</pre>
                      </div>
                      <div>
                        <p class="text-xs font-bold text-slate-500 mb-1 uppercase tracking-wide">Response body</p>
                        <pre class="bg-white border border-slate-200 rounded-lg p-3 text-xs text-slate-600 overflow-auto max-h-64 font-mono whitespace-pre-wrap">{prettyJson(log.response_body)}</pre>
                      </div>
                    </div>
                    {#if log.endpoint}
                      <p class="mt-2 text-xs text-slate-400 font-mono break-all">{log.endpoint}</p>
                    {/if}
                  </td>
                </tr>
              {/if}
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
                     {link.active ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
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
