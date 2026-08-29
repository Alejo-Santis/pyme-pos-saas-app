<script>
  // Ícono de marca: monograma "P" (PyME POS SaaS).
  // variant="badge" → fondo azul sólido, para superficies claras.
  // variant="ghost" → fondo blanco translúcido, para superficies ya coloreadas
  //                   (sidebar navy, banda azul del login).
  // El agujero de la "P" usa <mask> (transparencia real), así que revela lo que
  // sea que esté detrás sin tener que adivinar el color exacto del fondo.
  let { size = 32, variant = 'badge', rounded = 'rounded-lg' } = $props()
  const uid = $props.id()
</script>

<div
  class="{variant === 'badge' ? 'bg-primary' : 'bg-white/20'} {rounded} flex items-center justify-center shrink-0 text-white"
  style="width:{size}px;height:{size}px"
>
  <svg viewBox="0 0 100 100" style="width:{size * 0.6}px;height:{size * 0.6}px" aria-hidden="true">
    <mask id="pmark-{uid}">
      <rect x="34" y="22" width="13" height="56" rx="3" fill="#fff"/>
      <rect x="34" y="22" width="34" height="30" rx="15" fill="#fff"/>
      <rect x="50" y="29" width="11" height="16" rx="8" fill="#000"/>
    </mask>
    <rect x="0" y="0" width="100" height="100" fill="currentColor" mask="url(#pmark-{uid})"/>
  </svg>
</div>
