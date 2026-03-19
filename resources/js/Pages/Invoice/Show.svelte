<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'

  let {
    document           = null,
    documentTypes      = [],
    correctionConcepts = {},
    debitNoteConcepts  = {},
    taxes              = [],
  } = $props()

  // Mapa tipo → nombre legible
  const typeMap = Object.fromEntries(documentTypes.map(t => [String(t.id), t.name]))

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  // ── Totales calculados desde las líneas ──────────────────────────────────
  const lines = $derived(document?.lines ?? [])

  const lineBase     = (l) => Number(l.amount) * Number(l.sale_price)
  const lineDiscount = (l) => lineBase(l) * (Number(l.discount) / 100)
  const lineSubtotal = (l) => lineBase(l) - lineDiscount(l)
  const lineTaxAmt   = (l) => {
    const pct = l.taxes?.[0]?.percent ?? 0
    return lineSubtotal(l) * (Number(pct) / 100)
  }
  const lineTotal    = (l) => lineSubtotal(l) + lineTaxAmt(l)

  const subtotal       = $derived(lines.reduce((s, l) => s + lineSubtotal(l), 0))
  const totalDiscounts = $derived(lines.reduce((s, l) => s + lineDiscount(l), 0))
  const totalTax       = $derived(lines.reduce((s, l) => s + lineTaxAmt(l), 0))
  const total          = $derived(subtotal + totalTax)

  // ── Estado del documento ─────────────────────────────────────────────────
  const statusBadge = $derived(() => {
    if (document?.annulled)   return { label: 'Anulado',      cls: 'bg-red-100 text-red-700' }
    if (document?.electronic) return { label: 'Enviado DIAN', cls: 'bg-green-100 text-green-700' }
    if (document?.paid)       return { label: 'Pagado',       cls: 'bg-blue-100 text-blue-700' }
    return                           { label: 'Borrador',     cls: 'bg-slate-100 text-slate-600' }
  })

  const canEdit = $derived(!document?.electronic && !document?.annulled)

  // Factura de Venta (tipo 1/01) → puede tener NC y ND
  const isInvoice = $derived(
    String(document?.type_document_id) === '1' ||
    String(document?.type_document_operation_id) === '1' ||
    String(document?.type_document_operation_id) === '01'
  )

  const canHaveCreditNote = $derived(!document?.annulled && isInvoice)
  const canHaveDebitNote  = $derived(!document?.annulled && isInvoice)

  const creditNotes = $derived(document?.credit_notes ?? [])

  // ── Acciones básicas ─────────────────────────────────────────────────────
  let deleting = $state(false)
  let confirmDelete = $state(false)

  function destroy() {
    deleting = true
    router.delete(`/invoices/${document.id}`, {
      onFinish: () => { deleting = false },
    })
  }

  // Nombre del tercero
  const thirdName = $derived(() => {
    const t = document?.third_party
    if (!t) return 'Consumidor final'
    return [t.name, t.surname].filter(Boolean).join(' ')
  })

  // ── Modal de Nota Débito ─────────────────────────────────────────────────
  let showNdModal  = $state(false)
  let ndSubmitting = $state(false)
  let ndError      = $state('')
  let ndConcept    = $state('1')
  let ndNote       = $state('')

  const emptyNdLine = () => ({ description: '', amount: 1, sale_price: 0, discount: 0, tax_percent: 0 })
  let ndLines = $state([emptyNdLine()])

  const ndLineTotal = (l) => {
    const base = Number(l.amount) * Number(l.sale_price) * (1 - Number(l.discount) / 100)
    return base * (1 + Number(l.tax_percent) / 100)
  }
  const ndTotal = $derived(ndLines.reduce((s, l) => s + ndLineTotal(l), 0))

  function openNdModal() {
    ndConcept = '1'
    ndNote    = ''
    ndError   = ''
    ndLines   = [emptyNdLine()]
    showNdModal = true
  }

  function closeNdModal() { showNdModal = false; ndError = '' }

  function submitDebitNote() {
    if (!ndNote.trim()) { ndError = 'La observación es obligatoria.'; return }
    if (ndLines.some(l => !l.description.trim())) { ndError = 'Todas las líneas deben tener descripción.'; return }

    ndSubmitting = true
    ndError = ''

    router.post(`/invoices/${document.id}/debit-note`, {
      correction_concept: parseInt(ndConcept),
      note:               ndNote.trim(),
      lines: ndLines.map(l => ({
        description: l.description,
        amount:      Number(l.amount),
        sale_price:  Number(l.sale_price),
        discount:    Number(l.discount) || 0,
        taxes:       Number(l.tax_percent) > 0 ? [{ percent: Number(l.tax_percent) }] : [],
      })),
    }, {
      onError:  (errors) => { ndError = Object.values(errors).flat().join(' '); ndSubmitting = false },
      onFinish: () => { ndSubmitting = false },
    })
  }

  // ── Modal de Nota Crédito ────────────────────────────────────────────────
  let showNcModal    = $state(false)
  let ncSubmitting   = $state(false)
  let ncError        = $state('')

  // Concepto de corrección seleccionado
  let ncConcept = $state('1')
  let ncNote    = $state('')

  // Selección de líneas: { [line.id]: qty }
  let ncLineQtys = $state({})

  // Inicializar al abrir el modal
  function openNcModal() {
    ncConcept  = '1'
    ncNote     = ''
    ncError    = ''
    // Por defecto: todas las líneas con su cantidad original
    const init = {}
    for (const l of lines) {
      init[l.id] = Number(l.amount)
    }
    ncLineQtys = init
    showNcModal = true
  }

  function closeNcModal() {
    showNcModal = false
    ncError = ''
  }

  // Total de la NC según selección actual
  const ncSelectedTotal = $derived(() => {
    return lines.reduce((sum, l) => {
      const qty = Number(ncLineQtys[l.id] ?? 0)
      if (qty <= 0) return sum
      const base = qty * Number(l.sale_price)
      const disc = base * (Number(l.discount) / 100)
      const net  = base - disc
      const tax  = net * ((l.taxes?.[0]?.percent ?? 0) / 100)
      return sum + net + tax
    }, 0)
  })

  function submitCreditNote() {
    if (!ncNote.trim()) {
      ncError = 'La observación es obligatoria.'
      return
    }

    // Construir selected_lines solo con qty > 0
    const selectedLines = lines
      .filter(l => Number(ncLineQtys[l.id] ?? 0) > 0)
      .map(l => ({ line_id: l.id, qty: Number(ncLineQtys[l.id]) }))

    if (selectedLines.length === 0) {
      ncError = 'Seleccione al menos un ítem con cantidad mayor a 0.'
      return
    }

    ncSubmitting = true
    ncError = ''

    router.post(`/invoices/${document.id}/credit-note`, {
      correction_concept: parseInt(ncConcept),
      note:               ncNote.trim(),
      selected_lines:     selectedLines,
    }, {
      onError: (errors) => {
        ncError = Object.values(errors).flat().join(' ')
        ncSubmitting = false
      },
      onFinish: () => { ncSubmitting = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-5xl mx-auto space-y-5">

    <!-- ── Cabecera / acciones ──────────────────────────────────────────── -->
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex items-center gap-3">
        <a use:inertia href="/invoices" class="text-slate-400 hover:text-slate-600 transition">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-xl font-bold text-slate-800">
              {document?.prefix ?? ''}{document?.number ?? 'Documento'}
            </h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {statusBadge().cls}">
              {statusBadge().label}
            </span>
            {#if document?.reference}
              <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                NC de: {document.reference.internal_code ?? document.reference.number}
              </span>
            {/if}
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {typeMap[String(document?.type_document_id)] ?? 'Documento'}
            · {document?.issue_date ?? ''}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap">
        {#if canEdit}
          <a use:inertia href="/invoices/{document.id}/edit"
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition">
            <i class="mdi mdi-pencil-outline text-base"></i>
            Editar
          </a>
          <button onclick={() => confirmDelete = true} disabled={deleting}
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition disabled:opacity-60 cursor-pointer">
            {#if deleting}
              <i class="mdi mdi-loading mdi-spin text-base"></i>
            {:else}
              <i class="mdi mdi-delete-outline text-base"></i>
            {/if}
            Eliminar
          </button>
        {/if}

        {#if canHaveCreditNote}
          <button onclick={openNcModal}
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-orange-300 text-orange-700 rounded-lg hover:bg-orange-50 transition cursor-pointer font-medium">
            <i class="mdi mdi-file-undo-outline text-base"></i>
            Nota Crédito
          </button>
        {/if}

        {#if canHaveDebitNote}
          <button onclick={openNdModal}
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-indigo-300 text-indigo-700 rounded-lg hover:bg-indigo-50 transition cursor-pointer font-medium">
            <i class="mdi mdi-file-plus-outline text-base"></i>
            Nota Débito
          </button>
        {/if}

        {#if document?.electronic}
          <span class="flex items-center gap-1.5 px-3 py-2 text-sm bg-green-50 border border-green-200 text-green-700 rounded-lg font-medium">
            <i class="mdi mdi-check-circle-outline text-base"></i>
            Enviado DIAN
          </span>
        {/if}
      </div>
    </div>

    <!-- ── Notas crédito relacionadas ────────────────────────────────────── -->
    {#if creditNotes.length > 0}
      <div class="bg-orange-50 border border-orange-200 rounded-xl px-5 py-3">
        <p class="text-sm font-semibold text-orange-700 mb-2 flex items-center gap-2">
          <i class="mdi mdi-file-undo-outline"></i>
          Notas crédito emitidas sobre este documento
        </p>
        <div class="space-y-1">
          {#each creditNotes as nc}
            <div class="flex items-center justify-between text-sm">
              <a use:inertia href="/invoices/{nc.id}"
                class="text-orange-700 hover:underline font-medium">
                {nc.internal_code ?? nc.number}
              </a>
              <span class="text-orange-600 tabular-nums">${fmt(nc.total)}</span>
            </div>
          {/each}
        </div>
      </div>
    {/if}

    <!-- ── Información general ──────────────────────────────────────────── -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

      <!-- Empresa -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Emisor</h3>
        <div class="space-y-1.5">
          <p class="font-semibold text-slate-800 text-sm">
            {document?.company?.name ?? '—'}
          </p>
          {#if document?.company?.identification_number}
            <p class="text-sm text-slate-500">
              NIT {document.company.identification_number}{document.company.dv ? `-${document.company.dv}` : ''}
            </p>
          {/if}
          {#if document?.company?.address}
            <p class="text-sm text-slate-500">{document.company.address}</p>
          {/if}
          {#if document?.company?.phone}
            <p class="text-sm text-slate-500">{document.company.phone}</p>
          {/if}
          {#if document?.resolution}
            <div class="mt-2 pt-2 border-t border-slate-100">
              <p class="text-xs text-slate-400">Resolución DIAN</p>
              <p class="text-sm text-slate-600 font-medium">
                {document.resolution.prefix ?? ''} · {document.resolution.resolution_number ?? ''}
              </p>
            </div>
          {/if}
        </div>
      </div>

      <!-- Tercero -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Receptor</h3>
        <div class="space-y-1.5">
          {#if document?.third_party}
            <p class="font-semibold text-slate-800 text-sm">{thirdName()}</p>
            <p class="text-sm text-slate-500">
              NIT/CC {document.third_party.identification_number}{document.third_party.dv ? `-${document.third_party.dv}` : ''}
            </p>
            {#if document.third_party.email}
              <p class="text-sm text-slate-500">{document.third_party.email}</p>
            {/if}
            {#if document.third_party.address}
              <p class="text-sm text-slate-500">{document.third_party.address}</p>
            {/if}
          {:else}
            <p class="text-sm text-slate-500 italic">Consumidor final</p>
          {/if}
        </div>
      </div>
    </div>

    <!-- ── Líneas del documento ──────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-format-list-bulleted text-primary"></i>
          Detalle del documento
        </h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs">#</th>
              <th class="text-left px-4 py-2.5 font-medium text-slate-500 text-xs">Descripción</th>
              <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs w-20">Cant.</th>
              <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs w-28">P. Venta</th>
              <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs w-20">Dto %</th>
              <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs w-24">IVA</th>
              <th class="text-right px-4 py-2.5 font-medium text-slate-500 text-xs w-28">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each lines as line, idx}
              <tr class="hover:bg-slate-50/50">
                <td class="px-4 py-3 text-slate-400 text-xs">{idx + 1}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-700 text-sm">
                    {line.description || line.item?.name || '—'}
                  </p>
                  {#if line.item?.internal_code}
                    <p class="text-xs text-slate-400">{line.item.internal_code}</p>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right text-slate-600 tabular-nums">
                  {Number(line.amount).toLocaleString('es-CO')}
                </td>
                <td class="px-4 py-3 text-right text-slate-600 tabular-nums">
                  ${fmt(line.sale_price)}
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                  {#if Number(line.discount) > 0}
                    <span class="text-orange-600">{Number(line.discount).toFixed(1)}%</span>
                  {:else}
                    <span class="text-slate-300">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right tabular-nums text-slate-500">
                  {#if lineTaxAmt(line) > 0}
                    ${fmt(lineTaxAmt(line))}
                  {:else}
                    <span class="text-slate-300">—</span>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-800 tabular-nums">
                  ${fmt(lineTotal(line))}
                </td>
              </tr>
            {/each}
            {#if lines.length === 0}
              <tr>
                <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">
                  Sin líneas de detalle
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>

      <!-- Totales -->
      <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
        <div class="flex justify-end">
          <div class="space-y-1.5 min-w-64">
            <div class="flex justify-between text-sm text-slate-600">
              <span>Subtotal bruto</span>
              <span class="tabular-nums font-medium">${fmt(subtotal + totalDiscounts)}</span>
            </div>
            {#if totalDiscounts > 0}
              <div class="flex justify-between text-sm text-slate-500">
                <span>Descuentos</span>
                <span class="tabular-nums text-red-600">-${fmt(totalDiscounts)}</span>
              </div>
              <div class="flex justify-between text-sm text-slate-600">
                <span>Base gravable</span>
                <span class="tabular-nums font-medium">${fmt(subtotal)}</span>
              </div>
            {/if}
            {#if totalTax > 0}
              <div class="flex justify-between text-sm text-slate-500">
                <span>IVA</span>
                <span class="tabular-nums">${fmt(totalTax)}</span>
              </div>
            {/if}
            <div class="flex justify-between font-bold text-slate-800 text-base border-t border-slate-300 pt-2 mt-1">
              <span>Total a pagar</span>
              <span class="tabular-nums">${fmt(total)}</span>
            </div>
            {#if Number(document?.balance) > 0}
              <div class="flex justify-between text-sm text-red-600 font-medium">
                <span>Saldo pendiente</span>
                <span class="tabular-nums">${fmt(document.balance)}</span>
              </div>
            {/if}
          </div>
        </div>
      </div>
    </div>

    <!-- ── Información adicional ─────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 text-xs text-slate-400 flex flex-wrap gap-x-6 gap-y-1.5 pb-6">
      {#if document?.cufe}
        <span><span class="font-semibold text-slate-500">CUFE:</span> {document.cufe}</span>
      {/if}
      {#if document?.created_at}
        <span><span class="font-semibold text-slate-500">Creado:</span> {document.created_at}</span>
      {/if}
      {#if document?.user}
        <span><span class="font-semibold text-slate-500">Usuario:</span> {document.user.name}</span>
      {/if}
      {#if document?.note}
        <span class="w-full"><span class="font-semibold text-slate-500">Nota:</span> {document.note}</span>
      {/if}
    </div>

  </div>
</AppLayout>

<ConfirmModal
  bind:open={confirmDelete}
  title="Eliminar documento"
  message="¿Eliminar este documento? Esta acción no se puede deshacer."
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={destroy}
/>

<!-- ── Modal Nota Débito ─────────────────────────────────────────────────── -->
{#if showNdModal}
  <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    onclick={(e) => { if (e.target === e.currentTarget) closeNdModal() }}>

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col z-50">

      <!-- Cabecera -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center">
            <i class="mdi mdi-file-plus-outline text-indigo-600 text-lg"></i>
          </div>
          <div>
            <h2 class="font-bold text-slate-800 text-base">Emitir Nota Débito</h2>
            <p class="text-xs text-slate-500">
              Cargos adicionales sobre: {document?.prefix ?? ''}{document?.number}
            </p>
          </div>
        </div>
        <button onclick={closeNdModal}
          class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <!-- Cuerpo scrollable -->
      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

        <!-- Concepto -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">
            Concepto DIAN <span class="text-red-500">*</span>
          </label>
          <div class="space-y-2">
            {#each Object.entries(debitNoteConcepts) as [key, label]}
              <label class="flex items-start gap-3 cursor-pointer group">
                <input type="radio" bind:group={ndConcept} value={key}
                  class="mt-0.5 text-indigo-500 focus:ring-indigo-400 cursor-pointer" />
                <span class="text-sm text-slate-700 group-hover:text-slate-900 leading-tight">
                  <span class="font-medium text-indigo-700">{key}.</span> {label}
                </span>
              </label>
            {/each}
          </div>
        </div>

        <!-- Líneas de cargo -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-semibold text-slate-700">
              Líneas de cargo adicional <span class="text-red-500">*</span>
            </label>
            <button
              onclick={() => { ndLines = [...ndLines, emptyNdLine()] }}
              class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium cursor-pointer">
              <i class="mdi mdi-plus-circle-outline"></i>
              Agregar línea
            </button>
          </div>

          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="text-left px-3 py-2.5 text-xs font-medium text-slate-500">Descripción</th>
                  <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 w-20">Cant.</th>
                  <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 w-28">P. Unit.</th>
                  <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 w-20">Dto %</th>
                  <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 w-20">IVA %</th>
                  <th class="text-right px-3 py-2.5 text-xs font-medium text-slate-500 w-28">Total</th>
                  <th class="w-8"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each ndLines as line, idx}
                  <tr class="hover:bg-indigo-50/20 transition">
                    <td class="px-3 py-2">
                      <input
                        type="text"
                        bind:value={line.description}
                        placeholder="Descripción del cargo..."
                        class="w-full border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <input
                        type="number" min="0.001" step="1"
                        bind:value={line.amount}
                        class="w-full text-right border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300 tabular-nums"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <input
                        type="number" min="0" step="1000"
                        bind:value={line.sale_price}
                        class="w-full text-right border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300 tabular-nums"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <input
                        type="number" min="0" max="100" step="1"
                        bind:value={line.discount}
                        class="w-full text-right border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300 tabular-nums"
                      />
                    </td>
                    <td class="px-3 py-2">
                      <input
                        type="number" min="0" max="100" step="1"
                        bind:value={line.tax_percent}
                        class="w-full text-right border border-slate-200 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300 tabular-nums"
                      />
                    </td>
                    <td class="px-3 py-2 text-right font-semibold text-slate-700 tabular-nums text-sm">
                      ${fmt(ndLineTotal(line))}
                    </td>
                    <td class="px-2 py-2">
                      {#if ndLines.length > 1}
                        <button
                          onclick={() => { ndLines = ndLines.filter((_, i) => i !== idx) }}
                          class="text-slate-300 hover:text-red-500 transition cursor-pointer">
                          <i class="mdi mdi-close text-base"></i>
                        </button>
                      {/if}
                    </td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-indigo-50 border-t border-indigo-200">
                <tr>
                  <td colspan="5" class="px-3 py-2.5 text-sm font-semibold text-indigo-700">
                    Total cargos adicionales
                  </td>
                  <td class="px-3 py-2.5 text-right font-bold text-indigo-700 tabular-nums">
                    ${fmt(ndTotal)}
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Observación -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">
            Observación <span class="text-red-500">*</span>
          </label>
          <textarea
            bind:value={ndNote}
            rows="3"
            placeholder="Describe el motivo del cargo adicional..."
            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-300"
          ></textarea>
        </div>

        <!-- Error -->
        {#if ndError}
          <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
            <i class="mdi mdi-alert-circle-outline flex-shrink-0"></i>
            {ndError}
          </div>
        {/if}

      </div>

      <!-- Pie del modal -->
      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
        <button onclick={closeNdModal} disabled={ndSubmitting}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
          Cancelar
        </button>
        <button onclick={submitDebitNote} disabled={ndSubmitting}
          class="flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 transition cursor-pointer disabled:opacity-60">
          {#if ndSubmitting}
            <i class="mdi mdi-loading mdi-spin text-base"></i>
            Emitiendo...
          {:else}
            <i class="mdi mdi-file-check-outline text-base"></i>
            Emitir Nota Débito
          {/if}
        </button>
      </div>

    </div>
  </div>
{/if}

<!-- ── Modal Nota Crédito ────────────────────────────────────────────────── -->
{#if showNcModal}
  <!-- Overlay -->
  <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    onclick={(e) => { if (e.target === e.currentTarget) closeNcModal() }}>

    <!-- Panel modal -->
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-50">

      <!-- Cabecera -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center">
            <i class="mdi mdi-file-undo-outline text-orange-600 text-lg"></i>
          </div>
          <div>
            <h2 class="font-bold text-slate-800 text-base">Emitir Nota Crédito</h2>
            <p class="text-xs text-slate-500">
              Referencia: {document?.prefix ?? ''}{document?.number}
            </p>
          </div>
        </div>
        <button onclick={closeNcModal}
          class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <!-- Cuerpo scrollable -->
      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

        <!-- Concepto de corrección -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-2">
            Concepto de corrección <span class="text-red-500">*</span>
          </label>
          <div class="space-y-2">
            {#each Object.entries(correctionConcepts) as [key, label]}
              <label class="flex items-start gap-3 cursor-pointer group">
                <input type="radio" bind:group={ncConcept} value={key}
                  class="mt-0.5 text-orange-500 focus:ring-orange-400 cursor-pointer" />
                <span class="text-sm text-slate-700 group-hover:text-slate-900 leading-tight">
                  <span class="font-medium text-orange-700">{key}.</span> {label}
                </span>
              </label>
            {/each}
          </div>
        </div>

        <!-- Líneas a devolver -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-semibold text-slate-700">
              Ítems a devolver
            </label>
            <span class="text-xs text-slate-400">Ajusta la cantidad a devolver por ítem</span>
          </div>

          <div class="border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                  <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Ítem</th>
                  <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-24">Facturado</th>
                  <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Devolver</th>
                  <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Valor</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each lines as line}
                  {@const qty = Number(ncLineQtys[line.id] ?? 0)}
                  {@const base = qty * Number(line.sale_price)}
                  {@const net  = base * (1 - Number(line.discount) / 100)}
                  {@const tax  = net * ((line.taxes?.[0]?.percent ?? 0) / 100)}
                  <tr class="hover:bg-orange-50/30 transition">
                    <td class="px-4 py-3">
                      <p class="font-medium text-slate-700 text-sm leading-tight">
                        {line.description || line.item?.name || '—'}
                      </p>
                      <p class="text-xs text-slate-400">${fmt(line.sale_price)} c/u</p>
                    </td>
                    <td class="px-4 py-3 text-right text-slate-500 tabular-nums text-xs">
                      {Number(line.amount).toLocaleString('es-CO')}
                    </td>
                    <td class="px-4 py-3 text-right">
                      <input
                        type="number"
                        min="0"
                        max={line.amount}
                        step="1"
                        value={ncLineQtys[line.id] ?? line.amount}
                        oninput={(e) => { ncLineQtys = { ...ncLineQtys, [line.id]: e.target.value } }}
                        class="w-20 text-right border border-slate-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-300 tabular-nums"
                      />
                    </td>
                    <td class="px-4 py-3 text-right tabular-nums text-slate-700 text-sm font-medium">
                      {qty > 0 ? `$${fmt(net + tax)}` : '—'}
                    </td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-orange-50 border-t border-orange-200">
                <tr>
                  <td colspan="3" class="px-4 py-2.5 text-sm font-semibold text-orange-700">
                    Total a devolver
                  </td>
                  <td class="px-4 py-2.5 text-right font-bold text-orange-700 tabular-nums">
                    ${fmt(ncSelectedTotal())}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Observación -->
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">
            Observación <span class="text-red-500">*</span>
          </label>
          <textarea
            bind:value={ncNote}
            rows="3"
            placeholder="Describe el motivo de la devolución o corrección..."
            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-300"
          ></textarea>
        </div>

        <!-- Error -->
        {#if ncError}
          <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
            <i class="mdi mdi-alert-circle-outline flex-shrink-0"></i>
            {ncError}
          </div>
        {/if}

      </div>

      <!-- Pie del modal -->
      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
        <button onclick={closeNcModal} disabled={ncSubmitting}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
          Cancelar
        </button>
        <button onclick={submitCreditNote} disabled={ncSubmitting}
          class="flex items-center gap-2 px-5 py-2 bg-orange-600 text-white text-sm font-semibold rounded-xl hover:bg-orange-700 transition cursor-pointer disabled:opacity-60">
          {#if ncSubmitting}
            <i class="mdi mdi-loading mdi-spin text-base"></i>
            Emitiendo...
          {:else}
            <i class="mdi mdi-file-check-outline text-base"></i>
            Emitir Nota Crédito
          {/if}
        </button>
      </div>

    </div>
  </div>
{/if}
