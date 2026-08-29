<script>
  import { Link } from '@inertiajs/svelte'
  import BrandMark from '@/Components/UI/BrandMark.svelte'

  const modules = [
    { icon: 'mdi-file-document-outline',        title: 'Facturación Electrónica DIAN', desc: 'Documentos UBL 2.1, notas crédito y débito, documento soporte de compra.' },
    { icon: 'mdi-point-of-sale',                title: 'Punto de Venta',               desc: 'Terminales, turnos de caja y ventas rápidas conectadas a inventario.' },
    { icon: 'mdi-warehouse',                     title: 'Inventario Multi-bodega',      desc: 'Artículos, categorías y traslados entre bodegas con kardex.' },
    { icon: 'mdi-calculator-variant-outline',    title: 'Contabilidad',                 desc: 'PUC Colombia, asientos automáticos, libro diario y balance general.' },
    { icon: 'mdi-cash-multiple',                 title: 'Caja y Bancos',                desc: 'Cajas, cuentas bancarias y conciliación de movimientos.' },
    { icon: 'mdi-account-group-outline',         title: 'Nómina Colombiana',            desc: 'Liquidación, prestaciones sociales y aportes de ley.' },
  ]

  const year = new Date().getFullYear()
</script>

<div class="min-h-screen bg-body text-gray-800">

  <!-- ═══ Barra superior ═══════════════════════════════════════════════ -->
  <header class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <BrandMark size={32} />
        <span class="text-gray-900 text-lg font-bold tracking-tight">PyME POS</span>
        <span class="text-gray-400 text-lg font-light">SaaS</span>
      </div>
      <nav class="flex items-center gap-3">
        <Link href="/login" class="text-sm font-medium text-gray-600 hover:text-primary transition px-3 py-2">
          Iniciar sesión
        </Link>
        <Link href="/register"
          class="text-sm font-medium text-white bg-primary hover:bg-primary-dark transition px-4 py-2 rounded-lg">
          Registrar mi empresa
        </Link>
      </nav>
    </div>
  </header>

  <!-- ═══ Hero ═════════════════════════════════════════════════════════ -->
  <section class="relative overflow-hidden border-b border-gray-200">
    <!-- Motivo de círculos concéntricos, sutil, sin degradado -->
    <div class="absolute inset-0 pointer-events-none">
      <svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice">
        <circle fill="rgba(37,99,235,0.04)" cx="1000" cy="120" r="420"/>
        <circle fill="rgba(37,99,235,0.06)" cx="1000" cy="120" r="320"/>
        <circle fill="rgba(37,99,235,0.08)" cx="1000" cy="120" r="200"/>
      </svg>
    </div>

    <div class="relative max-w-6xl mx-auto px-6 py-20">
      <div class="max-w-2xl">
        <span class="inline-block text-xs font-semibold tracking-wide uppercase text-primary bg-primary-soft px-3 py-1 rounded-full mb-5">
          ERP para PYMES en Colombia
        </span>
        <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 leading-tight mb-5">
          Facturación electrónica, POS e inventario en un solo sistema
        </h1>
        <p class="text-lg text-gray-600 leading-relaxed mb-8">
          PyME POS SaaS conecta ventas, inventario, contabilidad y nómina bajo un mismo lugar,
          cumpliendo con los requisitos de facturación electrónica de la DIAN.
        </p>
        <div class="flex flex-wrap items-center gap-3">
          <Link href="/register"
            class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-primary hover:bg-primary-dark transition px-6 py-3 rounded-lg shadow-btn">
            <i class="mdi mdi-rocket-launch-outline text-base"></i>
            Comenzar prueba gratuita
          </Link>
          <Link href="/login"
            class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 hover:border-gray-400 transition px-6 py-3 rounded-lg">
            Ya tengo una cuenta
          </Link>
        </div>
      </div>
    </div>
  </section>

  <!-- ═══ Módulos ══════════════════════════════════════════════════════ -->
  <section class="max-w-6xl mx-auto px-6 py-16">
    <div class="text-center mb-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-2">Todo lo que tu empresa necesita</h2>
      <p class="text-gray-500 text-sm">Módulos integrados, sin hojas de cálculo sueltas ni sistemas desconectados</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      {#each modules as m}
        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-card">
          <div class="w-11 h-11 bg-primary-soft rounded-lg flex items-center justify-center mb-4">
            <i class="mdi {m.icon} text-primary text-2xl"></i>
          </div>
          <h3 class="text-base font-semibold text-gray-900 mb-1.5">{m.title}</h3>
          <p class="text-sm text-gray-500 leading-relaxed">{m.desc}</p>
        </div>
      {/each}
    </div>
  </section>

  <!-- ═══ CTA final ════════════════════════════════════════════════════ -->
  <section class="border-t border-gray-200 bg-white">
    <div class="max-w-6xl mx-auto px-6 py-14 flex flex-col sm:flex-row items-center justify-between gap-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900 mb-1">¿Listo para ordenar la operación de tu empresa?</h2>
        <p class="text-sm text-gray-500">Crea tu empresa en minutos, sin tarjeta de crédito.</p>
      </div>
      <Link href="/register"
        class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-primary hover:bg-primary-dark transition px-6 py-3 rounded-lg shadow-btn shrink-0">
        <i class="mdi mdi-rocket-launch-outline text-base"></i>
        Registrar mi empresa
      </Link>
    </div>
  </section>

  <!-- ═══ Footer ═══════════════════════════════════════════════════════ -->
  <footer class="border-t border-gray-200">
    <div class="max-w-6xl mx-auto px-6 py-6 flex flex-col sm:flex-row items-center justify-between gap-2">
      <p class="text-xs text-gray-400">&copy; {year} PyME POS SaaS · Todos los derechos reservados</p>
      <p class="text-xs text-gray-400">Facturación electrónica DIAN · Colombia</p>
    </div>
  </footer>

</div>
