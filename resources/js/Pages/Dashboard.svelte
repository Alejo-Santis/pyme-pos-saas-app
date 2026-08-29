<script>
  import { onMount, onDestroy } from 'svelte'
  import { page, router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import {
    Chart, LineController, LineElement, Filler,
    CategoryScale, LinearScale, PointElement, Tooltip,
  } from 'chart.js'

  Chart.register(LineController, LineElement, Filler,
    CategoryScale, LinearScale, PointElement, Tooltip)

  let {
    stats = {}, sales = [], purchases = [], year = new Date().getFullYear(),
    salesTrend = null, purchasesTrend = null, recentActivity = [], flash = {},
  } = $props()

  const user = $derived($page.props.auth?.user)

  const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  const currentYear = new Date().getFullYear()
  const years = [currentYear, currentYear - 1, currentYear - 2]

  const greeting = (() => {
    const h = new Date().getHours()
    if (h < 12) return 'Buenos días'
    if (h < 19) return 'Buenas tardes'
    return 'Buenas noches'
  })()

  const kpis = $derived([
    { label: 'Comprobantes contables', value: stats.accounting_receipts ?? 0, icon: 'mdi-file-document-multiple-outline', href: '/accounting', trend: salesTrend },
    { label: 'Productos activos', value: stats.items ?? 0,               icon: 'mdi-package-variant-closed',         href: '/inventory',      trend: null },
    { label: 'Terceros',          value: stats.third_parties ?? 0,       icon: 'mdi-account-group-outline',          href: '/third-parties',  trend: null },
    { label: 'Usuarios activos',  value: stats.users ?? 0,                icon: 'mdi-account-multiple-outline',       href: '/config/users',   trend: null },
  ])

  const activityIcon = { document: 'mdi-file-document-outline', third_party: 'mdi-account-plus-outline' }
  const activityLabel = (row) => row.type === 'document' ? `Documento ${row.label} creado` : `Nuevo tercero: ${row.label}`

  function timeAgo(dateStr) {
    const diff = (Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000
    if (diff < 3600) return `Hace ${Math.max(1, Math.round(diff / 60))} min`
    if (diff < 86400) return `Hace ${Math.round(diff / 3600)} h`
    return `Hace ${Math.round(diff / 86400)} d`
  }

  let canvasChart
  let chart

  const renderChart = () => {
    if (chart) chart.destroy()

    const ctx = canvasChart.getContext('2d')
    const gradSales = ctx.createLinearGradient(0, 0, 0, 220)
    gradSales.addColorStop(0, 'rgba(37,99,235,0.22)')
    gradSales.addColorStop(1, 'rgba(37,99,235,0)')

    chart = new Chart(canvasChart, {
      type: 'line',
      data: {
        labels: months,
        datasets: [
          {
            label: 'Ventas', data: sales, borderColor: '#185FA5', backgroundColor: gradSales,
            borderWidth: 2.5, tension: 0.35, fill: true, pointRadius: 0, pointHoverRadius: 4,
          },
          {
            label: 'Compras', data: purchases, borderColor: '#639922', backgroundColor: 'transparent',
            borderWidth: 2, borderDash: [5, 4], tension: 0.35, fill: false, pointRadius: 0, pointHoverRadius: 4,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94a3b8' } },
          y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 }, color: '#94a3b8' }, beginAtZero: true },
        },
      },
    })
  }

  const changeYear = (y) => {
    router.get(window.location.pathname, { year: y }, { preserveState: false })
  }

  onMount(() => renderChart())
  onDestroy(() => chart?.destroy())
</script>

<AppLayout>

  {#if flash?.success}
    <div class="mb-4 flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
      <i class="mdi mdi-check-circle shrink-0"></i><span>{flash.success}</span>
    </div>
  {/if}

  <!-- Encabezado: saludo + selector de año -->
  <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
    <div>
      <p class="text-sm text-slate-500">{greeting}</p>
      <h1 class="text-xl font-bold text-slate-800">{user?.name ?? 'Bienvenido'}</h1>
    </div>
    <div class="flex gap-2">
      {#each years as y}
        <button
          type="button"
          onclick={() => changeYear(y)}
          class="px-4 py-1.5 rounded-lg text-sm font-medium transition border cursor-pointer
                 {year === y
                   ? 'bg-primary border-primary text-white'
                   : 'bg-white border-slate-200 text-slate-500 hover:border-primary hover:text-primary'}"
        >{y}</button>
      {/each}
    </div>
  </div>

  <!-- Tarjetas KPI -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    {#each kpis as kpi}
      <a use:inertia href={kpi.href} class="bg-white rounded-xl border border-slate-200 p-4 hover:border-primary/40 transition-colors">
        <div class="w-9 h-9 rounded-lg bg-primary-soft flex items-center justify-center mb-3">
          <i class="mdi {kpi.icon} text-primary-dark text-lg"></i>
        </div>
        <p class="text-xs text-slate-500 mb-0.5">{kpi.label}</p>
        <p class="text-xl font-bold text-slate-800 leading-tight">{kpi.value.toLocaleString()}</p>
        {#if kpi.trend !== null}
          <p class="text-xs mt-1.5 flex items-center gap-1 {kpi.trend >= 0 ? 'text-emerald-600' : 'text-red-500'}">
            <i class="mdi {kpi.trend >= 0 ? 'mdi-arrow-up-right' : 'mdi-arrow-down-right'}"></i>
            {Math.abs(kpi.trend)}% vs mes anterior
          </p>
        {:else}
          <p class="text-xs mt-1.5 text-slate-400">Sin variación registrada</p>
        {/if}
      </a>
    {/each}
  </div>

  <!-- Gráfica + actividad reciente -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-4">
      <div class="flex items-center gap-4 mb-3">
        <h2 class="text-sm font-semibold text-slate-700">Ventas vs compras</h2>
        <span class="flex items-center gap-1.5 text-xs text-slate-400">
          <svg width="14" height="4"><line x1="0" y1="2" x2="14" y2="2" stroke="#185FA5" stroke-width="2"/></svg>
          Ventas
        </span>
        <span class="flex items-center gap-1.5 text-xs text-slate-400">
          <svg width="14" height="4"><line x1="0" y1="2" x2="14" y2="2" stroke="#639922" stroke-width="2" stroke-dasharray="3 2"/></svg>
          Compras
        </span>
      </div>
      <div style="height: 220px">
        <canvas bind:this={canvasChart}></canvas>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-4">
      <h2 class="text-sm font-semibold text-slate-700 mb-3">Actividad reciente</h2>
      {#if recentActivity.length}
        <ul class="space-y-3">
          {#each recentActivity as row}
            <li class="flex items-start gap-2.5">
              <i class="mdi {activityIcon[row.type] ?? 'mdi-circle-small'} text-primary text-base mt-0.5"></i>
              <div class="min-w-0">
                <p class="text-xs text-slate-600 truncate">{activityLabel(row)}</p>
                <p class="text-[11px] text-slate-400">{timeAgo(row.created_at)}</p>
              </div>
            </li>
          {/each}
        </ul>
      {:else}
        <p class="text-xs text-slate-400">Todavía no hay actividad registrada.</p>
      {/if}
    </div>

  </div>

</AppLayout>
