<script>
  /**
   * Modal reutilizable para importación masiva via Excel/CSV.
   *
   * Uso:
   *   <ImportModal
   *     bind:open={showImport}
   *     uploadUrl="/third-parties/import"
   *     templateUrl="/third-parties/import/template"
   *     title="Importar Terceros"
   *     columns={['tipo_documento','numero_documento', ...]}
   *   />
   */
  import { useForm } from '@inertiajs/svelte'
  import { page } from '@inertiajs/svelte'

  let {
    open       = $bindable(false),
    uploadUrl  = '',
    templateUrl= '',
    title      = 'Importar datos',
    columns    = [],
  } = $props()

  const form = useForm({ file: null })

  let fileInput
  let result = $state(null)   // { imported, errors }

  function close() {
    open = false
    result = null
    form.reset()
    if (fileInput) fileInput.value = ''
  }

  function submit() {
    if (!form.file) return
    result = null
    form.post(uploadUrl, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        const flash = $page.props.flash ?? {}
        result = {
          imported: flash.import_imported ?? 0,
          errors:   flash.import_errors   ?? [],
        }
        form.reset()
        if (fileInput) fileInput.value = ''
      },
      onError: () => {
        result = { imported: 0, errors: ['Error al procesar el archivo. Verifica el formato.'] }
      },
    })
  }

  function onFileChange(e) {
    form.file = e.target.files[0] ?? null
  }
</script>

{#if open}
  <!-- Overlay -->
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
    onclick={(e) => e.target === e.currentTarget && close()}
  >
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <div class="flex items-center gap-2">
          <i class="mdi mdi-file-import text-blue-600 text-xl"></i>
          <h2 class="text-base font-semibold text-slate-800">{title}</h2>
        </div>
        <button onclick={close} class="text-slate-400 hover:text-slate-600 cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <div class="px-6 py-5 space-y-4">

        <!-- Instrucciones + descarga de plantilla -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800 space-y-2">
          <p class="font-medium flex items-center gap-1">
            <i class="mdi mdi-information-outline"></i>
            Instrucciones
          </p>
          <ol class="list-decimal list-inside space-y-1 text-xs text-blue-700">
            <li>Descarga la plantilla Excel con el formato requerido.</li>
            <li>Completa los datos sin modificar las columnas de la primera fila.</li>
            <li>Guarda el archivo y súbelo aquí.</li>
          </ol>
          {#if templateUrl}
            <a
              href={templateUrl}
              target="_blank"
              class="inline-flex items-center gap-1.5 mt-1 px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition"
            >
              <i class="mdi mdi-microsoft-excel text-sm"></i>
              Descargar plantilla
            </a>
          {/if}
        </div>

        <!-- Columnas requeridas -->
        {#if columns.length > 0}
          <div>
            <p class="text-xs font-medium text-slate-500 mb-1.5">Columnas de la plantilla:</p>
            <div class="flex flex-wrap gap-1.5">
              {#each columns as col}
                <span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs font-mono">{col}</span>
              {/each}
            </div>
          </div>
        {/if}

        <!-- Upload -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1">Archivo Excel (.xlsx) o CSV</label>
          <input
            bind:this={fileInput}
            type="file"
            accept=".xlsx,.xls,.csv"
            onchange={onFileChange}
            class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
          />
          {#if form.errors.file}
            <p class="text-xs text-red-500 mt-1">{form.errors.file}</p>
          {/if}
        </div>

        <!-- Resultado de importación -->
        {#if result}
          <div class="rounded-xl border {result.errors.length === 0 ? 'border-green-200 bg-green-50' : 'border-amber-200 bg-amber-50'} p-4 space-y-2">
            <p class="text-sm font-semibold {result.errors.length === 0 ? 'text-green-700' : 'text-amber-700'}">
              <i class="mdi {result.errors.length === 0 ? 'mdi-check-circle-outline' : 'mdi-alert-outline'} mr-1"></i>
              {result.imported} registro(s) importado(s)
              {#if result.errors.length > 0}· {result.errors.length} con error{/if}
            </p>
            {#if result.errors.length > 0}
              <ul class="text-xs text-amber-800 space-y-0.5 max-h-36 overflow-y-auto">
                {#each result.errors as err}
                  <li class="flex items-start gap-1">
                    <i class="mdi mdi-circle-small mt-0.5 shrink-0"></i>{err}
                  </li>
                {/each}
              </ul>
            {/if}
          </div>
        {/if}

      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-slate-100">
        <button onclick={close}
          class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition cursor-pointer">
          {result ? 'Cerrar' : 'Cancelar'}
        </button>
        {#if !result}
          <button
            onclick={submit}
            disabled={!form.file || form.processing}
            class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer flex items-center gap-2"
          >
            {#if form.processing}
              <i class="mdi mdi-loading animate-spin"></i> Procesando…
            {:else}
              <i class="mdi mdi-upload"></i> Importar
            {/if}
          </button>
        {/if}
      </div>

    </div>
  </div>
{/if}
