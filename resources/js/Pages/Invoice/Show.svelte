<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    document      = null,
    documentTypes = [],
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
    if (document?.annulled)   return { label: 'Anulado',    cls: 'bg-red-100 text-red-700' }
    if (document?.electronic) return { label: 'Enviado DIAN', cls: 'bg-green-100 text-green-700' }
    if (document?.paid)       return { label: 'Pagado',     cls: 'bg-blue-100 text-blue-700' }
    return                           { label: 'Borrador',   cls: 'bg-slate-100 text-slate-600' }
  })

  const canEdit = $derived(!document?.electronic && !document?.annulled)

  // ── Acciones ─────────────────────────────────────────────────────────────
  let deleting = $state(false)

  function destroy() {
    if (!confirm('¿Eliminar este documento? Esta acción no se puede deshacer.')) return
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
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            {typeMap[String(document?.type_document_id)] ?? 'Documento'}
            · {document?.issue_date ?? ''}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        {#if canEdit}
          <a use:inertia href="/invoices/{document.id}/edit"
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-slate-200 rounded-lg text-slate-600 hover:bg-slate-50 transition">
            <i class="mdi mdi-pencil-outline text-base"></i>
            Editar
          </a>
          <button onclick={destroy} disabled={deleting}
            class="flex items-center gap-1.5 px-3 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition disabled:opacity-60 cursor-pointer">
            {#if deleting}
              <i class="mdi mdi-loading mdi-spin text-base"></i>
            {:else}
              <i class="mdi mdi-delete-outline text-base"></i>
            {/if}
            Eliminar
          </button>
        {/if}
        {#if !document?.electronic && !document?.annulled}
          <button
            class="flex items-center gap-1.5 px-4 py-2 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition cursor-pointer opacity-60"
            title="Integración DIAN próximamente">
            <i class="mdi mdi-send-outline text-base"></i>
            Enviar a DIAN
          </button>
        {/if}
      </div>
    </div>

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
    </div>

  </div>
</AppLayout>
