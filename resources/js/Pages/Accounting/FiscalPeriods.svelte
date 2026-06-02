<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { periods = [], selectedYear, availableYears = [] } = $props()

  let closeForm   = $state({ notes: '' })
  let reopenForm  = $state({ notes: '' })
  let closeYearForm = $state({ year: selectedYear - 1, notes: '' })

  let confirmClose   = $state(null)   // id del período a cerrar
  let confirmReopen  = $state(null)   // id del período a reabrir
  let showCloseYear  = $state(false)

  function selectYear(y) {
    router.get('/accounting/fiscal-periods', { year: y }, { preserveState: false })
  }

  function submitClose(period) {
    router.post(`/accounting/fiscal-periods/${period.id}/close`, closeForm, {
      onSuccess: () => { confirmClose = null; closeForm.notes = '' },
    })
  }

  function submitReopen(period) {
    router.post(`/accounting/fiscal-periods/${period.id}/reopen`, reopenForm, {
      onSuccess: () => { confirmReopen = null; reopenForm.notes = '' },
    })
  }

  function submitCloseYear() {
    router.post('/accounting/fiscal-periods/close-year', closeYearForm, {
      onSuccess: () => { showCloseYear = false },
    })
  }

  const monthAbbr = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun',
                         'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

  function statusCls(status) {
    return status === 'open'
      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
      : 'bg-slate-100 text-slate-600 border border-slate-300'
  }
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Encabezado -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Períodos Fiscales</h1>
        <p class="text-sm text-slate-500 mt-0.5">Cierre y reapertura de períodos contables</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- Selector de año -->
        <select
          class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 shadow-sm"
          value={selectedYear}
          onchange={(e) => selectYear(Number(e.target.value))}
        >
          {#each availableYears as y}
            <option value={y}>{y}</option>
          {/each}
          <option value={selectedYear}>{selectedYear}</option>
        </select>
        <!-- Cierre anual -->
        <button
          onclick={() => { showCloseYear = true }}
          class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700"
        >
          <i class="mdi mdi-calendar-lock text-sm"></i>
          Cierre anual
        </button>
      </div>
    </div>

    <!-- Advertencia informativa -->
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
      <div class="flex items-start gap-2">
        <i class="mdi mdi-information-outline mt-0.5 text-base"></i>
        <div>
          <strong>¿Qué hace el cierre de período?</strong>
          Al cerrar un período, el motor contable rechazará automáticamente cualquier asiento con fecha
          dentro de ese mes. Los comprobantes existentes no se modifican. La reapertura requiere justificación
          y queda registrada en el historial.
        </div>
      </div>
    </div>

    <!-- Grid de períodos -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {#each periods as period}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-lg font-semibold text-slate-800">{monthAbbr[period.month]} {period.year}</p>
              <p class="mt-0.5 text-xs text-slate-500">{period.name}</p>
            </div>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {statusCls(period.status)}">
              {period.status === 'open' ? 'Abierto' : 'Cerrado'}
            </span>
          </div>

          {#if period.status === 'closed' && period.closed_at}
            <p class="mt-3 text-xs text-slate-400">Cerrado: {period.closed_at}</p>
          {/if}

          {#if period.notes}
            <p class="mt-1 text-xs text-slate-500 italic truncate" title={period.notes}>{period.notes}</p>
          {/if}

          <div class="mt-4 flex gap-2">
            {#if period.is_open}
              <button
                onclick={() => { confirmClose = period.id; closeForm.notes = '' }}
                class="flex-1 rounded-lg border border-rose-200 bg-rose-50 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
              >
                <i class="mdi mdi-lock mr-1"></i>Cerrar
              </button>
            {:else}
              <button
                onclick={() => { confirmReopen = period.id; reopenForm.notes = '' }}
                class="flex-1 rounded-lg border border-amber-200 bg-amber-50 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100"
              >
                <i class="mdi mdi-lock-open mr-1"></i>Reabrir
              </button>
            {/if}
          </div>
        </div>
      {/each}
    </div>
  </div>

  <!-- Modal: confirmar cierre de período -->
  {#if confirmClose}
    {@const period = periods.find(p => p.id === confirmClose)}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-base font-semibold text-slate-800">Cerrar período: {period?.name}</h3>
        <p class="mt-2 text-sm text-slate-600">
          Los asientos con fecha dentro de <strong>{period?.name}</strong> quedarán bloqueados.
          Esta acción es reversible pero requiere justificación.
        </p>
        <div class="mt-4">
          <label class="block text-xs font-medium text-slate-600 mb-1">Notas (opcional)</label>
          <textarea
            bind:value={closeForm.notes}
            rows="2"
            placeholder="Ej: Cierre rutinario de enero 2026"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
          ></textarea>
        </div>
        <div class="mt-5 flex gap-3 justify-end">
          <button
            onclick={() => confirmClose = null}
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
          >Cancelar</button>
          <button
            onclick={() => submitClose(period)}
            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
          >Confirmar cierre</button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: confirmar reapertura de período -->
  {#if confirmReopen}
    {@const period = periods.find(p => p.id === confirmReopen)}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-base font-semibold text-slate-800">Reabrir período: {period?.name}</h3>
        <p class="mt-2 text-sm text-slate-600">
          Se permitirán nuevamente asientos en <strong>{period?.name}</strong>.
          La justificación queda registrada en el historial del período.
        </p>
        <div class="mt-4">
          <label class="block text-xs font-medium text-slate-600 mb-1">Justificación <span class="text-rose-500">*</span></label>
          <textarea
            bind:value={reopenForm.notes}
            rows="3"
            placeholder="Explique el motivo de la reapertura (mín. 10 caracteres)"
            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
          ></textarea>
        </div>
        <div class="mt-5 flex gap-3 justify-end">
          <button
            onclick={() => confirmReopen = null}
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
          >Cancelar</button>
          <button
            onclick={() => submitReopen(period)}
            disabled={reopenForm.notes.length < 10}
            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-40 disabled:cursor-not-allowed"
          >Confirmar reapertura</button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: cierre anual -->
  {#if showCloseYear}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-base font-semibold text-slate-800">Cierre anual</h3>
        <p class="mt-2 text-sm text-slate-600">
          Cierra todos los períodos abiertos de un año anterior.
          Solo se puede aplicar a años ya vencidos.
        </p>
        <div class="mt-4 space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Año a cerrar</label>
            <input
              type="number"
              bind:value={closeYearForm.year}
              min="2020"
              max={selectedYear - 1}
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Notas (opcional)</label>
            <textarea
              bind:value={closeYearForm.notes}
              rows="2"
              placeholder="Ej: Cierre contable año fiscal 2025"
              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"
            ></textarea>
          </div>
        </div>
        <div class="mt-5 flex gap-3 justify-end">
          <button
            onclick={() => showCloseYear = false}
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
          >Cancelar</button>
          <button
            onclick={submitCloseYear}
            class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
          >Ejecutar cierre anual</button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
