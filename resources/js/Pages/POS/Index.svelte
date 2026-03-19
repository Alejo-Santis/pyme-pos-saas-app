<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'
  import { router, page } from '@inertiajs/svelte'
  import { toast } from '@/Stores/toast.svelte.js'

  let { terminals, myShift, cashBoxes = [] } = $props()

  let flash  = $derived($page.props.flash  ?? {})
  let errors = $derived($page.props.errors ?? {})

  // Modal abrir turno
  let showOpenModal  = $state(false)
  let selectedTerminal = $state(null)
  let initialBalance = $state(0)
  let opening = $state(false)

  // Modal nueva terminal
  let showTerminalModal = $state(false)
  let editingTerminal   = $state(null)
  const emptyTerminalForm = () => ({
    name: '', location: '', resolution_id: '', warehouse_id: '',
    establishment_id: '', cash_box_id: '',
    printer_name: '', printer_ip: '', printer_port: '9100',
    printer_type: 'escpos', is_usb: true, state: true,
  })
  let terminalForm = $state(emptyTerminalForm())
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

  // ── Modal confirmación eliminar terminal ────────────────────────────────
  let confirmDelete = $state({ open: false, terminal: null })

  // ── Modal cuadre de caja (cierre de turno) ───────────────────────────────
  let showCloseModal  = $state(false)
  let closeTerminal   = $state(null)
  let closeSummary    = $state(null)   // datos del turno desde el servidor
  let closeSummaryLoading = $state(false)
  let countedCash     = $state(0)
  let closeNotes      = $state('')
  let closing         = $state(false)

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const fmtCOP = (n) => '$' + fmt(n)

  const expectedCash  = $derived(closeSummary ? closeSummary.expected_cash : 0)
  const difference    = $derived(Number(countedCash) - expectedCash)
  const diffPositive  = $derived(difference >= 0)

  async function closeShift(t) {
    closeTerminal = t
    countedCash   = 0
    closeNotes    = ''
    closeSummary  = null
    showCloseModal = true
    // Cargar totales del turno
    closeSummaryLoading = true
    try {
      const res = await fetch(`/pos/${t.id}/shift-summary`, {
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }
      })
      if (res.ok) closeSummary = await res.json()
    } finally {
      closeSummaryLoading = false
    }
  }

  async function submitCloseShift() {
    if (!closeTerminal) return
    closing = true
    try {
      const res = await fetch(`/pos/${closeTerminal.id}/close`, {
        method:  'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
        },
        body: JSON.stringify({
          counted_cash: Number(countedCash),
          close_notes:  closeNotes.trim() || null,
        }),
      })
      const data = await res.json()
      if (data.success) {
        showCloseModal = false
        toast.success(data.message)
        // Partial reload: solo actualiza myShift y terminals, sin swap de componente
        router.reload({ only: ['myShift', 'terminals'], preserveScroll: true })
      } else {
        toast.error(data.message ?? 'Error al cerrar el turno.')
      }
    } catch {
      toast.error('Error de red al cerrar el turno.')
    } finally {
      closing = false
    }
  }

  function openNewTerminal() {
    editingTerminal = null
    terminalForm    = emptyTerminalForm()
    showTerminalModal = true
  }

  function openEditTerminal(t) {
    editingTerminal = t
    terminalForm = {
      name: t.name, location: t.location ?? '',
      resolution_id: t.resolution_id ?? '', warehouse_id: t.warehouse_id ?? '',
      establishment_id: t.establishment_id ?? '', cash_box_id: t.cash_box_id ?? '',
      printer_name: t.printer_name ?? '', printer_ip: t.printer_ip ?? '',
      printer_port: t.printer_port ?? '9100', printer_type: t.printer_type ?? 'escpos',
      is_usb: t.is_usb ?? true, state: t.state ?? true,
    }
    showTerminalModal = true
  }

  function saveTerminal() {
    savingTerminal = true
    const url    = editingTerminal ? `/pos/terminals/${editingTerminal.id}` : '/pos/terminals'
    const method = editingTerminal ? 'put' : 'post'
    router[method](url, terminalForm, {
      preserveScroll: true,
      onSuccess: () => { showTerminalModal = false; editingTerminal = null },
      onFinish:  () => { savingTerminal = false },
    })
  }

  function destroyTerminal(t) {
    confirmDelete = { open: true, terminal: t }
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
        onclick={openNewTerminal}
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
              {#if t.cash_box}
                <div class="flex items-center gap-1.5">
                  <i class="mdi mdi-cash-register text-slate-400"></i>
                  Caja: {t.cash_box.name}
                </div>
              {:else}
                <div class="flex items-center gap-1.5 text-amber-500">
                  <i class="mdi mdi-alert-outline text-amber-400"></i>
                  Sin caja asignada
                </div>
              {/if}
              {#if t.printer_name}
                <div class="flex items-center gap-1.5 text-slate-500">
                  <i class="mdi mdi-printer text-slate-400"></i>
                  {t.printer_name}
                  {#if t.is_usb}
                    <span class="text-[10px] bg-slate-100 rounded px-1">USB</span>
                  {:else}
                    <span class="text-[10px] bg-slate-100 rounded px-1">{t.printer_ip}:{t.printer_port}</span>
                  {/if}
                </div>
              {:else}
                <div class="flex items-center gap-1.5 text-slate-400">
                  <i class="mdi mdi-printer-off text-slate-300"></i>
                  Sin impresora
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
              <!-- Editar / Eliminar -->
              {#if !mine && !busy}
                <button
                  onclick={() => openEditTerminal(t)}
                  class="px-3 py-2 border border-slate-200 text-slate-500 text-xs rounded-lg hover:bg-slate-50 transition cursor-pointer"
                  title="Editar terminal">
                  <i class="mdi mdi-pencil-outline text-sm"></i>
                </button>
                <button
                  onclick={() => destroyTerminal(t)}
                  class="px-3 py-2 border border-slate-200 text-slate-400 text-xs rounded-lg hover:bg-red-50 hover:text-red-500 hover:border-red-200 transition cursor-pointer"
                  title="Eliminar terminal">
                  <i class="mdi mdi-trash-can-outline text-sm"></i>
                </button>
              {/if}
            </div>

          </div>
        {/each}
      </div>
    {/if}

  </div>
</AppLayout>

<!-- ── Modal Cuadre de Caja ──────────────────────────────────────────────── -->
{#if showCloseModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    onclick={(e) => { if (e.target === e.currentTarget && !closing) showCloseModal = false }}>
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden">

      <!-- Cabecera -->
      <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-200">
        <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
          <i class="mdi mdi-cash-register text-blue-700 text-lg"></i>
        </div>
        <div class="flex-1">
          <h2 class="font-bold text-slate-800 text-base">Cuadre de Caja</h2>
          <p class="text-xs text-slate-500">{closeTerminal?.name}</p>
        </div>
        {#if !closing}
          <button onclick={() => showCloseModal = false} class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        {/if}
      </div>

      <!-- Cuerpo -->
      <div class="px-6 py-5 space-y-4">

        {#if closeSummaryLoading}
          <div class="flex items-center justify-center py-6 text-slate-400 gap-2">
            <i class="mdi mdi-loading mdi-spin text-2xl"></i>
            <span class="text-sm">Calculando totales del turno…</span>
          </div>
        {:else if closeSummary}

          <!-- Resumen del sistema -->
          <div class="bg-slate-50 rounded-xl border border-slate-200 divide-y divide-slate-200 text-sm">
            <div class="flex justify-between px-4 py-2.5 text-slate-600">
              <span>Base inicial</span>
              <span class="font-medium tabular-nums">{fmtCOP(closeSummary.initial_balance)}</span>
            </div>
            <div class="flex justify-between px-4 py-2.5 text-slate-600">
              <span>Ventas totales ({closeSummary.sales_count} tickets)</span>
              <span class="font-semibold tabular-nums text-slate-800">{fmtCOP(closeSummary.total_sales)}</span>
            </div>
            <div class="flex justify-between px-4 py-2.5 text-slate-600">
              <span>Ingresos en efectivo</span>
              <span class="font-medium tabular-nums">{fmtCOP(closeSummary.total_cash)}</span>
            </div>
            {#if closeSummary.total_transfer > 0}
              <div class="flex justify-between px-4 py-2.5 text-slate-600">
                <span>Transferencias / tarjeta</span>
                <span class="font-medium tabular-nums">{fmtCOP(closeSummary.total_transfer)}</span>
              </div>
            {/if}
            <div class="flex justify-between px-4 py-2.5 font-semibold text-blue-700 bg-blue-50">
              <span>Efectivo esperado en caja</span>
              <span class="tabular-nums">{fmtCOP(closeSummary.expected_cash)}</span>
            </div>
          </div>

          <!-- Conteo físico -->
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">
              Efectivo contado físicamente <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium">$</span>
              <input
                type="number"
                min="0"
                step="1000"
                bind:value={countedCash}
                class="w-full pl-7 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300 tabular-nums text-right text-lg font-bold"
                placeholder="0"
              />
            </div>
          </div>

          <!-- Diferencia -->
          <div class="rounded-xl border px-4 py-3 flex items-center justify-between
                      {diffPositive ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}">
            <span class="text-sm font-semibold {diffPositive ? 'text-green-700' : 'text-red-700'}">
              {diffPositive ? 'Sobrante' : 'Faltante'}
            </span>
            <span class="text-lg font-bold tabular-nums {diffPositive ? 'text-green-700' : 'text-red-700'}">
              {diffPositive ? '+' : '-'}{fmtCOP(Math.abs(difference))}
            </span>
          </div>

          <!-- Observaciones -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1.5">
              Observaciones (opcional)
            </label>
            <textarea
              bind:value={closeNotes}
              rows="2"
              placeholder="Ej: Diferencia por billete falso, descuadre de monedas…"
              class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
            ></textarea>
          </div>

        {:else}
          <div class="py-4 text-center text-sm text-red-600">
            <i class="mdi mdi-alert-circle-outline mr-1"></i>
            No se pudo cargar el resumen del turno.
          </div>
        {/if}

      </div>

      <!-- Pie -->
      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
        <button
          onclick={() => showCloseModal = false}
          disabled={closing}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
          Cancelar
        </button>
        <button
          onclick={submitCloseShift}
          disabled={closing || !closeSummary}
          class="flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition cursor-pointer disabled:opacity-60">
          {#if closing}
            <i class="mdi mdi-loading mdi-spin text-base"></i>
            Cerrando…
          {:else}
            <i class="mdi mdi-lock-outline text-base"></i>
            Cerrar turno
          {/if}
        </button>
      </div>

    </div>
  </div>
{/if}

<!-- Confirmación: eliminar terminal -->
<ConfirmModal
  bind:open={confirmDelete.open}
  title="Eliminar terminal"
  message={`¿Eliminar la terminal "${confirmDelete.terminal?.name}"? Esta acción no se puede deshacer.`}
  confirmLabel="Eliminar"
  onConfirm={() => router.delete(`/pos/terminals/${confirmDelete.terminal.id}`, { preserveScroll: true })}
/>

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

<!-- ── Modal Nueva / Editar Terminal ─────────────────────────────────── -->
{#if showTerminalModal}
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" onclick={() => { showTerminalModal = false; editingTerminal = null }}></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col max-h-[92vh]">

      <!-- Cabecera -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h2 class="text-base font-semibold text-slate-800">
          {editingTerminal ? `Editar: ${editingTerminal.name}` : 'Nueva Terminal POS'}
        </h2>
        <button onclick={() => { showTerminalModal = false; editingTerminal = null }}
          class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <!-- Cuerpo scrollable -->
      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

        <!-- General -->
        <div class="space-y-3">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Datos de la terminal</p>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Nombre *</label>
              <input bind:value={terminalForm.name} type="text"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Caja 1" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Ubicación</label>
              <input bind:value={terminalForm.location} type="text"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Punto de venta principal" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Caja registradora</label>
              <select bind:value={terminalForm.cash_box_id}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Sin caja asignada</option>
                {#each cashBoxes as cb}
                  <option value={cb.id}>{cb.name} ({cb.internal_code})</option>
                {/each}
              </select>
            </div>
          </div>
        </div>

        <!-- Impresora -->
        <div class="space-y-3 border-t border-slate-100 pt-4">
          <div class="flex items-center gap-2">
            <i class="mdi mdi-printer text-slate-400"></i>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Impresora térmica (QZ Tray)</p>
          </div>
          <p class="text-xs text-slate-400">
            Requiere <a href="https://qz.io/download/" target="_blank" class="text-blue-600 underline">QZ Tray</a>
            instalado en el computador de la caja. Dejar vacío si no hay impresora.
          </p>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Nombre exacto de la impresora en el SO</label>
            <input bind:value={terminalForm.printer_name} type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Ej: XP-58 · Epson TM-T20III · StarMicronics" />
            <p class="text-xs text-slate-400 mt-1">Debe coincidir exactamente con el nombre en Windows/Mac/Linux</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de protocolo</label>
              <select bind:value={terminalForm.printer_type}
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="escpos">ESC/POS (estándar)</option>
                <option value="star">Star Micronics</option>
                <option value="80mm">ESC/POS 80mm</option>
              </select>
            </div>
            <div>
              <label class="flex items-center gap-2 text-xs font-medium text-slate-600 mb-1 h-5">
                Conexión
              </label>
              <div class="flex items-center gap-3 pt-1.5">
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                  <input type="radio" bind:group={terminalForm.is_usb} value={true} class="cursor-pointer" />
                  USB
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                  <input type="radio" bind:group={terminalForm.is_usb} value={false} class="cursor-pointer" />
                  Red/IP
                </label>
              </div>
            </div>
          </div>

          {#if !terminalForm.is_usb}
            <div class="grid grid-cols-3 gap-3">
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">IP de la impresora</label>
                <input bind:value={terminalForm.printer_ip} type="text"
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="192.168.1.100" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Puerto</label>
                <input bind:value={terminalForm.printer_port} type="number"
                  class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="9100" />
              </div>
            </div>
          {/if}
        </div>

      </div>

      <!-- Pie -->
      <div class="px-6 py-4 border-t border-slate-200 flex gap-3">
        <button onclick={() => { showTerminalModal = false; editingTerminal = null }}
          class="flex-1 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          Cancelar
        </button>
        <button onclick={saveTerminal} disabled={savingTerminal}
          class="flex-1 flex items-center justify-center gap-1.5 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-60 transition cursor-pointer">
          <i class="mdi {savingTerminal ? 'mdi-loading mdi-spin' : 'mdi-content-save-outline'}"></i>
          {savingTerminal ? 'Guardando…' : (editingTerminal ? 'Guardar cambios' : 'Crear terminal')}
        </button>
      </div>

    </div>
  </div>
{/if}

