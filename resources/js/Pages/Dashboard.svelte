<script>
  import { onMount, onDestroy } from 'svelte'
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import {
    Chart, BarController, BarElement, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Title, Tooltip, Legend,
  } from 'chart.js'

  Chart.register(BarController, BarElement, LineController, LineElement,
    CategoryScale, LinearScale, PointElement, Title, Tooltip, Legend)

  let { auth, stats = {}, sales = [], purchases = [], year = new Date().getFullYear(), flash = {} } = $props()

  const months = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  const currentYear = new Date().getFullYear()
  const years = [currentYear, currentYear - 1, currentYear - 2]

  // Referencias DOM para los canvas
  let canvasSales, canvasPurchases
  let chartSales, chartPurchases

  const renderCharts = () => {
    if (chartSales)     chartSales.destroy()
    if (chartPurchases) chartPurchases.destroy()

    const ctxS = canvasSales.getContext('2d')
    const gradS = ctxS.createLinearGradient(0, 0, 0, 300)
    gradS.addColorStop(0, 'rgba(78,115,223,0.85)')
    gradS.addColorStop(1, 'rgba(78,115,223,0.2)')

    chartSales = new Chart(canvasSales, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{ label: 'Ventas', data: sales,
          backgroundColor: gradS, borderColor: 'rgba(78,115,223,1)',
          borderWidth: 2, borderRadius: 6 }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    })

    const ctxP = canvasPurchases.getContext('2d')
    const gradP = ctxP.createLinearGradient(0, 0, 0, 300)
    gradP.addColorStop(0, 'rgba(28,200,138,0.85)')
    gradP.addColorStop(1, 'rgba(28,200,138,0.2)')

    chartPurchases = new Chart(canvasPurchases, {
      type: 'bar',
      data: {
        labels: months,
        datasets: [{ label: 'Compras', data: purchases,
          backgroundColor: gradP, borderColor: 'rgba(28,200,138,1)',
          borderWidth: 2, borderRadius: 6 }],
      },
      options: { responsive: true, plugins: { legend: { display: false } } },
    })
  }

  const changeYear = (y) => {
    router.get(window.location.pathname, { year: y }, { preserveState: false })
  }

  onMount(() => renderCharts())
  onDestroy(() => { chartSales?.destroy(); chartPurchases?.destroy() })
</script>

<AppLayout>

  <!-- Flash de éxito -->
  {#if flash?.success}
    <div class="mb-4 flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
      <i class="mdi mdi-check-circle shrink-0"></i><span>{flash.success}</span>
    </div>
  {/if}

  <!-- Selector de período -->
  <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-3 mb-4 flex items-center justify-between flex-wrap gap-3">
    <span class="text-slate-500 text-sm flex items-center gap-1.5">
      <i class="mdi mdi-calendar-clock text-blue-500"></i> Seleccionar Período
    </span>
    <div class="flex gap-2">
      {#each years as y}
        <button
          type="button"
          onclick={() => changeYear(y)}
          class="px-5 py-1.5 rounded text-sm font-semibold transition border cursor-pointer
                 {year === y
                   ? 'bg-blue-600 border-blue-600 text-white'
                   : 'bg-white border-blue-500 text-blue-600 hover:bg-blue-50'}"
        >{y}</button>
      {/each}
    </div>
  </div>

  <!-- Tarjetas métricas con gradientes (estilo Xedoc) -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">

    <!-- Productos -->
    <div class="rounded-xl shadow-sm text-white flex items-center gap-4 p-5"
         style="background: linear-gradient(45deg,#4e73df,#224abe)">
      <i class="mdi mdi-cube text-5xl opacity-90"></i>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Productos creados</p>
        <a href="/inventory" class="text-white text-3xl font-bold leading-none">{(stats.items ?? 0).toLocaleString()}</a>
      </div>
    </div>

    <!-- Usuarios -->
    <div class="rounded-xl shadow-sm text-white flex items-center gap-4 p-5"
         style="background: linear-gradient(45deg,#1cc88a,#13855c)">
      <i class="mdi mdi-account-multiple text-5xl opacity-90"></i>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Usuarios Activos</p>
        <a href="/config" class="text-white text-3xl font-bold leading-none">{(stats.users ?? 0).toLocaleString()}</a>
      </div>
    </div>

    <!-- Terceros -->
    <div class="rounded-xl shadow-sm text-white flex items-center gap-4 p-5"
         style="background: linear-gradient(45deg,#f6c23e,#dda20a)">
      <i class="mdi mdi-account-tie text-5xl opacity-90"></i>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Terceros</p>
        <a href="/third-parties" class="text-white text-3xl font-bold leading-none">{(stats.third_parties ?? 0).toLocaleString()}</a>
      </div>
    </div>

    <!-- Documentos -->
    <div class="rounded-xl shadow-sm text-white flex items-center gap-4 p-5"
         style="background: linear-gradient(45deg,#e74a3b,#be2617)">
      <i class="mdi mdi-file-document-multiple-outline text-5xl opacity-90"></i>
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Comprob. Contable</p>
        <a href="/accounting" class="text-white text-3xl font-bold leading-none">{(stats.accounting_receipts ?? 0).toLocaleString()}</a>
      </div>
    </div>

  </div>

  <!-- Gráficas Ventas / Compras -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    <!-- Ventas -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
        <h5 class="font-bold text-blue-600 flex items-center gap-2 text-sm">
          <i class="mdi mdi-cash-multiple text-lg"></i> Ventas
        </h5>
      </div>
      <div class="p-4">
        <canvas bind:this={canvasSales} height="160"></canvas>
      </div>
    </div>

    <!-- Compras -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
      <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
        <h5 class="font-bold text-emerald-600 flex items-center gap-2 text-sm">
          <i class="mdi mdi-cart-outline text-lg"></i> Compras
        </h5>
      </div>
      <div class="p-4">
        <canvas bind:this={canvasPurchases} height="160"></canvas>
      </div>
    </div>

  </div>

</AppLayout>
