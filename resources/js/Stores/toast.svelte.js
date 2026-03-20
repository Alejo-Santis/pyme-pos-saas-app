/**
 * Store global de toasts — Svelte 5 Runes.
 * Importar { toast } desde cualquier página para mostrar notificaciones.
 *
 * Uso:
 *   import { toast } from '@/Stores/toast.svelte.js'
 *   toast.success('Guardado correctamente')
 *   toast.error('Ocurrió un error')
 *   toast.warning('Revisa los campos')
 *   toast.info('Información adicional')
 */

let _toasts = $state([])
let _nextId  = 0

function add(type, message, duration) {
  const id = ++_nextId
  _toasts = [..._toasts, { id, type, message, visible: true }]

  if (duration > 0) {
    setTimeout(() => dismiss(id), duration)
  }
}

export function dismiss(id) {
  _toasts = _toasts.map(t => t.id === id ? { ...t, visible: false } : t)
  setTimeout(() => { _toasts = _toasts.filter(t => t.id !== id) }, 300)
}

export function getToasts() {
  return _toasts
}

export const toast = {
  success : (msg, duration = 4000) => add('success', msg, duration),
  error   : (msg, duration = 6000) => add('error',   msg, duration),
  warning : (msg, duration = 5000) => add('warning', msg, duration),
  info    : (msg, duration = 4000) => add('info',    msg, duration),
}
