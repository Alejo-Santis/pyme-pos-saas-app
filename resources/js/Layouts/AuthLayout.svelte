<script>
  let { children } = $props()
</script>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">

  <!-- Card central -->
  <div class="w-full max-w-md">

    <!-- Logo / marca -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-2">
        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
          <i class="mdi mdi-lightning-bolt text-white text-xl"></i>
        </div>
        <span class="text-white text-2xl font-bold tracking-tight">NextPOS</span>
        <span class="text-blue-400 text-2xl font-light">SaaS</span>
      </div>
      <p class="text-slate-400 text-sm">ERP · Facturación Electrónica DIAN · Colombia</p>
    </div>

    <!-- Contenido de la página -->
    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-8 shadow-2xl">
      {@render children()}
    </div>

    <!-- Footer -->
    <p class="text-center text-slate-500 text-xs mt-6">
      &copy; {new Date().getFullYear()} NextPOS SaaS · Todos los derechos reservados
    </p>

  </div>
</div>
