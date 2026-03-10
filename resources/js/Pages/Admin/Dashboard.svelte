<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { onMount } from 'svelte'
  import { inertia } from '@inertiajs/svelte'

  let { stats = {}, monthlyGrowth = {}, recentTenants = [] } = $props()

  // Colores de estado
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

  // Chart.js para tenants por mes
  let chartCanvas
  onMount(async () => {
    const { Chart, registerables } = await import('chart.js')
    Chart.register(...registerables)

    const labels = Object.keys(monthlyGrowth)
    const data   = Object.values(monthlyGrowth)

    new Chart(chartCanvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Empresas registradas',
          data,
          backgroundColor: 'rgba(37,99,235,0.7)',
          borderRadius: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } },
          x: { grid: { display: false } },
        },
      },
    })
  })
</script>

<AdminLayout>
  <div class="space-y-6">

    <div>
      <h2 class="text-xl font-bold text-slate-800">Dashboard</h2>
      <p class="text-slate-500 text-sm">Resumen general de la plataforma</p>
    </div>

    <!-- Tarjetas de métricas -->
    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Total Empresas</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{stats.total_tenants ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-domain text-blue-600 text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Activas</p>
            <p class="text-3xl font-bold text-emerald-600 mt-1">{stats.active_tenants ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-emerald-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-check-circle-outline text-emerald-600 text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">En Trial</p>
            <p class="text-3xl font-bold text-blue-600 mt-1">{stats.trial_tenants ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-blue-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-clock-outline text-blue-600 text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Suspendidas</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{stats.suspended_tenants ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-amber-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-pause-circle-outline text-amber-600 text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Suscripciones activas</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{stats.total_subscriptions ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-slate-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-credit-card-outline text-slate-600 text-2xl"></i>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Planes activos</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{stats.total_plans ?? 0}</p>
          </div>
          <div class="w-11 h-11 bg-purple-50 rounded-xl flex items-center justify-center">
            <i class="mdi mdi-layers-outline text-purple-600 text-2xl"></i>
          </div>
        </div>
      </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Gráfica de crecimiento -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Empresas registradas (últimos 6 meses)</h3>
        <div class="h-48">
          <canvas bind:this={chartCanvas}></canvas>
        </div>
      </div>

      <!-- Últimos registros -->
      <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-700">Últimas empresas registradas</h3>
          <a use:inertia href="/admin/tenants" class="text-xs text-primary hover:underline">Ver todas</a>
        </div>

        {#if recentTenants.length === 0}
          <p class="text-slate-400 text-sm text-center py-6">Sin registros aún</p>
        {:else}
          <div class="space-y-3">
            {#each recentTenants as tenant}
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center shrink-0">
                    <span class="text-primary text-xs font-bold">
                      {tenant.name?.charAt(0)?.toUpperCase()}
                    </span>
                  </div>
                  <div>
                    <a use:inertia href="/admin/tenants/{tenant.id}" class="text-sm font-medium text-slate-700 hover:text-primary">
                      {tenant.name}
                    </a>
                    <p class="text-xs text-slate-400">{tenant.plan}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium {statusColor[tenant.status] ?? 'bg-slate-100 text-slate-600'}">
                    {statusLabel[tenant.status] ?? tenant.status}
                  </span>
                  <span class="text-xs text-slate-400">{tenant.created_at}</span>
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>

    </div>
  </div>
</AdminLayout>
