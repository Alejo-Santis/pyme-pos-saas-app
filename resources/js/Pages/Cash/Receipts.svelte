<script>
  import { useForm } from '@inertiajs/svelte'
  import { router } from '@inertiajs/svelte'
  import { onMount } from 'svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    cashReceipts,
    paymentReceipts,
    filters = {},
    summary = {},
    thirdParties = [],
    cashBoxes = [],
    bankAccounts = [],
    receivableDocuments = [],
    payableDocuments = [],
  } = $props()

  let activeTab = $state('cash')
  let startDate = $state(filters.start_date ?? '')
  let endDate = $state(filters.end_date ?? '')
  let showModal = $state(false)
  let modalType = $state('cash')

  function applyFilters() {
    router.get('/cash/receipts', { start_date: startDate, end_date: endDate }, {
      preserveState: true,
      replace: true,
    })
  }

  function clearFilters() {
    startDate = ''
    endDate = ''
    router.get('/cash/receipts', {}, { preserveState: true, replace: true })
  }

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d + 'T00:00:00').toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
  }

  const activeList = $derived(activeTab === 'cash' ? cashReceipts : paymentReceipts)
  const allocationDocs = $derived(modalType === 'cash' ? receivableDocuments : payableDocuments)
  const modalTitle = $derived(modalType === 'cash' ? 'Nuevo recibo de caja' : 'Nuevo comprobante de egreso')

  const form = useForm({
    third_party_id: '',
    issue_date: new Date().toISOString().slice(0, 10),
    notes: '',
    payment_forms: [{
      payment_form_id: 1,
      payment_method_id: 10,
      value: '',
      cash_box_id: cashBoxes[0]?.id ?? '',
      bank_account_id: '',
      transaction_reference: '',
    }],
    allocations: [],
  })

  onMount(() => {
    const params = new URLSearchParams(window.location.search)
    const type = params.get('type')
    if (type === 'cash' || type === 'payment') {
      openModal(type)
      $form.third_party_id = params.get('third_party_id') ?? ''

      const documentId = params.get('document_id')
      if (documentId) {
        $form.allocations = [{
          document_id: documentId,
          amount: params.get('amount') ?? selectedDoc(documentId)?.balance ?? '',
          withholdings_tax: 0,
          transaction_reference: '',
        }]
        const amount = Number(params.get('amount') ?? selectedDoc(documentId)?.balance ?? 0)
        if (amount > 0) {
          $form.payment_forms[0].value = amount
        }
      }
    }
  })

  function openModal(type) {
    modalType = type
    $form.reset()
    $form.issue_date = new Date().toISOString().slice(0, 10)
    $form.payment_forms = [{
      payment_form_id: 1,
      payment_method_id: 10,
      value: '',
      cash_box_id: cashBoxes[0]?.id ?? '',
      bank_account_id: '',
      transaction_reference: '',
    }]
    $form.allocations = []
    showModal = true
  }

  function addPayment() {
    $form.payment_forms = [...$form.payment_forms, {
      payment_form_id: 1,
      payment_method_id: 30,
      value: '',
      cash_box_id: '',
      bank_account_id: bankAccounts[0]?.id ?? '',
      transaction_reference: '',
    }]
  }

  function removePayment(idx) {
    $form.payment_forms = $form.payment_forms.length === 1
      ? [$form.payment_forms[0]]
      : $form.payment_forms.filter((_, i) => i !== idx)
  }

  function onPaymentMethodChange(payment) {
    if (Number(payment.payment_method_id) === 10) {
      payment.cash_box_id = payment.cash_box_id || cashBoxes[0]?.id || ''
      payment.bank_account_id = ''
    } else {
      payment.cash_box_id = ''
      payment.bank_account_id = payment.bank_account_id || bankAccounts[0]?.id || ''
    }
    $form.payment_forms = [...$form.payment_forms]
  }

  function addAllocation() {
    $form.allocations = [...$form.allocations, { document_id: '', amount: '', withholdings_tax: 0, transaction_reference: '' }]
  }

  function removeAllocation(idx) {
    $form.allocations = $form.allocations.filter((_, i) => i !== idx)
  }

  function selectedDoc(documentId) {
    return allocationDocs.find((doc) => doc.id === documentId)
  }

  function fillAllocationBalance(allocation) {
    const doc = selectedDoc(allocation.document_id)
    if (doc) {
      allocation.amount = Number(doc.balance || 0)
      $form.allocations = [...$form.allocations]
    }
  }

  function submitReceipt() {
    const url = modalType === 'cash' ? '/cash/receipts' : '/cash/payment-receipts'
    $form.post(url, {
      preserveScroll: true,
      onSuccess: () => {
        showModal = false
        router.reload({ only: ['cashReceipts', 'paymentReceipts', 'summary', 'receivableDocuments', 'payableDocuments'] })
      },
    })
  }
</script>

<AppLayout title="Recibos y Egresos">
  <div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <a href="/cash" class="text-slate-400 hover:text-slate-600" title="Volver">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Recibos y Egresos</h1>
        <p class="text-sm text-slate-500 mt-0.5">Trazabilidad de recaudos, devoluciones y pagos</p>
      </div>
    </div>
    <div class="flex gap-2">
      <button onclick={() => openModal('cash')} class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
        <i class="mdi mdi-receipt-text-plus-outline"></i> Recibo
      </button>
      <button onclick={() => openModal('payment')} class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
        <i class="mdi mdi-cash-refund"></i> Egreso
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
    <button
      onclick={() => activeTab = 'cash'}
      class="text-left bg-white rounded-xl border p-5 transition {activeTab === 'cash' ? 'border-green-400 ring-2 ring-green-100' : 'border-slate-200 hover:border-slate-300'}"
    >
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-500">Recibos de caja</p>
        <i class="mdi mdi-receipt-text-outline text-green-600 text-xl"></i>
      </div>
      <p class="text-2xl font-bold text-green-700 mt-1">{fmt(summary.cash_total)}</p>
    </button>
    <button
      onclick={() => activeTab = 'payment'}
      class="text-left bg-white rounded-xl border p-5 transition {activeTab === 'payment' ? 'border-red-400 ring-2 ring-red-100' : 'border-slate-200 hover:border-slate-300'}"
    >
      <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-500">Comprobantes de egreso</p>
        <i class="mdi mdi-cash-refund text-red-600 text-xl"></i>
      </div>
      <p class="text-2xl font-bold text-red-700 mt-1">{fmt(summary.payment_total)}</p>
    </button>
  </div>

  <div class="bg-white rounded-xl border border-slate-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Desde</label>
      <input bind:value={startDate} type="date" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </div>
    <div>
      <label class="block text-xs font-medium text-slate-600 mb-1">Hasta</label>
      <input bind:value={endDate} type="date" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </div>
    <button onclick={applyFilters} class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-lg">
      Filtrar
    </button>
    {#if startDate || endDate}
      <button onclick={clearFilters} class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
        Limpiar
      </button>
    {/if}
  </div>

  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Código</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Tercero</th>
          <th class="px-4 py-3 text-left font-semibold text-slate-600">Fecha</th>
          <th class="px-4 py-3 text-center font-semibold text-slate-600">Detalles</th>
          <th class="px-4 py-3 text-right font-semibold text-slate-600">Valor</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        {#each activeList.data as receipt}
          <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-3">
              <p class="font-mono text-xs text-slate-600">{receipt.internal_code}</p>
              {#if receipt.annulled}
                <span class="text-[11px] text-red-600 bg-red-50 px-1.5 py-0.5 rounded">Anulado</span>
              {/if}
            </td>
            <td class="px-4 py-3">
              <p class="text-slate-800">{receipt.third_party?.name ?? 'Consumidor final'}</p>
              {#if receipt.third_party?.identification_number}
                <p class="text-xs text-slate-400">{receipt.third_party.identification_number}</p>
              {/if}
            </td>
            <td class="px-4 py-3 text-slate-600">{fmtDate(receipt.issue_date)}</td>
            <td class="px-4 py-3 text-center text-slate-600">{receipt.details_count ?? 0}</td>
            <td class="px-4 py-3 text-right font-semibold {activeTab === 'cash' ? 'text-green-700' : 'text-red-700'}">
              {fmt(receipt.amount_received ?? receipt.total_amount)}
            </td>
          </tr>
        {:else}
          <tr>
            <td colspan="5" class="px-4 py-12 text-center text-slate-400">
              <i class="mdi mdi-receipt-text-remove-outline text-4xl block mb-2 opacity-50"></i>
              Sin registros en el período
            </td>
          </tr>
        {/each}
      </tbody>
    </table>

    {#if activeList.last_page > 1}
      <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Mostrando {activeList.from}–{activeList.to} de {activeList.total}</span>
        <div class="flex gap-1">
          {#each activeList.links as link}
            {#if link.url}
              <button
                onclick={() => router.get(link.url, {}, { preserveState: true })}
                class="px-3 py-1 rounded {link.active ? 'bg-blue-600 text-white' : 'hover:bg-slate-100'}"
              >
                {@html link.label}
              </button>
            {/if}
          {/each}
        </div>
      </div>
    {/if}
  </div>

  {#if showModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
          <div>
            <h2 class="font-semibold text-slate-800">{modalTitle}</h2>
            <p class="text-xs text-slate-500 mt-0.5">Registra el movimiento y cruza saldos pendientes cuando aplique.</p>
          </div>
          <button onclick={() => showModal = false} class="text-slate-400 hover:text-slate-600" title="Cerrar">
            <i class="mdi mdi-close text-xl"></i>
          </button>
        </div>

        <div class="p-5 overflow-y-auto space-y-5">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Tercero</label>
              <select bind:value={$form.third_party_id} class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Sin tercero / consumidor final</option>
                {#each thirdParties as third}
                  <option value={third.id}>{third.name} · {third.identification_number}</option>
                {/each}
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Fecha</label>
              <input bind:value={$form.issue_date} type="date" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Nota</label>
              <input bind:value={$form.notes} type="text" placeholder="Concepto general" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <p class="text-sm font-semibold text-slate-700">Medios de pago</p>
              <button onclick={addPayment} class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                <i class="mdi mdi-plus-circle-outline"></i> Agregar
              </button>
            </div>
            <div class="p-4 space-y-3">
              {#each $form.payment_forms as payment, idx}
                <div class="grid grid-cols-1 md:grid-cols-[1.2fr_1fr_1fr_1fr_2rem] gap-2">
                  <select bind:value={payment.payment_method_id} onchange={() => onPaymentMethodChange(payment)} class="border border-slate-300 rounded-lg px-2 py-2 text-sm">
                    <option value={10}>Efectivo</option>
                    <option value={30}>Transferencia</option>
                    <option value={45}>Transferencia bancaria</option>
                    <option value={47}>Nequi / billetera</option>
                    <option value={48}>Tarjeta crédito</option>
                    <option value={49}>Tarjeta débito</option>
                  </select>

                  {#if Number(payment.payment_method_id) === 10}
                    <select bind:value={payment.cash_box_id} class="border border-slate-300 rounded-lg px-2 py-2 text-sm">
                      {#each cashBoxes as box}
                        <option value={box.id}>{box.name}</option>
                      {/each}
                    </select>
                  {:else}
                    <select bind:value={payment.bank_account_id} class="border border-slate-300 rounded-lg px-2 py-2 text-sm">
                      {#each bankAccounts as account}
                        <option value={account.id}>{account.bank?.name ? account.bank.name + ' · ' : ''}{account.name}</option>
                      {/each}
                    </select>
                  {/if}

                  <input bind:value={payment.value} type="number" min="0" step="1" placeholder="Valor" class="border border-slate-300 rounded-lg px-2 py-2 text-sm text-right" />
                  <input bind:value={payment.transaction_reference} type="text" placeholder="Referencia" class="border border-slate-300 rounded-lg px-2 py-2 text-sm" />
                  <button onclick={() => removePayment(idx)} class="text-slate-400 hover:text-red-600" title="Quitar pago">
                    <i class="mdi mdi-close-circle-outline text-lg"></i>
                  </button>
                </div>
              {/each}
            </div>
          </div>

          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
              <div>
                <p class="text-sm font-semibold text-slate-700">Cruce de documentos</p>
                <p class="text-xs text-slate-500">Opcional. Úsalo para abonos, pagos parciales o saldar cartera/CXP.</p>
              </div>
              <button onclick={addAllocation} class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                <i class="mdi mdi-plus-circle-outline"></i> Agregar cruce
              </button>
            </div>
            <div class="p-4 space-y-3">
              {#each $form.allocations as allocation, idx}
                <div class="grid grid-cols-1 md:grid-cols-[2fr_1fr_1fr_2rem] gap-2">
                  <select bind:value={allocation.document_id} onchange={() => fillAllocationBalance(allocation)} class="border border-slate-300 rounded-lg px-2 py-2 text-sm">
                    <option value="">Seleccionar documento</option>
                    {#each allocationDocs as doc}
                      <option value={doc.id}>
                        {doc.internal_code} · {doc.third_party?.name ?? 'Sin tercero'} · saldo {fmt(doc.balance)}
                      </option>
                    {/each}
                  </select>
                  <input bind:value={allocation.amount} type="number" min="0" step="1" placeholder="Valor" class="border border-slate-300 rounded-lg px-2 py-2 text-sm text-right" />
                  <input bind:value={allocation.transaction_reference} type="text" placeholder="Referencia" class="border border-slate-300 rounded-lg px-2 py-2 text-sm" />
                  <button onclick={() => removeAllocation(idx)} class="text-slate-400 hover:text-red-600" title="Quitar cruce">
                    <i class="mdi mdi-close-circle-outline text-lg"></i>
                  </button>
                </div>
              {:else}
                <p class="text-sm text-slate-400 py-2">Sin cruces. El recibo/egreso quedará como movimiento general.</p>
              {/each}
            </div>
          </div>

          {#if $form.errors.payment_forms || $form.errors.allocations}
            <div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
              {$form.errors.payment_forms ?? $form.errors.allocations}
            </div>
          {/if}
        </div>

        <div class="px-5 py-4 border-t border-slate-200 flex justify-end gap-3">
          <button onclick={() => showModal = false} class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancelar</button>
          <button onclick={submitReceipt} disabled={$form.processing} class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {$form.processing ? 'Guardando...' : 'Registrar'}
          </button>
        </div>
      </div>
    </div>
  {/if}
</AppLayout>
