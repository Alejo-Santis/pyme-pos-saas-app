<script>
  import { onMount, onDestroy } from 'svelte'
  import { page, router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import {
    Chart, BarController, BarElement, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Title, Tooltip, Legend, Filler,
  } from 'chart.js'

  Chart.register(BarController, BarElement, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Title, Tooltip, Legend, Filler)

  let {
    auth, stats = {}, sales = [], revenue = [], purchases = [],
    recentDocuments = [],
    setup = {},
    salesTrend = null, purchasesTrend = null,
    year = new Date().getFullYear(), flash = {}
  } = $props()

  const user = $derived($page.props.auth?.user)

  const greeting = (() => {
    const h = new Date().getHours()
    if (h < 12) return 'Buenos días'
    if (h < 19) return 'Buenas tardes'
    return 'Buenas noches'
  })()

  const months     = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  const monthsFull = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
  const currentYear = new Date().getFullYear()
  const years = [currentYear, currentYear - 1, currentYear - 2]
  const today = new Date()

  const currentMonth = today.getMonth() // 0-based
  const setupNext = $derived(setup.next_step ?? null)

  let canvasSales, canvasRevenue, canvasPurchases
  let chartSales, chartRevenue, chartPurchases

  // ── Accesos rápidos ──────────────────────────────────────────────────────
  const shortcuts = [
    { href: '/invoices/create', icon: 'mdi-file-plus-outline',       label: 'Nueva Factura',     color: 'bg-blue-500'   },
    { href: '/pos',             icon: 'mdi-point-of-sale',            label: 'Abrir POS',         color: 'bg-violet-500' },
    { href: '/inventory',       icon: 'mdi-package-variant-closed',   label: 'Ver Inventario',    color: 'bg-amber-500'  },
    { href: '/third-parties',   icon: 'mdi-account-plus-outline',     label: 'Nuevo Tercero',     color: 'bg-emerald-500'},
    { href: '/cash',            icon: 'mdi-bank-outline',             label: 'Caja y Bancos',     color: 'bg-rose-500'   },
    { href: '/purchases',       icon: 'mdi-cart-plus',                label: 'Nueva Compra',      color: 'bg-orange-500' },
  ]

  // ── KPI cards ────────────────────────────────────────────────────────────
  const kpis = $derived([
    {
      label:  'Documentos',
      value:  stats.accounting_receipts ?? 0,
      icon:   'mdi-file-document-multiple-outline',
      href:   '/invoices',
      accent: '#2563eb',
      bg:     '#eff6ff',
      text:   '#1d4ed8',
      format: 'count',
      trend:  salesTrend,
    },
    {
      label:  'Artículos',
      value:  stats.items ?? 0,
      icon:   'mdi-package-variant-closed',
      href:   '/inventory',
      accent: '#7c3aed',
      bg:     '#f5f3ff',
      text:   '#6d28d9',
      format: 'count',
    },
    {
      label:  'Terceros',
      value:  stats.third_parties ?? 0,
      icon:   'mdi-account-group-outline',
      href:   '/third-parties',
      accent: '#059669',
      bg:     '#ecfdf5',
      text:   '#047857',
      format: 'count',
    },
    {
      label:  'Ventas del mes',
      value:  stats.revenue_this_month ?? 0,
      icon:   'mdi-cash-multiple',
      href:   '/invoices',
      accent: '#d97706',
      bg:     '#fffbeb',
      text:   '#b45309',
      format: 'currency',
    },
  ])

  // ── KPI métricas de ingresos ─────────────────────────────────────────────
  const revenueKpis = $derived([
    {
      label:   'Ingresos del mes',
      value:   stats.revenue_this_month ?? 0,
      icon:    'mdi-trending-up',
      color:   'text-emerald-600',
      bg:      'bg-emerald-50',
    },
    {
      label:   'Ingresos del año',
      value:   stats.revenue_this_year ?? 0,
      icon:    'mdi-chart-areaspline',
      color:   'text-blue-600',
      bg:      'bg-blue-50',
    },
    {
      label:   'Pendientes de cobro',
      value:   stats.pending_payment ?? 0,
      icon:    'mdi-clock-alert-outline',
      color:   'text-amber-600',
      bg:      'bg-amber-50',
      format:  'count',
    },
    {
      label:   'Usuarios activos',
      value:   stats.users ?? 0,
      icon:    'mdi-account-check-outline',
      color:   'text-violet-600',
      bg:      'bg-violet-50',
      format:  'count',
    },
  ])

  // ── Helpers de formato ───────────────────────────────────────────────────
  function fmt(value, format = 'currency') {
    if (format === 'count') return value.toLocaleString('es-CO')
    return '$ ' + value.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
  }

  // ── Estado de documentos recientes ───────────────────────────────────────
  const opLabels = {
    1:  'Factura',
    3:  'Nota Crédito',
    4:  'Nota Débito',
    5:  'Doc. Soporte',
    14: 'POS',
  }

  function docBadge(doc) {
    if (doc.annulled)   return { label: 'Anulado',   cls: 'bg-red-100 text-red-600'       }
    if (doc.paid)       return { label: 'Pagado',    cls: 'bg-emerald-100 text-emerald-600' }
    if (doc.electronic) return { label: 'DIAN ✓',   cls: 'bg-blue-100 text-blue-600'       }
    return               { label: 'Pendiente',       cls: 'bg-amber-100 text-amber-600'     }
  }

  // ── Gráficas ─────────────────────────────────────────────────────────────
  const baseOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#1e293b',
        titleColor: '#94a3b8',
        bodyColor: '#f1f5f9',
        padding: 10,
        cornerRadius: 8,
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: '#94a3b8', font: { size: 11, family: 'Nunito' } },
      },
      y: {
        grid: { color: '#f1f5f9', lineWidth: 1 },
        border: { dash: [4, 4] },
        ticks: {
          color: '#94a3b8',
          font: { size: 11, family: 'Nunito' },
          precision: 0,
        },
        beginAtZero: true,
      },
    },
  }

  const renderCharts = () => {
    chartSales?.destroy()
    chartRevenue?.destroy()
    chartPurchases?.destroy()

    // Documentos emitidos (conteo)
    chartSales = new Chart(canvasSales, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Documentos',
          data: sales,
          backgroundColor: months.map((_, i) =>
            i === currentMonth && year === currentYear ? '#2563eb' : '#bfdbfe'
          ),
          borderRadius: 6,
          borderSkipped: false,
        }],
      },
      options: baseOpts,
    })

    // Ingresos en pesos (línea)
    chartRevenue = new Chart(canvasRevenue, {
      type: 'line',
      data: {
        labels: months,
        datasets: [{
          label: 'Ingresos',
          data: revenue,
          borderColor: '#059669',
          backgroundColor: 'rgba(5,150,105,0.08)',
          borderWidth: 2.5,
          pointRadius: months.map((_, i) =>
            i === currentMonth && year === currentYear ? 5 : 3
          ),
          pointBackgroundColor: months.map((_, i) =>
            i === currentMonth && year === currentYear ? '#059669' : '#a7f3d0'
          ),
          fill: true,
          tension: 0.4,
        }],
      },
      options: {
        ...baseOpts,
        scales: {
          ...baseOpts.scales,
          y: {
            ...baseOpts.scales.y,
            ticks: {
              ...baseOpts.scales.y.ticks,
              callback: (v) => '$ ' + (v / 1_000_000).toFixed(1) + 'M',
            },
          },
        },
      },
    })

    // Órdenes de compra
    chartPurchases = new Chart(canvasPurchases, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{
          label: 'Compras',
          data: purchases,
          backgroundColor: months.map((_, i) =>
            i === currentMonth && year === currentYear ? '#7c3aed' : '#ddd6fe'
          ),
          borderRadius: 6,
          borderSkipped: false,
        }],
      },
      options: baseOpts,
    })
  }

  const changeYear = (y) => {
    router.get(window.location.pathname, { year: y }, { preserveState: false })
  }

  onMount(() => renderCharts())
  onDestroy(() => {
    chartSales?.destroy()
    chartRevenue?.destroy()
    chartPurchases?.destroy()
  })
</script>

<AppLayout>

  <!-- Flash -->
  {#if flash?.success}
    <div class="mb-5 flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
      <i class="mdi mdi-check-circle text-emerald-500 text-lg shrink-0"></i>
      <span class="font-medium">{flash.success}</span>
    </div>
  {/if}

  <!-- ── Encabezado: saludo + selector de año ─────────────────────────────── -->
  <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
    <div>
      <p class="text-sm text-slate-500">{greeting}</p>
      <h1 class="text-xl font-bold text-slate-800">{user?.name ?? auth?.user?.name ?? 'Bienvenido'}</h1>
      <p class="text-slate-400 text-sm mt-0.5">
        {monthsFull[today.getMonth()]} {today.getDate()}, {today.getFullYear()} · {stats.plan_name ?? '—'}
        {#if stats.trial_ends}
          <span class="ml-2 inline-flex items-center gap-1 bg-amber-100 text-amber-700 text-xs font-semibold px-2 py-0.5 rounded-full">
            <i class="mdi mdi-clock-outline text-xs"></i> Trial hasta {stats.trial_ends}
          </span>
        {/if}
      </p>
    </div>

    <!-- Selector de año -->
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

  {#if setupNext && (setup.percent ?? 0) < 100}
    <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 shadow-sm">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600">
            <i class="mdi {setupNext.icon} text-xl text-white"></i>
          </div>
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-sm font-bold text-slate-800">Configura tu ERP</h2>
              <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-blue-700">{setup.percent ?? 0}%</span>
            </div>
            <p class="mt-1 text-sm text-slate-600">
              Siguiente: <span class="font-semibold text-slate-800">{setupNext.title}</span>. {setupNext.description}
            </p>
            <div class="mt-3 h-2 max-w-lg rounded-full bg-white">
              <div class="h-2 rounded-full bg-blue-600" style="width: {setup.percent ?? 0}%"></div>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <a
            use:inertia
            href={setupNext.href}
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
          >
            <span>{setupNext.action}</span>
            <i class="mdi mdi-arrow-right text-base"></i>
          </a>
          <a
            use:inertia
            href="/setup"
            class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-50"
          >
            <i class="mdi mdi-map-check-outline text-base"></i>
            <span>Ver guía</span>
          </a>
        </div>
      </div>
    </div>
  {/if}

  <!-- ── KPI Cards ─────────────────────────────────────────────────────────── -->
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    {#each kpis as kpi}
      <a
        use:inertia
        href={kpi.href}
        class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm
               hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 group"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="w-11 h-11 rounded-xl flex items-center justify-center"
               style="background:{kpi.bg}">
            <i class="mdi {kpi.icon} text-xl" style="color:{kpi.accent}"></i>
          </div>
          <i class="mdi mdi-arrow-top-right text-slate-300 group-hover:text-slate-400 transition text-lg"></i>
        </div>
        <div class="text-2xl font-bold text-slate-800">
          {kpi.format === 'currency'
            ? '$ ' + kpi.value.toLocaleString('es-CO', { minimumFractionDigits: 0 })
            : kpi.value.toLocaleString('es-CO')}
        </div>
        <div class="text-slate-500 text-sm mt-0.5">{kpi.label}</div>
        {#if kpi.trend !== undefined && kpi.trend !== null}
          <div class="text-xs mt-1.5 flex items-center gap-1 {kpi.trend >= 0 ? 'text-emerald-600' : 'text-red-500'}">
            <i class="mdi {kpi.trend >= 0 ? 'mdi-arrow-up-right' : 'mdi-arrow-down-right'}"></i>
            {Math.abs(kpi.trend)}% vs mes anterior
          </div>
        {/if}
      </a>
    {/each}
  </div>

  <!-- ── Métricas secundarias (ingresos) ───────────────────────────────────── -->
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
    {#each revenueKpis as rk}
      <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex items-center gap-3">
        <div class="w-10 h-10 {rk.bg} rounded-xl flex items-center justify-center shrink-0">
          <i class="mdi {rk.icon} {rk.color} text-lg"></i>
        </div>
        <div class="overflow-hidden">
          <div class="text-slate-800 font-bold text-base truncate">
            {#if rk.format === 'count'}
              {rk.value.toLocaleString('es-CO')}
            {:else}
              $ {rk.value.toLocaleString('es-CO', { minimumFractionDigits: 0 })}
            {/if}
          </div>
          <div class="text-slate-400 text-xs truncate">{rk.label}</div>
        </div>
      </div>
    {/each}
  </div>

  <!-- ── Accesos rápidos ───────────────────────────────────────────────────── -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-5">
    <h2 class="text-slate-700 text-sm font-bold mb-3 flex items-center gap-2">
      <i class="mdi mdi-lightning-bolt text-blue-500"></i> Accesos rápidos
    </h2>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
      {#each shortcuts as sc}
        <a
          use:inertia
          href={sc.href}
          class="flex flex-col items-center gap-2 p-3 rounded-xl bg-slate-50
                 hover:bg-slate-100 transition text-center group cursor-pointer"
        >
          <div class="w-10 h-10 {sc.color} rounded-xl flex items-center justify-center
                      group-hover:scale-110 transition-transform">
            <i class="mdi {sc.icon} text-white text-lg"></i>
          </div>
          <span class="text-slate-600 text-xs font-medium leading-tight">{sc.label}</span>
        </a>
      {/each}
    </div>
  </div>

  <!-- ── Gráficas ──────────────────────────────────────────────────────────── -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">

    <!-- Documentos emitidos -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div>
          <h3 class="text-slate-800 text-sm font-bold flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span>
            Documentos emitidos
          </h3>
          <p class="text-slate-400 text-xs mt-0.5">Facturas y documentos · {year}</p>
        </div>
        <div class="text-right">
          <div class="text-lg font-bold text-slate-800">
            {sales.reduce((a, b) => a + b, 0).toLocaleString('es-CO')}
          </div>
          <div class="text-slate-400 text-xs">total {year}</div>
        </div>
      </div>
      <div class="p-5" style="height:200px">
        <canvas bind:this={canvasSales}></canvas>
      </div>
    </div>

    <!-- Ingresos en pesos -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div>
          <h3 class="text-slate-800 text-sm font-bold flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 inline-block"></span>
            Ingresos por ventas
          </h3>
          <p class="text-slate-400 text-xs mt-0.5">FEV + POS · {year}</p>
        </div>
        <div class="text-right">
          <div class="text-lg font-bold text-slate-800">
            $ {revenue.reduce((a, b) => a + b, 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })}
          </div>
          <div class="text-slate-400 text-xs">total {year}</div>
        </div>
      </div>
      <div class="p-5" style="height:200px">
        <canvas bind:this={canvasRevenue}></canvas>
      </div>
    </div>

  </div>

  <!-- ── Documentos recientes + Compras ────────────────────────────────────── -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    <!-- Documentos recientes (2/3 del ancho) -->
    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <h3 class="text-slate-800 text-sm font-bold flex items-center gap-2">
          <i class="mdi mdi-history text-blue-500 text-base"></i>
          Actividad reciente
        </h3>
        <a use:inertia href="/invoices"
           class="text-xs text-blue-500 hover:text-blue-700 font-semibold transition">
          Ver todos →
        </a>
      </div>

      {#if recentDocuments.length === 0}
        <div class="py-12 text-center">
          <i class="mdi mdi-file-document-outline text-slate-300 text-4xl block mb-2"></i>
          <p class="text-slate-400 text-sm">Sin documentos registrados aún</p>
          <a use:inertia href="/invoices/create"
             class="inline-flex items-center gap-1.5 mt-3 text-blue-500 text-sm font-semibold hover:text-blue-700">
            <i class="mdi mdi-plus text-base"></i> Crear primer documento
          </a>
        </div>
      {:else}
        <div class="divide-y divide-slate-50">
          {#each recentDocuments as doc}
            {@const badge = docBadge(doc)}
            <a use:inertia href="/invoices/{doc.id}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition group">
              <!-- Ícono tipo documento -->
              <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                <i class="mdi mdi-file-document-outline text-blue-400 text-base"></i>
              </div>
              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-slate-700 text-sm font-semibold">
                    {opLabels[doc.type_document_operation_id] ?? 'Doc.'} {doc.label}
                  </span>
                  <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {badge.cls}">
                    {badge.label}
                  </span>
                </div>
                <span class="text-slate-400 text-xs truncate block">{doc.third_party}</span>
              </div>
              <!-- Total y fecha -->
              <div class="text-right shrink-0">
                <div class="text-slate-800 text-sm font-bold">
                  $ {doc.total.toLocaleString('es-CO', { minimumFractionDigits: 0 })}
                </div>
                <div class="text-slate-400 text-xs">{doc.date}</div>
              </div>
            </a>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Gráfica de compras (1/3 del ancho) -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
        <div>
          <h3 class="text-slate-800 text-sm font-bold flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-violet-500 inline-block"></span>
            Órdenes de compra
          </h3>
          <p class="text-slate-400 text-xs mt-0.5">{year}</p>
        </div>
        <div class="text-right">
          <div class="text-base font-bold text-slate-800">
            {purchases.reduce((a, b) => a + b, 0).toLocaleString('es-CO')}
          </div>
          <div class="text-slate-400 text-xs">total</div>
        </div>
      </div>
      <div class="p-4" style="height:240px">
        <canvas bind:this={canvasPurchases}></canvas>
      </div>
      <div class="px-5 pb-4">
        <a use:inertia href="/purchases"
           class="flex items-center justify-center gap-1.5 w-full py-2 rounded-lg bg-slate-50
                  hover:bg-slate-100 text-slate-600 text-xs font-semibold transition">
          <i class="mdi mdi-cart-outline text-sm"></i>
          Ver órdenes de compra
        </a>
      </div>
    </div>

  </div>

</AppLayout>
