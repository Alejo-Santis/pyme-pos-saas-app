<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { auth, stats = {}, flash = {} } = $props()

  const cards = [
    { label: 'Artículos',          value: stats.items ?? 0,           icon: 'mdi-package-variant-closed',    color: 'bg-blue-50 text-blue-600',      border: 'border-blue-100' },
    { label: 'Terceros',           value: stats.third_parties ?? 0,   icon: 'mdi-account-group-outline',     color: 'bg-violet-50 text-violet-600',  border: 'border-violet-100' },
    { label: 'Documentos del mes', value: stats.documents_month ?? 0, icon: 'mdi-file-document-outline',     color: 'bg-emerald-50 text-emerald-600', border: 'border-emerald-100' },
    { label: 'Usuarios activos',   value: stats.users ?? 0,           icon: 'mdi-account-check-outline',     color: 'bg-amber-50 text-amber-600',    border: 'border-amber-100' },
  ]
</script>

<AppLayout>

  <!-- Flash de éxito (viene del registro) -->
  {#if flash?.success}
    <div class="mb-4 flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
      <i class="mdi mdi-check-circle text-lg shrink-0"></i>
      <span>{flash.success}</span>
    </div>
  {/if}

  <!-- Encabezado -->
  <div class="mb-6">
    <h1 class="text-xl font-bold text-slate-800">Panel Principal</h1>
    <p class="text-slate-500 text-sm mt-0.5">
      Bienvenido, <span class="font-medium text-slate-700">{auth.user.name}</span>.
      Aquí tienes un resumen de tu empresa.
    </p>
  </div>

  <!-- Tarjetas métricas -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    {#each cards as card}
      <div class="bg-white rounded-xl border {card.border} p-5 flex items-center gap-4">
        <div class="w-12 h-12 {card.color} rounded-xl flex items-center justify-center shrink-0">
          <i class="mdi {card.icon} text-2xl"></i>
        </div>
        <div>
          <p class="text-2xl font-bold text-slate-800">{card.value.toLocaleString()}</p>
          <p class="text-slate-500 text-sm">{card.label}</p>
        </div>
      </div>
    {/each}
  </div>

  <!-- Fila inferior -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Accesos rápidos -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
        <i class="mdi mdi-lightning-bolt text-blue-500"></i>
        Accesos rápidos
      </h2>
      <div class="grid grid-cols-2 gap-2">
        {#each [
          { href: '/invoices/create', icon: 'mdi-plus-circle-outline',   label: 'Nueva factura', color: 'text-blue-600' },
          { href: '/pos',             icon: 'mdi-point-of-sale',          label: 'Ir a POS',      color: 'text-violet-600' },
          { href: '/inventory',       icon: 'mdi-package-variant-closed', label: 'Inventario',    color: 'text-emerald-600' },
          { href: '/reports',         icon: 'mdi-chart-bar',              label: 'Reportes',      color: 'text-amber-600' },
        ] as action}
          <a href={action.href}
             class="flex items-center gap-2 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 transition text-sm text-slate-700">
            <i class="mdi {action.icon} {action.color} text-lg"></i>
            {action.label}
          </a>
        {/each}
      </div>
    </div>

    <!-- Estado del plan -->
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
        <i class="mdi mdi-information-outline text-blue-500"></i>
        Estado de la cuenta
      </h2>
      <div class="space-y-3">
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-500">Plan actual</span>
          <span class="font-medium text-slate-800">{stats.plan_name ?? '—'}</span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-500">Estado</span>
          <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full px-2 py-0.5 text-xs font-medium">
            <i class="mdi mdi-clock-outline text-xs"></i>
            Período de prueba
          </span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-slate-500">Prueba hasta</span>
          <span class="font-medium text-slate-800">{stats.trial_ends ?? '—'}</span>
        </div>
        <div class="pt-2 border-t border-slate-100">
          <a href="/config"
             class="text-blue-600 hover:text-blue-700 text-sm font-medium flex items-center gap-1">
            <i class="mdi mdi-cog-outline"></i>
            Configurar empresa
          </a>
        </div>
      </div>
    </div>

  </div>

</AppLayout>
