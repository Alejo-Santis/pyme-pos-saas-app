/**
 * ReceiptBuilder.js
 *
 * Construye el contenido del recibo en formato ESC/POS para impresoras térmicas.
 * Compatible con impresoras 58mm (32 cols) y 80mm (48 cols).
 */

// ── Comandos ESC/POS ──────────────────────────────────────────────────────────

const ESC = '\x1B'
const GS  = '\x1D'
const LF  = '\n'

const CMD = {
  INIT:           ESC + '@',          // Inicializar impresora
  ALIGN_LEFT:     ESC + 'a\x00',      // Alinear izquierda
  ALIGN_CENTER:   ESC + 'a\x01',      // Alinear centro
  ALIGN_RIGHT:    ESC + 'a\x02',      // Alinear derecha
  BOLD_ON:        ESC + 'E\x01',      // Negrita activa
  BOLD_OFF:       ESC + 'E\x00',      // Negrita inactiva
  DOUBLE_HEIGHT:  ESC + '!\x10',      // Doble alto
  DOUBLE_BOTH:    ESC + '!\x30',      // Doble alto y ancho
  NORMAL:         ESC + '!\x00',      // Texto normal
  UNDERLINE_ON:   ESC + '-\x01',      // Subrayado activo
  UNDERLINE_OFF:  ESC + '-\x00',      // Subrayado inactivo
  FEED_3:         ESC + 'd\x03',      // Avanzar 3 líneas
  FEED_1:         ESC + 'd\x01',      // Avanzar 1 línea
  CUT_PARTIAL:    GS  + 'V\x42\x00', // Corte parcial
  CUT_FULL:       GS  + 'V\x00',     // Corte completo
}

// ── Helpers de formato ────────────────────────────────────────────────────────

const COLS_58MM = 32
const COLS_80MM = 48

function cols(is80mm = false) {
  return is80mm ? COLS_80MM : COLS_58MM
}

/** Formatea número como moneda COP */
function fmt(n) {
  return '$' + Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
}

/** Formatea fecha/hora */
function fmtDateTime(date = new Date()) {
  const d = new Date(date)
  return d.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' })
       + ' '
       + d.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}

/** Centra texto en 'width' caracteres */
function center(text, width) {
  const str = String(text ?? '').substring(0, width)
  const pad = Math.max(0, Math.floor((width - str.length) / 2))
  return ' '.repeat(pad) + str
}

/** Línea con texto izquierda y derecha */
function twoCol(left, right, width) {
  const r = String(right ?? '')
  const l = String(left ?? '').substring(0, width - r.length - 1)
  const spaces = ' '.repeat(Math.max(1, width - l.length - r.length))
  return l + spaces + r
}

/** Divisor de caracteres repetidos */
function divider(char = '-', width = COLS_58MM) {
  return char.repeat(width) + LF
}

// ── Constructor principal ─────────────────────────────────────────────────────

/**
 * Construye el recibo completo en formato QZ Tray.
 *
 * @param {Object} params
 * @param {Object}   params.company      - Datos de la empresa (name, nit, address, phone, city)
 * @param {Object}   params.terminal     - Terminal POS (name)
 * @param {Object}   params.shift        - Turno (cashier_session_key, user.name)
 * @param {Object}   params.sale         - Resultado de la venta (internal_code, total, change)
 * @param {Array}    params.lines        - Líneas de la venta
 * @param {Array}    params.paymentForms - Formas de pago usadas
 * @param {Object}   [params.third]      - Tercero/cliente (name, identification_number)
 * @param {Object}   params.totals       - Totales (subtotal, discount, tax, total)
 * @param {boolean}  [params.is80mm]     - true para impresoras 80mm (48 cols)
 * @returns {Array} Array de objetos data para qz.print()
 */
export function buildReceipt({ company, terminal, shift, sale, lines: saleLines, paymentForms, third, totals, is80mm = false }) {
  const W = cols(is80mm)
  let c = '' // contenido acumulado

  const payMethodName = {
    10: 'Efectivo',
    30: 'Transferencia',
    45: 'Transf. bancaria',
    47: 'Nequi/billetera',
    42: 'Transferencia',
    48: 'T. Crédito',
    49: 'T. Débito',
  }

  // ── Encabezado empresa ────────────────────────────────────────────────
  c += CMD.INIT
  c += CMD.ALIGN_CENTER

  c += CMD.BOLD_ON + CMD.DOUBLE_HEIGHT
  c += center(company?.name ?? 'EMPRESA', W) + LF
  c += CMD.NORMAL + CMD.BOLD_OFF

  if (company?.nit)     c += 'NIT: ' + company.nit + LF
  if (company?.address) c += company.address.substring(0, W) + LF
  if (company?.city)    c += company.city.substring(0, W) + LF
  if (company?.phone)   c += 'Tel: ' + company.phone + LF

  c += divider('=', W)

  // ── Info del documento ────────────────────────────────────────────────
  c += CMD.ALIGN_LEFT
  c += twoCol('Ticket:', sale.internal_code, W) + LF
  c += twoCol('Fecha:', fmtDateTime(), W) + LF
  c += twoCol('Terminal:', terminal.name, W) + LF
  c += twoCol('Cajero:', shift?.user?.name ?? shift?.cashier_session_key ?? '', W) + LF

  if (third) {
    c += twoCol('Cliente:', third.name.substring(0, W - 10), W) + LF
    c += twoCol('ID:', third.identification_number, W) + LF
  } else {
    c += 'Cliente: Consumidor final' + LF
  }

  c += divider('-', W)

  // ── Artículos ──────────────────────────────────────────────────────────
  c += CMD.BOLD_ON
  c += twoCol('ARTÍCULO / Cant x Precio', 'TOTAL', W) + LF
  c += CMD.BOLD_OFF
  c += divider('-', W)

  for (const line of saleLines) {
    const taxPct    = (line.taxes ?? []).reduce((s, t) => s + (t.percent ?? 0), 0)
    const base      = line.amount * line.sale_price * (1 - (line.discount / 100))
    const lineTotal = base * (1 + taxPct / 100)

    // Primera línea: nombre (truncado)
    c += line.description.substring(0, W) + LF

    // Segunda línea: cant × precio [descuento] → total
    let detail = '  ' + line.amount + ' x ' + fmt(line.sale_price)
    if (line.discount > 0) detail += ' -' + line.discount + '%'
    c += twoCol(detail, fmt(lineTotal), W) + LF
  }

  c += divider('-', W)

  // ── Totales ────────────────────────────────────────────────────────────
  c += CMD.ALIGN_RIGHT
  c += twoCol('Subtotal:', fmt(totals.subtotal), W) + LF

  if (totals.discount > 0) {
    c += twoCol('Descuento:', '-' + fmt(totals.discount).replace('$', ''), W) + LF
  }

  if (totals.tax > 0) {
    c += twoCol('Impuestos:', fmt(totals.tax), W) + LF
  }

  c += CMD.BOLD_ON
  c += twoCol('TOTAL:', fmt(totals.total), W) + LF
  c += CMD.BOLD_OFF

  c += divider('=', W)

  // ── Formas de pago ─────────────────────────────────────────────────────
  c += CMD.ALIGN_LEFT
  for (const pf of paymentForms) {
    if (Number(pf.value) > 0) {
      const label = payMethodName[pf.payment_method_id] ?? 'Pago'
      c += twoCol(label + ':', fmt(pf.value), W) + LF
    }
  }

  const change = Math.max(0, paymentForms.reduce((s, p) => s + Number(p.value || 0), 0) - totals.total)
  if (change > 0) {
    c += CMD.BOLD_ON
    c += twoCol('CAMBIO:', fmt(change), W) + LF
    c += CMD.BOLD_OFF
  }

  c += divider('=', W)

  // ── Pie ────────────────────────────────────────────────────────────────
  c += CMD.ALIGN_CENTER
  c += CMD.BOLD_ON
  c += '¡Gracias por su compra!' + LF
  c += CMD.BOLD_OFF
  c += 'Conserve su recibo' + LF
  if (sale.internal_code) {
    c += 'Doc: ' + sale.internal_code + LF
  }

  // Avanzar y cortar
  c += CMD.FEED_3
  c += CMD.CUT_PARTIAL

  return [{ type: 'raw', format: 'plain', flavor: 'plain', data: c }]
}

/**
 * Construye el reporte de cierre de turno.
 *
 * @param {Object} params
 * @param {Object} params.terminal - Terminal POS
 * @param {Object} params.shift    - Turno cerrado (todos los campos)
 * @param {string} params.userName - Nombre del cajero
 * @param {boolean} [params.is80mm]
 */
export function buildShiftReport({ terminal, shift, userName, is80mm = false }) {
  const W = cols(is80mm)
  let c = ''

  c += CMD.INIT
  c += CMD.ALIGN_CENTER
  c += CMD.BOLD_ON + CMD.DOUBLE_HEIGHT
  c += center('CIERRE DE TURNO', W) + LF
  c += CMD.NORMAL + CMD.BOLD_OFF

  c += divider('=', W)

  c += CMD.ALIGN_LEFT
  c += twoCol('Terminal:', terminal.name, W) + LF
  c += twoCol('Cajero:', userName, W) + LF
  c += twoCol('Sesión:', shift.cashier_session_key, W) + LF
  c += twoCol('Apertura:', fmtDateTime(shift.shift_opened_at), W) + LF
  c += twoCol('Cierre:', fmtDateTime(shift.shift_closed_at ?? new Date()), W) + LF

  c += divider('=', W)

  c += CMD.BOLD_ON
  c += center('RESUMEN DE VENTAS', W) + LF
  c += CMD.BOLD_OFF

  c += twoCol('Base inicial:', fmt(shift.initial_balance), W) + LF
  c += twoCol('Total ventas:', fmt(shift.total_sales), W) + LF
  c += divider('-', W)
  c += twoCol('Efectivo:', fmt(shift.total_cash), W) + LF
  c += twoCol('Tarjetas:', fmt(Number(shift.total_card || 0) + Number(shift.total_transfer || 0)), W) + LF
  c += divider('-', W)

  c += CMD.BOLD_ON
  c += twoCol('TOTAL CAJA:', fmt(Number(shift.initial_balance) + Number(shift.total_cash)), W) + LF
  c += CMD.BOLD_OFF

  c += divider('=', W)
  c += CMD.ALIGN_CENTER
  c += 'Firma cajero: _______________' + LF
  c += CMD.FEED_3
  c += CMD.CUT_PARTIAL

  return [{ type: 'raw', format: 'plain', flavor: 'plain', data: c }]
}
