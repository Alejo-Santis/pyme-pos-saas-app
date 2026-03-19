<script>
  /**
   * Modal de confirmación reutilizable — reemplaza confirm() nativo.
   *
   * Uso:
   *   <ConfirmModal bind:open={show} message="¿Eliminar?" onConfirm={doIt} />
   *
   * Props:
   *   open          — bindable, controla visibilidad
   *   title         — título del modal (opcional)
   *   message       — cuerpo del mensaje
   *   confirmLabel  — texto botón confirmar (default: "Confirmar")
   *   cancelLabel   — texto botón cancelar (default: "Cancelar")
   *   danger        — si true, botón en rojo; si false, en blue (default: true)
   *   onConfirm     — callback cuando el usuario acepta
   */
  let {
    open        = $bindable(false),
    title       = '¿Confirmar acción?',
    message     = '',
    confirmLabel = 'Confirmar',
    cancelLabel  = 'Cancelar',
    danger      = true,
    onConfirm   = () => {},
  } = $props()

  function accept() {
    open = false
    onConfirm()
  }

  function cancel() {
    open = false
  }
</script>

{#if open}
  <!-- Overlay -->
  <div
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    onclick={(e) => { if (e.target === e.currentTarget) cancel() }}
    role="dialog"
    aria-modal="true"
  >
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">

      <!-- Cabecera -->
      <div class="px-6 pt-5 pb-3 flex items-start gap-3">
        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0
                    {danger ? 'bg-red-100' : 'bg-blue-100'}">
          <i class="mdi {danger ? 'mdi-alert-outline text-red-600' : 'mdi-help-circle-outline text-blue-600'} text-lg"></i>
        </div>
        <div class="pt-0.5">
          <h2 class="font-semibold text-slate-800 text-base leading-tight">{title}</h2>
          {#if message}
            <p class="text-sm text-slate-500 mt-1 leading-relaxed">{message}</p>
          {/if}
        </div>
      </div>

      <!-- Acciones -->
      <div class="px-6 pb-5 flex justify-end gap-2 pt-2">
        <button
          onclick={cancel}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer font-medium"
        >
          {cancelLabel}
        </button>
        <button
          onclick={accept}
          class="px-4 py-2 text-sm rounded-xl font-semibold text-white transition cursor-pointer
                 {danger
                   ? 'bg-red-600 hover:bg-red-700'
                   : 'bg-blue-600 hover:bg-blue-700'}"
        >
          {confirmLabel}
        </button>
      </div>

    </div>
  </div>
{/if}
