<script>
  import { useForm } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { banks, suppliers } = $props()

  // ── Modal banco ──────────────────────────────────────────────────────────────
  let showBankModal  = $state(false)
  let editingBank    = $state(null)

  const bankForm = useForm({ name: '', third_party_id: '' })

  function openCreateBank() {
    $bankForm.reset()
    editingBank    = null
    showBankModal  = true
  }

  function openEditBank(bank) {
    $bankForm.name           = bank.name
    $bankForm.third_party_id = bank.third_party_id ?? ''
    editingBank   = bank
    showBankModal = true
  }

  function submitBank() {
    if (editingBank) {
      $bankForm.put(`/cash/banks/${editingBank.id}`, {
        onSuccess: () => { showBankModal = false }
      })
    } else {
      $bankForm.post('/cash/banks', {
        onSuccess: () => { showBankModal = false }
      })
    }
  }

  // ── Modal cuenta bancaria ─────────────────────────────────────────────────────
  let showAccountModal = $state(false)
  let selectedBank     = $state(null)

  const accountForm = useForm({
    name:                '',
    type:                'Ahorro',
    account_bank_number: '',
    has_gmf:             false,
    initial_balance:     '',
  })

  function openCreateAccount(bank) {
    selectedBank = bank
    $accountForm.reset()
    showAccountModal = true
  }

  function submitAccount() {
    $accountForm.post(`/cash/banks/${selectedBank.id}/accounts`, {
      onSuccess: () => { showAccountModal = false }
    })
  }

  // ── Modal movimiento bancario ─────────────────────────────────────────────────
  let showMoveModal  = $state(false)
  let selectedAccount = $state(null)

  const moveForm = useForm({
    type:        'debit',
    amount:      '',
    description: '',
    reference:   '',
    issue_date:  new Date().toISOString().split('T')[0],
  })

  function openMovement(account) {
    selectedAccount = account
    $moveForm.reset()
    $moveForm.issue_date = new Date().toISOString().split('T')[0]
    showMoveModal = true
  }

  function submitMovement() {
    $moveForm.post(`/cash/banks/accounts/${selectedAccount.id}/movements`, {
      onSuccess: () => { showMoveModal = false }
    })
  }

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }
</script>

<AppLayout title="Bancos">
  <!-- Encabezado -->
  <div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <a href="/cash" class="text-slate-400 hover:text-slate-600">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Bancos y Cuentas</h1>
        <p class="text-sm text-slate-500 mt-0.5">Gestión de cuentas bancarias</p>
      </div>
    </div>
    <button onclick={openCreateBank}
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
      <i class="mdi mdi-plus"></i> Nuevo Banco
    </button>
  </div>

  <!-- Lista de bancos -->
  <div class="space-y-4">
    {#each banks as bank}
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <!-- Cabecera del banco -->
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="mdi mdi-bank-outline text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="font-semibold text-slate-800">{bank.name}</p>
              <p class="text-xs text-slate-500">{bank.internal_code}</p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <div class="text-right mr-4">
              <p class="text-xs text-slate-500">Saldo total</p>
              <p class="font-bold text-slate-800">{fmt(bank.total_balance ?? 0)}</p>
            </div>
            <button onclick={() => openCreateAccount(bank)}
                    class="text-sm text-green-600 hover:text-green-800 border border-green-200 hover:border-green-300 px-3 py-1.5 rounded-lg transition-colors">
              <i class="mdi mdi-plus mr-1"></i> Cuenta
            </button>
            <button onclick={() => openEditBank(bank)}
                    class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
              <i class="mdi mdi-pencil-outline"></i>
            </button>
          </div>
        </div>

        <!-- Cuentas del banco -->
        {#if bank.bank_accounts?.length > 0}
          <table class="w-full text-sm">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-5 py-2.5 text-left font-semibold text-slate-600">Cuenta</th>
                <th class="px-5 py-2.5 text-left font-semibold text-slate-600">Número</th>
                <th class="px-5 py-2.5 text-left font-semibold text-slate-600">Tipo</th>
                <th class="px-5 py-2.5 text-right font-semibold text-slate-600">Saldo</th>
                <th class="px-5 py-2.5 text-center font-semibold text-slate-600">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              {#each bank.bank_accounts as account}
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-slate-800">{account.name}</p>
                    <p class="text-xs text-slate-400">{account.internal_code}</p>
                  </td>
                  <td class="px-5 py-3 text-slate-600 font-mono text-xs">
                    {account.account_bank_number ?? '—'}
                  </td>
                  <td class="px-5 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                 {account.type === 'Ahorro' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}">
                      {account.type}
                    </span>
                    {#if account.has_gmf}
                      <span class="ml-1 text-xs text-orange-600">4×1000</span>
                    {/if}
                  </td>
                  <td class="px-5 py-3 text-right font-medium text-slate-800">
                    {fmt(account.current_balance ?? 0)}
                  </td>
                  <td class="px-5 py-3 text-center">
                    <button onclick={() => openMovement(account)}
                            class="text-blue-600 hover:text-blue-800 font-medium text-xs">
                      Movimiento
                    </button>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        {:else}
          <div class="px-5 py-6 text-center text-sm text-slate-400">
            <i class="mdi mdi-credit-card-outline text-2xl block mb-1 opacity-50"></i>
            Sin cuentas bancarias
            <button onclick={() => openCreateAccount(bank)}
                    class="ml-2 text-blue-600 hover:underline">Agregar</button>
          </div>
        {/if}
      </div>
    {:else}
      <div class="py-16 text-center text-slate-400">
        <i class="mdi mdi-bank-outline text-5xl block mb-3 opacity-40"></i>
        <p class="text-lg font-medium">No hay bancos registrados</p>
        <button onclick={openCreateBank}
                class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-sm px-5 py-2.5 rounded-lg">
          Crear Banco
        </button>
      </div>
    {/each}
  </div>

  <!-- Modal: crear/editar banco -->
  {#if showBankModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">{editingBank ? 'Editar Banco' : 'Nuevo Banco'}</h2>
          <button onclick={() => showBankModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre del banco <span class="text-red-500">*</span></label>
            <input bind:value={$bankForm.name} type="text" placeholder="Ej: Bancolombia"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            {#if $bankForm.errors.name}<p class="text-xs text-red-600 mt-1">{$bankForm.errors.name}</p>{/if}
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tercero asociado</label>
            <select bind:value={$bankForm.third_party_id}
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">— Sin tercero —</option>
              {#each suppliers as s}
                <option value={s.id}>{s.business_name}</option>
              {/each}
            </select>
          </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showBankModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitBank}
                  disabled={$bankForm.processing}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$bankForm.processing ? 'Guardando…' : (editingBank ? 'Actualizar' : 'Crear')}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: crear cuenta bancaria -->
  {#if showAccountModal && selectedBank}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Nueva Cuenta — {selectedBank.name}</h2>
          <button onclick={() => showAccountModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Nombre <span class="text-red-500">*</span></label>
            <input bind:value={$accountForm.name} type="text" placeholder="Ej: Cuenta Ahorros Principal"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Tipo <span class="text-red-500">*</span></label>
              <select bind:value={$accountForm.type}
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="Ahorro">Ahorro</option>
                <option value="Corriente">Corriente</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Número de cuenta</label>
              <input bind:value={$accountForm.account_bank_number} type="text" placeholder="Opcional"
                     class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Saldo inicial</label>
            <input bind:value={$accountForm.initial_balance} type="number" min="0" placeholder="0"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" bind:checked={$accountForm.has_gmf} class="rounded border-slate-300 text-blue-600"/>
            <span class="text-sm text-slate-700">Aplica gravamen 4×1000</span>
          </label>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showAccountModal = false}
                  class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitAccount}
                  disabled={$accountForm.processing}
                  class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$accountForm.processing ? 'Creando…' : 'Crear Cuenta'}
          </button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Modal: movimiento bancario -->
  {#if showMoveModal && selectedAccount}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 class="font-semibold text-slate-800">Movimiento — {selectedAccount.name}</h2>
          <button onclick={() => showMoveModal = false} class="text-slate-400 hover:text-slate-600">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>
        <div class="px-6 py-4 space-y-4">
          <div class="flex gap-2">
            <button onclick={() => $moveForm.type = 'debit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$moveForm.type === 'debit' ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 text-slate-600'}">
              <i class="mdi mdi-arrow-down-circle-outline mr-1"></i> Consignación
            </button>
            <button onclick={() => $moveForm.type = 'credit'}
                    class="flex-1 py-2 rounded-lg text-sm font-medium border-2 transition-colors
                           {$moveForm.type === 'credit' ? 'border-red-500 bg-red-50 text-red-700' : 'border-slate-200 text-slate-600'}">
              <i class="mdi mdi-arrow-up-circle-outline mr-1"></i> Retiro
            </button>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Valor <span class="text-red-500">*</span></label>
            <input bind:value={$moveForm.amount} type="number" min="0" placeholder="0"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Descripción <span class="text-red-500">*</span></label>
            <input bind:value={$moveForm.description} type="text"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Referencia</label>
            <input bind:value={$moveForm.reference} type="text"
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
