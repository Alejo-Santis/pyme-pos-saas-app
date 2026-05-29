<script>
  import { useForm, router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { cashBoxes, totalBalance } = $props()

  // ── Modal nueva caja ────────────────────────────────────────────────────────
  let showCreateModal = $state(false)
  let editingBox      = $state(null)

  const form = useForm({ name: '', is_main: false })

  function openCreate() {
    $form.reset()
    editingBox = null
    showCreateModal = true
  }

  function openEdit(box) {
    $form.name    = box.name
    $form.is_main = box.is_main
    editingBox    = box
    showCreateModal = true
  }

  function submitBox() {
    if (editingBox) {
      $form.put(`/cash/boxes/${editingBox.id}`, {
        onSuccess: () => { showCreateModal = false }
      })
    } else {
      $form.post('/cash/boxes', {
        onSuccess: () => { showCreateModal = false }
      })
    }
  }

  // ── Modal movimiento manual ──────────────────────────────────────────────────
  let selectedBox   = $state(null)
  let showMoveModal = $state(false)

  const moveForm = useForm({
    type:        'debit',
    amount:      '',
    description: '',
    reference:   '',
    issue_date:  new Date().toISOString().split('T')[0],
  })

  function openMovement(box) {
    selectedBox = box
    $moveForm.reset()
    $moveForm.issue_date = new Date().toISOString().split('T')[0]
    showMoveModal = true
  }

  function submitMovement() {
    $moveForm.post(`/cash/boxes/${selectedBox.id}/movements`, {
      onSuccess: () => { showMoveModal = false }
    })
  }

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }
</script>

<AppLayout title="Caja y Bancos">
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Caja y Bancos</h1>
      <p class="text-sm text-slate-500 mt-0.5">Gestión de efectivo y cuentas bancarias</p>
    </div>
    <div class="flex gap-2">
      <a href="/cash/receipts"
         class="inline-flex items-center gap-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
        <i class="mdi mdi-receipt-text-outline"></i> Recibos
      </a>
      <a href="/cash/banks"
         class="inline-flex items-center gap-2 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
        <i class="mdi mdi-bank-outline"></i> Bancos
      </a>
      <button onclick={openCreate}
              class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
        <i class="mdi mdi-plus"></i> Nueva Caja
      </button>
    </div>
  </div>

  <!-- Resumen total -->
  <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 text-white mb-6">
    <p class="text-blue-200 text-sm font-medium">Saldo total en cajas</p>
    <p class="text-4xl font-bold mt-1">{fmt(totalBalance)}</p>
    <p class="text-blue-200 text-sm mt-2">{cashBoxes.length} caja{cashBoxes.length !== 1 ? 's' : ''} activa{cashBoxes.length !== 1 ? 's' : ''}</p>
  </div>

  <!-- Grid de cajas -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    {#each cashBoxes as box}
      <div class="bg-white rounded-xl border border-slate-200 p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="mdi mdi-cash-multiple text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="font-semibold text-slate-800">{box.name}</p>
              <p class="text-xs text-slate-500">{box.internal_code}</p>
            </div>
          </div>
          {#if box.is_main}
            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Principal</span>
          {/if}
        </div>

        <div class="mt-4">
          <p class="text-xs text-slate-500 mb-0.5">Saldo actual</p>
          <p class="text-2xl font-bold {box.current_balance >= 0 ? 'text-slate-800' : 'text-red-600'}">
            {fmt(box.current_balance)}
          </p>
        </div>

        <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
          <a href="/cash/boxes/{box.id}"
             class="flex-1 text-center text-sm text-blue-600 hover:text-blue-800 font-medium py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
            Ver detalle
          </a>
          <button onclick={() => openMovement(box)}
                  class="flex-1 text-center text-sm text-green-600 hover:text-green-800 font-medium py-1.5 rounded-lg hover:bg-green-50 transition-colors">
            Movimiento
          </button>
          <button onclick={() => openEdit(box)}
                  class="text-sm text-slate-400 hover:text-slate-600 px-2 py-1.5 rounded-lg hover:bg-slate-50 transition-colors">
            <i class="mdi mdi-pencil-outline"></i>
          </button>
        </div>
      </div>
    {:else}
      <div class="col-span-3 py-16 text-center text-slate-400">
        <i class="mdi mdi-cash-off text-5xl block mb-3 opacity-40"></i>
        <p class="text-lg font-medium">No hay cajas registradas</p>
        <p class="text-sm mt-1">Crea tu primera caja para gestionar el efectivo</p>
        <button onclick={openCreate}
                class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-sm px-5 py-2.5 rounded-lg">
          Crear Caja
        </button>
      </div>
    {/each}
  </div>

  <!-- Modal: crear/editar caja -->
  {#if showCreateModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">{editingBox ? 'Editar Caja' : 'Nueva Caja'}</h2>
          <button onclick={() => showCreateModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input bind:value={$form.name} type="text" placeholder="Ej: Caja General"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            {#if $form.errors.name}<p class="text-xs text-red-600 mt-1">{$form.errors.name}</p>{/if}
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" bind:checked={$form.is_main} class="rounded border-slate-300 text-blue-600"/>
            <span class="text-sm text-slate-700">Marcar como caja principal</span>
          </label>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showCreateModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitBox}
                  disabled={$form.processing}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$form.processing ? 'Guardando…' : (editingBox ? 'Actualizar' : 'Crear Caja')}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: movimiento manual -->
  {#if showMoveModal && selectedBox}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Movimiento — {selectedBox.name}</h2>
          <button onclick={() => showMoveModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <!-- Tipo -->
          <div class="flex gap-2">
            <button onclick={() => $moveForm.type = 'debit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$moveForm.type === 'debit' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'}">
              <i class="mdi mdi-arrow-down-circle-outline mr-1"></i> Ingreso
            </button>
            <button onclick={() => $moveForm.type = 'credit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$moveForm.type === 'credit' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'}">
              <i class="mdi mdi-arrow-up-circle-outline mr-1"></i> Egreso
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Valor <span class="text-red-500">*</span></label>
            <input bind:value={$moveForm.amount} type="number" min="0" step="1" placeholder="0"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción <span class="text-red-500">*</span></label>
            <input bind:value={$moveForm.description} type="text" placeholder="Concepto del movimiento"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Referencia</label>
            <input bind:value={$moveForm.reference} type="text" placeholder="Número de referencia"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha <span class="text-red-500">*</span></label>
            <input bind:value={$moveForm.issue_date} type="date"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showMoveModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitMovement}
                  disabled={$moveForm.processing}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$moveForm.processing ? 'Guardando…' : 'Registrar'}
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
