<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { item } = $props()

  function fmt(n) {
    if (n === null || n === undefined) return '—'
    return Number(n).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('es-CO', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  }
</script>

<AppLayout title="Documento del buzón tributario">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">{item.business_name_provider ?? item.subject ?? 'Documento sin nombre'}</h1>
      <p class="text-sm text-slate-500 mt-0.5">Buzón tributario</p>
    </div>
    <a href="/tax-mailbox" class="text-sm text-slate-500 hover:text-slate-700">
      <i class="mdi mdi-arrow-left mr-1"></i> Volver al buzón
    </a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <h2 class="text-sm font-semibold text-slate-600 mb-4">Datos del emisor</h2>
      <dl class="space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Razón social</dt><dd class="text-slate-800 font-medium">{item.business_name_provider ?? '—'}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">NIT</dt><dd class="text-slate-800 font-medium">{item.identification_number_provider ?? '—'}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">CUFE</dt><dd class="text-slate-800 font-mono text-xs break-all max-w-[60%] text-right">{item.cufe ?? '—'}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="text-slate-800 font-medium">{fmtDate(item.date)}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Total</dt><dd class="text-slate-800 font-medium">{fmt(item.tax_inclusive_amount)}</dd></div>
      </dl>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-5">
      <h2 class="text-sm font-semibold text-slate-600 mb-4">Estado</h2>
      <dl class="space-y-3 text-sm">
        <div class="flex justify-between items-center">
          <dt class="text-slate-500">Procesamiento</dt>
          <dd>
            {#if item.document_id}
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Procesado</span>
            {:else}
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pendiente</span>
            {/if}
          </dd>
        </div>
        {#if item.document}
          <div class="flex justify-between"><dt class="text-slate-500">Documento generado</dt><dd class="text-blue-700 font-medium">{item.document.internal_code}</dd></div>
        {:else}
          <p class="text-xs text-slate-400 mt-2">
            El procesamiento automático a documento de compra estará disponible cuando se active la integración con la DIAN.
          </p>
        {/if}
        {#if item.xml_file_name}
          <div class="pt-2">
            <a href="/tax-mailbox/{item.id}/download"
               class="inline-flex items-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
              <i class="mdi mdi-download"></i> Descargar {item.xml_file_name}
            </a>
          </div>
        {/if}
      </dl>
    </div>
  </div>
</AppLayout>
