<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ExportButtons from '@/Components/UI/ExportButtons.svelte'

  let { summary = {}, detail = [], filters = {} } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  let form = $state({
    date_from: filters.date_from ?? new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0,10),
    date_to:   filters.date_to   ?? new Date().toISOString().slice(0,10),
  })

  function search() {
    router.get('/accounting/balance-sheet', form, { preserveState: true, replace: true })
  }

  const activos    = $derived(detail.find(s => s.class === 1))
  const pasivos    = $derived(detail.find(s => s.class === 2))
  const patrimonio = $derived(detail.find(s => s.class === 3))

  let expanded = $state({})
  function toggle(key) { expanded = { ...expanded, [key]: !expanded[key] } }
</script>

<AppLayout>
  <div class="space-y-5">

    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Balance General</h1>
        <p class="text-sm text-slate-500 mt-0.5">Estado de Situación Financiera · NIIF PYMES</p>
      </div>
      <div class="flex items-center gap-3">
        <ExportButtons baseUrl="/accounting/balance-sheet/export" params={{ date_from: form.date_from, date_to: form.date_to }} />
        {#if summary.cuadre}
          <div class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-xl">
            <i class="mdi mdi-check-circle-outline"></i> Balance cuadrado
          </div>
        {:else}
          <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-xl">
            <i class="mdi mdi-alert-circle-outline"></i>
            Diferencia: ${fmt(Math.abs(summary.diferencia ?? 0))}
          </div>
        {/if}
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 flex items-end gap-3">
      <div class="flex-1">
        <label class="text-xs font-medium text-slate-500 block mb-1">Desde</label>
        <input type="date" bind:value={form.date_from}
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
      </div>
      <div class="flex-1">
        <label class="text-xs font-medium text-slate-500 block mb-1">Hasta</label>
        <input type="date" bind:value={form.date_to}
          class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-primary" />
      </div>
      <button onclick={search}
        class="px-5 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer">
        Generar
      </button>
    </div>

    <!-- Totales de encabezado -->
    <div class="grid grid-cols-3 gap-4">
      <div class="bg-blue-600 text-white rounded-xl p-5 shadow-sm">
        <p class="text-xs font-medium text-blue-200 uppercase tracking-wide mb-1">Total Activos</p>
        <p class="text-2xl font-bold tabular-nums">${fmt(summary.total_activos)}</p>
      </div>
      <div class="bg-red-500 text-white rounded-xl p-5 shadow-sm">
        <p class="text-xs font-medium text-red-200 uppercase tracking-wide mb-1">Total Pasivos</p>
        <p class="text-2xl font-bold tabular-nums">${fmt(summary.total_pasivos)}</p>
      </div>
      <div class="bg-emerald-600 text-white rounded-xl p-5 shadow-sm">
        <p class="text-xs font-medium text-emerald-200 uppercase tracking-wide mb-1">Patrimonio</p>
        <p class="text-2xl font-bold tabular-nums">${fmt(summary.total_patrimonio)}</p>
        {#if Number(summary.utilidad_periodo) !== 0}
          <p class="text-xs text-emerald-200 mt-1">
            Incl. {Number(summary.utilidad_periodo) >= 0 ? 'utilidad' : 'pérdida'}:
            ${fmt(Math.abs(summary.utilidad_periodo))}
          </p>
        {/if}
      </div>
    </div>

    <!-- Detalle en dos columnas (Activo | Pasivo + Patrimonio) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- ACTIVOS -->
      <div class="space-y-2">
        <h2 class="text-sm font-bold text-slate-700 px-1 flex items-center gap-2">
          <i class="mdi mdi-bank-outline text-blue-600"></i> ACTIVOS
        </h2>
        {#if activos}
          {#each activos.groups as group}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <button onclick={() => toggle('a_' + group.code)}
                class="w-full flex items-center justify-between px-4 py-3 hover:bg-blue-50/50 transition cursor-pointer text-left">
                <span class="text-sm font-semibold text-slate-700">{group.name}</span>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-blue-700 tabular-nums">${fmt(group.total)}</span>
                  <i class="mdi text-slate-400 text-base {expanded['a_' + group.code] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
                </div>
              </button>
              {#if expanded['a_' + group.code]}
                {#each group.accounts as acc}
                  <div class="flex justify-between px-6 py-2 border-t border-slate-50 hover:bg-slate-50/50">
                    <div class="flex items-center gap-2">
                      <span class="font-mono text-xs text-slate-400">{acc.code}</span>
                      <span class="text-sm text-slate-600">{acc.name}</span>
                    </div>
                    <span class="text-sm tabular-nums text-slate-700">${fmt(acc.balance)}</span>
                  </div>
                {/each}
              {/if}
            </div>
          {/each}
          <div class="bg-blue-600 text-white rounded-xl px-4 py-3 flex justify-between font-bold">
            <span>TOTAL ACTIVOS</span>
            <span class="tabular-nums">${fmt(activos.total)}</span>
          </div>
        {/if}
      </div>

      <!-- PASIVOS + PATRIMONIO -->
      <div class="space-y-2">
        <h2 class="text-sm font-bold text-slate-700 px-1 flex items-center gap-2">
          <i class="mdi mdi-scale-balance text-red-500"></i> PASIVOS Y PATRIMONIO
        </h2>

        <!-- Pasivos -->
        {#if pasivos}
          <div class="text-xs font-semibold text-red-600 uppercase tracking-wide px-1 pt-1">Pasivos</div>
          {#each pasivos.groups as group}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <button onclick={() => toggle('p_' + group.code)}
                class="w-full flex items-center justify-between px-4 py-3 hover:bg-red-50/50 transition cursor-pointer text-left">
                <span class="text-sm font-semibold text-slate-700">{group.name}</span>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-red-600 tabular-nums">${fmt(group.total)}</span>
                  <i class="mdi text-slate-400 text-base {expanded['p_' + group.code] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
                </div>
              </button>
              {#if expanded['p_' + group.code]}
                {#each group.accounts as acc}
                  <div class="flex justify-between px-6 py-2 border-t border-slate-50 hover:bg-slate-50/50">
                    <div class="flex items-center gap-2">
                      <span class="font-mono text-xs text-slate-400">{acc.code}</span>
                      <span class="text-sm text-slate-600">{acc.name}</span>
                    </div>
                    <span class="text-sm tabular-nums text-slate-700">${fmt(acc.balance)}</span>
                  </div>
                {/each}
              {/if}
            </div>
          {/each}
          <div class="bg-red-500 text-white rounded-xl px-4 py-2.5 flex justify-between font-semibold text-sm">
            <span>Total Pasivos</span>
            <span class="tabular-nums">${fmt(pasivos.total)}</span>
          </div>
        {/if}

        <!-- Patrimonio -->
        {#if patrimonio}
          <div class="text-xs font-semibold text-emerald-600 uppercase tracking-wide px-1 pt-2">Patrimonio</div>
          {#each patrimonio.groups as group}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
              <button onclick={() => toggle('pat_' + group.code)}
                class="w-full flex items-center justify-between px-4 py-3 hover:bg-emerald-50/50 transition cursor-pointer text-left">
                <span class="text-sm font-semibold text-slate-700">{group.name}</span>
                <div class="flex items-center gap-2">
                  <span class="text-sm font-bold text-emerald-700 tabular-nums">${fmt(group.total)}</span>
                  <i class="mdi text-slate-400 text-base {expanded['pat_' + group.code] ? 'mdi-chevron-up' : 'mdi-chevron-down'}"></i>
                </div>
              </button>
              {#if expanded['pat_' + group.code]}
                {#each group.accounts as acc}
                  <div class="flex justify-between px-6 py-2 border-t border-slate-50 hover:bg-slate-50/50">
                    <div class="flex items-center gap-2">
                      <span class="font-mono text-xs text-slate-400">{acc.code}</span>
                      <span class="text-sm text-slate-600">{acc.name}</span>
                    </div>
                    <span class="text-sm tabular-nums text-slate-700">${fmt(acc.balance)}</span>
                  </div>
                {/each}
              {/if}
            </div>
          {/each}
          <!-- Utilidad del período -->
          {#if Number(summary.utilidad_periodo) !== 0}
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-2.5 flex justify-between text-sm">
              <span class="text-slate-600">Utilidad / pérdida del período</span>
              <span class="font-semibold tabular-nums {Number(summary.utilidad_periodo) >= 0 ? 'text-emerald-700' : 'text-red-600'}">
                ${fmt(summary.utilidad_periodo)}
              </span>
            </div>
          {/if}
          <div class="bg-emerald-600 text-white rounded-xl px-4 py-2.5 flex justify-between font-semibold text-sm">
            <span>Total Patrimonio</span>
            <span class="tabular-nums">${fmt(summary.total_patrimonio)}</span>
          </div>
        {/if}

        <!-- Total Pasivo + Patrimonio -->
        <div class="bg-slate-800 text-white rounded-xl px-4 py-3 flex justify-between font-bold">
          <span>TOTAL PASIVO + PATRIMONIO</span>
          <span class="tabular-nums">${fmt(Number(summary.total_pasivos) + Number(summary.total_patrimonio))}</span>
        </div>
      </div>

    </div>
  </div>
</AppLayout>
