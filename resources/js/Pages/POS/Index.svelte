<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import { router, page } from '@inertiajs/svelte'

  let { terminals, myShift } = $props()

  let flash  = $derived($page.props.flash  ?? {})
  let errors = $derived($page.props.errors ?? {})

  // Modal abrir turno
  let showOpenModal  = $state(false)
  let selectedTerminal = $state(null)
  let initialBalance = $state(0)
  let opening = $state(false)

  // Modal nueva terminal
  let showTerminalModal = $state(false)
  let terminalForm = $state({ name: '', location: '', resolution_id: '', warehouse_id: '', establishment_id: '' })
  let savingTerminal = $state(false)

  function openShiftModal(t) {
    selectedTerminal = t
    initialBalance   = 0
    showOpenModal    = true
  }

  function confirmOpenShift() {
    if (!selectedTerminal) return
    opening = true
    router.post(`/pos/${selectedTerminal.id}/open`, { initial_balance: initialBalance }, {
      onFinish: () => { opening = false },
      onSuccess: () => { showOpenModal = false },
    })
  }

  function goToTerminal(t) {
    router.visit(`/pos/${t.id}`)
  }

  function closeShift(t) {
    if (confirm(`¿Cerrar el turno en "${t.name}"?`)) {
      router.post(`/pos/${t.id}/close`)
    }
  }

  function saveTerminal() {
    savingTerminal = true
    router.post('/pos/terminals', terminalForm, {
      preserveScroll: true,
      onSuccess: () => { showTerminalModal = false; terminalForm = { name: '', location: '', resolution_id: '', warehouse_id: '', establishment_id: '' } },
      onFinish:  () => { savingTerminal = false },
    })
  }

  const isMyActiveTerminal = (t) => myShift?.pos_terminal_id === t.id
</script>

<AppLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Punto de Venta</h1>
        <p class="text-sm text-slate-500 mt-0.5">Selecciona una terminal para comenzar</p>
      </div>
      <button
        onclick={() => showTerminalModal = true}
        class="flex items-center gap-2 px-4 py-2 border border-slate-300 text-slate-600 text-sm font-medium rounded-lg hover:bg-slate-50 transition cursor-pointer"
      >
        <i class="mdi mdi-plus"></i> Nueva Terminal
      </button>
    </div>

    <!-- Flash / errores -->
    {#if flash.success}
      <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        <i class="mdi mdi-check-circle-outline"></i> {flash.success}
      </div>
    {/if}
    {#if errors.terminal || errors.shift}
      <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
        <i class="mdi mdi-alert-circle-outline"></i> {errors.terminal ?? errors.shift}
      </div>
    {/if}

    <!-- Mi turno activo -->
    {#if myShift}
      <div class="flex items-center justify-between px-5 py-4 bg-blue-50 border border-blue-200 rounded-xl">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
            <i class="mdi mdi-point-of-sale text-white text-xl"></i>
          </div>
          <div>
            <p class="text-sm font-semibold text-blue-900">Turno activo: {myShift.terminal?.name}</p>
            <p class="text-xs text-blue-600">Sesión: {myShift.cashier_session_key} · Base: ${Number(myShift.initial_balance).toLocaleString('es-CO')}</p>
          </div>
        </div>
        <button
          onclick={() => goToTerminal(myShift.terminal)}
          class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer"
        >
          Continuar venta <i class="mdi mdi-arrow-right ml-1"></i>
        </button>
      </div>
    {/if}

    <!-- Grid de terminales -->
    {#if terminals.length === 0}
      <div class="text-center py-20 bg-white rounded-xl border border-slate-200">
        <i class="mdi mdi-point-of-sale text-6xl text-slate-300 block mb-3"></i>
        <p class="text-slate-500">No hay terminales configuradas.</p>
        <button onclick={() => showTerminalModal = true} class="mt-3 text-blue-600 text-sm underline cursor-pointer">Crear primera terminal</button>
      </div>
    {:else}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {#each terminals as t}
          {@const busy = t.active_terminal_user && !isMyActiveTerminal(t)}
          {@const mine = isMyActiveTerminal(t)}
          <div class="bg-white rounded-xl border {mine ? 'border-blue-400 shadow-blue-100' : busy ? 'border-amber-300' : 'border-slate-200'} shadow-sm p-5 flex flex-col gap-4">

            <!-- Info terminal -->
            <div class="flex items-start justify-between">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center
                  {mine ? 'bg-blue-100' : busy ? 'bg-amber-100' : 'bg-slate-100'}">
                  <i class="mdi mdi-point-of-sale text-xl {mine ? 'text-blue-600' : busy ? 'text-amber-600' : 'text-slate-500'}"></i>
                </div>
                <div>
                  <p class="text-sm font-semibold text-slate-800">{t.name}</p>
                  {#if t.location}
                    <p class="text-xs text-slate-500">{t.location}</p>
                  {/if}
                </div>
              </div>

              <!-- Badge estado -->
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full
                {mine ? 'bg-blue-100 text-blue-700' : busy ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'}">
                {mine ? 'Mi turno' : busy ? 'Ocupada' : 'Disponible'}
              </span>
            </div>

            <!-- Detalles -->
            <div class="space-y-1 text-xs text-slate-500">
              {#if t.resolution}
                <div class="flex items-center gap-1.5">
                  <i class="mdi mdi-file-certificate-outline text-slate-400"></i>
                  Res. {t.resolution.prefix ?? 'Sin prefijo'} · {t.resolution.current_number}/{t.resolution.to}
                </div>
              {/if}
              {#if t.warehouse}
                <div class="flex items-center gap-1.5">
                  <i class="mdi mdi-warehouse text-slate-400"></i>
                  {t.warehouse.name}
                </div>
              {/if}
              {#if busy}
                <div class="flex items-center gap-1.5 text-amber-600">
                  <i class="mdi mdi-account text-amber-400"></i>
                  {t.active_terminal_user?.user?.name ?? 'Otro cajero'}
                </div>
              {/if}
            </div>

            <!-- Acciones -->
            <div class="flex gap-2 pt-1 border-t border-slate-100">
              {#if mine}
                <button
                  onclick={() => goToTerminal(t)}
                  class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition cursor-pointer"
                >
                  <i class="mdi mdi-storefront-outline"></i> Ir a la caja
                </button>
                <button
                  onclick={() => closeShift(t)}
                  class="px-3 py-2 border border-red-200 text-red-500 text-xs rounded-lg hover:bg-red-50 transition cursor-pointer"
                  title="Cerrar turno"
                >
                  <i class="mdi mdi-door-open text-sm"></i>
                </button>
              {:else if !busy}
                <button
                  onclick={() => openShiftModal(t)}
                  class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition cursor-pointer"
                >
                  <i class="mdi mdi-login-variant"></i> Abrir turno
                </button>
              {:else}
                <button disabled class="flex-1 py-2 bg-slate-100 text-slate-400 text-xs rounded-lg cursor-not-allowed">
                  Terminal ocupada
                </button>
              {/if}
            </div>

          </div>
        {/each}
      </div>
    {/if}

  </div>
</AppLayout>

<!-- ── Modal Abrir Turno ──────────────────────────────────────────────── -->
{#if showOpenModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick={() => showOpenModal = false}></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">

      <h2 class="text-base font-semibold text-slate-800">
        Abrir Turno — {selectedTerminal?.name}
      </h2>

      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1">Base de caja inicial ($)</label>
        <input
          type="number"
          min="0"
          step="1000"
          bind:value={initialBalance}
          class="w-full px-3 py-2.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          placeholder="0"
        />
        <p class="text-xs text-slate-400 mt-1">Dinero físico con el que inicia la caja</p>
      </div>

      <div class="flex gap-3 pt-2">
        <button onclick={() => showOpenModal = false} class="flex-1 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          Cancelar
        </button>
        <button
          onclick={confirmOpenShift}
          disabled={opening}
          class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-60 transition cursor-pointer"
        >
          <i class="mdi {opening ? 'mdi-loading mdi-spin' : 'mdi-login-variant'}"></i>
          {opening ? 'Abriendo…' : 'Abrir turno'}
        </button>
      </div>

    </div>
  </div>
{/if}

<!-- ── Modal Nueva Terminal ───────────────────────────────────────────── -->
{#if showTerminalModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick={() => showTerminalModal = false}></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 space-y-4">

      <h2 class="text-base font-semibold text-slate-800">Nueva Terminal POS</h2>

      <div class="space-y-3">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Nombre *</label>
          <input bind:value={terminalForm.name} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Caja 1" />
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Ubicación</label>
          <input bind:value={terminalForm.location} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Punto de venta principal" />
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button onclick={() => showTerminalModal = false} class="flex-1 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          Cancelar
        </button>
        <button
          onclick={saveTerminal}
          disabled={savingTerminal}
          class="flex-1 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60 transition cursor-pointer"
        >
          {savingTerminal ? 'Guardando…' : 'Crear terminal'}
        </button>
      </div>

    </div>
  </div>
{/if}

