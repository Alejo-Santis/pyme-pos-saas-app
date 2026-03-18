<script>
  /**
   * Botones de exportación reutilizables — Excel y PDF.
   * Uso:
   *   <ExportButtons baseUrl="/reports/sales/export" params={{ date_from, date_to }} />
   */
  let { baseUrl, params = {} } = $props();

  function buildUrl(format) {
    const qs = new URLSearchParams({ ...params, format }).toString();
    return `${baseUrl}?${qs}`;
  }
</script>

<div class="flex items-center gap-1">
  <a
    href={buildUrl('excel')}
    target="_blank"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition"
    title="Exportar a Excel"
  >
    <i class="mdi mdi-microsoft-excel text-sm"></i>
    Excel
  </a>
  <a
    href={buildUrl('pdf')}
    target="_blank"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition"
    title="Exportar a PDF"
  >
    <i class="mdi mdi-file-pdf-box text-sm"></i>
    PDF
  </a>
</div>
