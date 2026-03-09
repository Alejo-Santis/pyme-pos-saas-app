<script>
  let { children } = $props()
</script>

<!-- Fondo con círculos concéntricos SVG (inspirado en Xedoc, adaptado a Tailwind) -->
<div class="relative min-h-screen bg-body flex items-center justify-center p-4 overflow-hidden">

  <!-- Fondo SVG círculos concéntricos en tono azul primario -->
  <div class="absolute inset-0 pointer-events-none">
    <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 800 800">
      <g>
        <circle fill="rgba(37,99,235,0.04)" cx="400" cy="400" r="700"/>
        <circle fill="rgba(37,99,235,0.06)" cx="400" cy="400" r="550"/>
        <circle fill="rgba(37,99,235,0.09)" cx="400" cy="400" r="380"/>
        <circle fill="rgba(37,99,235,0.12)" cx="400" cy="400" r="220"/>
        <circle fill="rgba(37,99,235,0.15)" cx="400" cy="400" r="100"/>
      </g>
    </svg>
  </div>

  <!-- Tarjeta central -->
  <div class="relative w-full max-w-md z-10">

    <!-- Cabecera de la tarjeta: banda azul con logo -->
    <div class="bg-primary rounded-t-2xl py-6 text-center shadow-md">
      <div class="flex items-center justify-center gap-2">
        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
          <i class="mdi mdi-lightning-bolt text-white text-lg"></i>
        </div>
        <span class="text-white text-2xl font-bold tracking-tight">NextPOS</span>
        <span class="text-blue-200 text-xl font-light">SaaS</span>
      </div>
      <p class="text-blue-200 text-xs mt-1.5 tracking-wide">ERP · Facturación Electrónica DIAN · Colombia</p>
    </div>

    <!-- Cuerpo de la tarjeta: fondo blanco -->
    <div class="bg-white rounded-b-2xl shadow-xl px-8 py-7">
      {@render children()}
    </div>

    <!-- Footer -->
    <p class="text-center text-slate-400 text-xs mt-5">
      &copy; {new Date().getFullYear()} NextPOS SaaS · Todos los derechos reservados
    </p>

  </div>
</div>
