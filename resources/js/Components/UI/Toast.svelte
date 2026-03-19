<script>
  /**
   * Componente de renderizado de toasts.
   * - Escucha flash messages de Inertia automáticamente.
   * - Se coloca una sola vez en AppLayout.svelte.
   * - Para uso programático importar { toast } desde '@/stores/toast.svelte.js'.
   */
  import { page } from '@inertiajs/svelte'
  import { toast, dismiss, getToasts } from '@/stores/toast.svelte.js'

  // Clave del último flash procesado — evita duplicados cuando $effect
  // re-corre múltiples veces para la misma navegación Inertia.
  // NO usar $state — no debe crear dependencia reactiva dentro del effect.
  let _lastKey = ''

  $effect(() => {
    const flash  = $page.props.flash  ?? {}
    const errors = $page.props.errors ?? {}

    // Si no hay nada que mostrar, salir
    const hasFlash = Object.values(flash).some(Boolean)
    const hasError = Object.values(errors).length > 0
    if (!hasFlash && !hasError) return

    // Clave única: URL + contenido del flash — cambia en cada navegación real
    const key = $page.url + '||' + JSON.stringify(flash) + '||' + JSON.stringify(errors)
    if (key === _lastKey) return
    _lastKey = key

    if (flash.success) toast.success(flash.success)
    if (flash.error)   toast.error(flash.error)
    if (flash.warning) toast.warning(flash.warning)
    if (flash.info)    toast.info(flash.info)

    // Primer error de validación
    const firstError = Object.values(errors)[0]
    if (firstError) toast.error(typeof firstError === 'string' ? firstError : firstError[0])
  })

  // Paleta visual por tipo
  const styles = {
    success : { bar: 'bg-emerald-500', bg: 'bg-emerald-50 border-emerald-200', text: 'text-emerald-800', icon: 'mdi-check-circle-outline'      },
    error   : { bar: 'bg-red-500',     bg: 'bg-red-50 border-red-200',         text: 'text-red-800',     icon: 'mdi-alert-circle-outline'        },
    warning : { bar: 'bg-amber-500',   bg: 'bg-amber-50 border-amber-200',     text: 'text-amber-800',   icon: 'mdi-alert-outline'               },
    info    : { bar: 'bg-blue-500',    bg: 'bg-blue-50 border-blue-200',       text: 'text-blue-800',    icon: 'mdi-information-outline'         },
  }

  const toasts = $derived(getToasts())
</script>

<!-- Contenedor fijo esquina inferior derecha -->
<div class="fixed bottom-5 right-5 z-[9999] flex flex-col-reverse gap-2 w-80 pointer-events-none">
  {#each toasts as t (t.id)}
    {@const s = styles[t.type] ?? styles.info}
    <div
      class="pointer-events-auto flex items-stretch rounded-lg border shadow-lg overflow-hidden
             transition-all duration-300 ease-out
             {t.visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'}
             {s.bg}"
      role="alert"
    >
      <!-- Barra lateral de color -->
      <div class="w-1 flex-shrink-0 {s.bar}"></div>

      <!-- Contenido -->
      <div class="flex items-start gap-2 flex-1 px-3 py-3">
        <i class="mdi {s.icon} text-xl flex-shrink-0 mt-0.5 {s.text}"></i>
        <p class="flex-1 text-sm font-medium leading-snug {s.text}">{t.message}</p>
      </div>

      <!-- Botón cerrar -->
      <button
        onclick={() => dismiss(t.id)}
        class="px-2 flex items-center opacity-40 hover:opacity-80 transition-opacity {s.text}"
        aria-label="Cerrar notificación"
      >
        <i class="mdi mdi-close text-sm"></i>
      </button>
    </div>
  {/each}
</div>
