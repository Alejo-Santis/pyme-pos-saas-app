/**
 * QzTrayService.js
 *
 * Servicio para integración con QZ Tray (impresión POS).
 * QZ Tray es una aplicación de escritorio que debe estar instalada y corriendo
 * en el computador del cajero.
 *
 * Descarga QZ Tray: https://qz.io/download/
 */

let qz = null
let _connected = false

// ── Carga dinámica de la librería QZ Tray ────────────────────────────────────

function loadQzLibrary() {
  return new Promise((resolve, reject) => {
    if (window.qz) {
      resolve(window.qz)
      return
    }
    // Intentar cargar desde archivo local primero; fallback a CDN
    const src = '/js/qz-tray.min.js'
    const script = document.createElement('script')
    script.src = src
    script.onload = () => resolve(window.qz)
    script.onerror = () => {
      // Fallback CDN
      const cdn = document.createElement('script')
      cdn.src = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.4/qz-tray.min.js'
      cdn.onload = () => resolve(window.qz)
      cdn.onerror = () => reject(new Error('No se pudo cargar la librería QZ Tray'))
      document.head.appendChild(cdn)
    }
    document.head.appendChild(script)
  })
}

// ── Conexión ─────────────────────────────────────────────────────────────────

/**
 * Conecta con QZ Tray en el computador local.
 * Para producción, reemplazar las promesas de seguridad con certificado real.
 * @returns {Promise<boolean>} true si conectó exitosamente
 */
export async function connect() {
  try {
    qz = await loadQzLibrary()

    if (!qz) throw new Error('QZ Tray no disponible')

    // Configuración de seguridad — DESARROLLO (sin certificado firmado)
    // Para producción: proveer certificado .crt y firma privada
    qz.security.setCertificatePromise((resolve) => resolve(''))
    qz.security.setSignatureAlgorithm('SHA512')
    qz.security.setSignaturePromise((toSign) => (resolve) => resolve(null))

    if (!qz.websocket.isActive()) {
      await qz.websocket.connect({ retries: 2, delay: 1 })
    }

    _connected = true
    return true
  } catch (err) {
    _connected = false
    console.warn('[QZ Tray] No disponible:', err.message)
    return false
  }
}

/**
 * Desconecta QZ Tray.
 */
export async function disconnect() {
  if (qz && qz.websocket.isActive()) {
    await qz.websocket.disconnect()
  }
  _connected = false
}

/**
 * Verifica si QZ Tray está conectado y activo.
 */
export function isConnected() {
  return _connected && qz && qz.websocket.isActive()
}

// ── Impresión ─────────────────────────────────────────────────────────────────

/**
 * Imprime datos RAW ESC/POS en la impresora indicada.
 * @param {string} printerName - Nombre exacto de la impresora en el SO
 * @param {Array}  data        - Array de objetos QZ data (ver ReceiptBuilder)
 * @param {Object} [options]   - Opciones adicionales para qz.configs.create
 */
export async function printRaw(printerName, data, options = {}) {
  if (!isConnected()) {
    const ok = await connect()
    if (!ok) throw new Error('QZ Tray no está disponible en este equipo')
  }

  const config = qz.configs.create(printerName, {
    scaleContent: false,
    encoding: 'UTF-8',
    ...options,
  })

  await qz.print(config, data)
}

/**
 * Lista las impresoras disponibles en el equipo.
 * @returns {Promise<string[]>}
 */
export async function listPrinters() {
  if (!isConnected()) await connect()
  if (!isConnected()) return []
  return await qz.printers.find()
}

/**
 * Verifica que una impresora con el nombre dado existe.
 * @param {string} printerName
 * @returns {Promise<boolean>}
 */
export async function printerExists(printerName) {
  if (!printerName) return false
  try {
    const found = await qz.printers.find(printerName)
    return !!found
  } catch {
    return false
  }
}
