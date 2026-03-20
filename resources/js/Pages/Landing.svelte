<script>
  let { plans = [] } = $props()

  let billingPeriod = $state('monthly')

  function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(price)
  }

  function getPrice(plan) {
    return billingPeriod === 'yearly' ? plan.price_yearly : plan.price_monthly
  }

  function getFeatures(plan) {
    if (!plan.features) return []
    if (Array.isArray(plan.features)) return plan.features
    try { return JSON.parse(plan.features) } catch { return [] }
  }

  const features = [
    {
      icon: 'mdi-file-document-check-outline',
      title: 'Facturación Electrónica DIAN',
      desc: 'Emite facturas electrónicas válidas ante la DIAN con firma digital, CUFE y envío automático en segundos.',
    },
    {
      icon: 'mdi-point-of-sale',
      title: 'Punto de Venta (POS)',
      desc: 'POS táctil con turnos de caja, múltiples terminales, impresión de recibos vía QZ Tray y cierre diario.',
    },
    {
      icon: 'mdi-package-variant-closed',
      title: 'Inventario Multi-bodega',
      desc: 'Control de stock en tiempo real, traslados entre bodegas, categorías y alertas de agotamiento.',
    },
    {
      icon: 'mdi-calculator-variant-outline',
      title: 'Contabilidad PUC Colombia',
      desc: 'Motor contable con asientos automáticos, PUC completo cargado desde el primer día y reportes NIIF PYMES.',
    },
    {
      icon: 'mdi-bank-outline',
      title: 'Caja y Bancos',
      desc: 'Gestión de cajas, cuentas bancarias, movimientos de tesorería y conciliación bancaria.',
    },
    {
      icon: 'mdi-chart-bar',
      title: 'Reportes y Exportaciones',
      desc: 'Reportes financieros, de inventario y ventas exportables a Excel y PDF en cualquier momento.',
    },
  ]

  // Plan del medio como destacado
  const featuredSlug = $derived(plans[1]?.slug ?? '')
</script>

<svelte:head>
  <title>NextPOS SaaS — ERP Colombia</title>
  <meta name="description" content="Plataforma SaaS de facturación electrónica y gestión empresarial para Colombia. Factura electrónica DIAN, POS, inventario y contabilidad en un solo lugar." />
</svelte:head>

<!-- ── Navbar ──────────────────────────────────────────────────────────── -->
<header class="fixed top-0 inset-x-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

    <!-- Logo -->
    <a href="/" class="flex items-center gap-2 font-bold text-xl text-[#1e3a5f]">
      <span class="w-8 h-8 bg-[#2563eb] rounded-lg flex items-center justify-center">
        <i class="mdi mdi-storefront-outline text-white text-base"></i>
      </span>
      NextPOS
    </a>

    <!-- Nav links (desktop) -->
    <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-gray-600">
      <a href="#caracteristicas" class="hover:text-[#2563eb] transition-colors">Características</a>
      <a href="#precios" class="hover:text-[#2563eb] transition-colors">Precios</a>
    </nav>

    <!-- CTA -->
    <div class="flex items-center gap-3">
      <a href="/admin/login" class="hidden sm:inline text-sm text-gray-600 hover:text-[#2563eb] font-medium transition-colors">
        Iniciar sesión
      </a>
      <a href="/register" class="inline-flex items-center gap-1.5 bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors shadow-sm">
        <i class="mdi mdi-rocket-launch-outline text-base"></i>
        Empezar gratis
      </a>
    </div>
  </div>
</header>

<!-- ── Hero ───────────────────────────────────────────────────────────── -->
<section class="relative pt-24 pb-20 overflow-hidden bg-gradient-to-br from-[#f0f6ff] via-white to-[#eff6ff]">

  <!-- Decoración de fondo -->
  <div class="absolute inset-0 pointer-events-none overflow-hidden">
    <div class="absolute -top-40 -right-40 w-[600px] h-[600px] bg-[#2563eb]/5 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-40 -left-40 w-[500px] h-[500px] bg-[#1e3a5f]/5 rounded-full blur-3xl"></div>
  </div>

  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">

    <!-- Badge -->
    <span class="inline-flex items-center gap-2 bg-[#2563eb]/10 text-[#2563eb] text-xs font-semibold px-3 py-1.5 rounded-full mb-6">
      <i class="mdi mdi-check-circle text-sm"></i>
      Certificado DIAN — Facturación Electrónica UBL 2.1
    </span>

    <!-- Headline -->
    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-[#1e3a5f] leading-tight mb-6">
      El ERP colombiano<br>
      <span class="text-[#2563eb]">que tu empresa necesita</span>
    </h1>

    <p class="text-lg sm:text-xl text-gray-500 max-w-2xl mx-auto mb-10 leading-relaxed">
      Facturación electrónica DIAN, POS, inventario, contabilidad y nómina
      en una sola plataforma. Sin complicaciones, desde el primer día.
    </p>

    <!-- CTAs -->
    <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-14">
      <a href="/register" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-[#2563eb] hover:bg-[#1d4ed8] text-white font-bold text-base px-8 py-3.5 rounded-xl transition-colors shadow-lg shadow-blue-200">
        <i class="mdi mdi-rocket-launch-outline text-lg"></i>
        Crear mi empresa gratis
      </a>
      <a href="#caracteristicas" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white hover:bg-gray-50 text-[#1e3a5f] font-semibold text-base px-8 py-3.5 rounded-xl border border-gray-200 transition-colors">
        <i class="mdi mdi-play-circle-outline text-lg"></i>
        Ver características
      </a>
    </div>

    <!-- Stats -->
    <div class="inline-flex flex-wrap justify-center gap-8 text-center">
      {#each [
        { num: '100%', label: 'Válido ante la DIAN' },
        { num: '∞', label: 'Usuarios por empresa' },
        { num: '30 días', label: 'Prueba gratuita' },
      ] as stat}
        <div>
          <div class="text-2xl font-extrabold text-[#1e3a5f]">{stat.num}</div>
          <div class="text-sm text-gray-500 mt-0.5">{stat.label}</div>
        </div>
      {/each}
    </div>
  </div>
</section>

<!-- ── Características ─────────────────────────────────────────────────── -->
<section id="caracteristicas" class="py-20 bg-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="text-center mb-14">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-[#1e3a5f] mb-4">
        Todo lo que tu negocio necesita
      </h2>
      <p class="text-gray-500 text-lg max-w-xl mx-auto">
        Módulos integrados que trabajan juntos para que no pierdas tiempo entre sistemas.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      {#each features as feat}
        <div class="group p-6 rounded-2xl border border-gray-100 bg-white hover:border-[#2563eb]/30 hover:shadow-lg hover:shadow-blue-50 transition-all">
          <div class="w-12 h-12 bg-[#eff6ff] group-hover:bg-[#2563eb] rounded-xl flex items-center justify-center mb-4 transition-colors">
            <i class="mdi {feat.icon} text-[#2563eb] group-hover:text-white text-2xl transition-colors"></i>
          </div>
          <h3 class="font-bold text-[#1e3a5f] text-base mb-2">{feat.title}</h3>
          <p class="text-gray-500 text-sm leading-relaxed">{feat.desc}</p>
        </div>
      {/each}
    </div>
  </div>
</section>

<!-- ── Precios ─────────────────────────────────────────────────────────── -->
{#if plans.length > 0}
<section id="precios" class="py-20 bg-[#f4f7fe]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="text-center mb-12">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-[#1e3a5f] mb-4">
        Planes simples y transparentes
      </h2>
      <p class="text-gray-500 text-lg mb-8">
        Empieza gratis. Escala cuando crezcas.
      </p>

      <!-- Toggle mensual / anual -->
      <div class="inline-flex items-center gap-1 bg-white border border-gray-200 rounded-xl p-1">
        <button
          onclick={() => billingPeriod = 'monthly'}
          class="px-5 py-2 rounded-lg text-sm font-semibold transition-all {billingPeriod === 'monthly' ? 'bg-[#2563eb] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'}"
        >
          Mensual
        </button>
        <button
          onclick={() => billingPeriod = 'yearly'}
          class="px-5 py-2 rounded-lg text-sm font-semibold transition-all {billingPeriod === 'yearly' ? 'bg-[#2563eb] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'}"
        >
          Anual
          <span class="ml-1.5 bg-[#0acf97]/20 text-[#0acf97] text-xs px-1.5 py-0.5 rounded-full font-bold">-20%</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
      {#each plans as plan}
        {@const featured = plan.slug === featuredSlug}
        {@const planFeatures = getFeatures(plan)}
        <div class="relative flex flex-col rounded-2xl border-2 overflow-hidden transition-shadow
          {featured
            ? 'border-[#2563eb] shadow-xl shadow-blue-100'
            : 'border-gray-200 bg-white hover:shadow-lg'}">

          {#if featured}
            <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-[#2563eb] to-[#1d4ed8]"></div>
            <div class="absolute top-4 right-4 bg-[#2563eb] text-white text-xs font-bold px-3 py-1 rounded-full">
              Popular
            </div>
          {/if}

          <div class="p-7 {featured ? 'bg-white' : ''}">
            <h3 class="text-lg font-extrabold text-[#1e3a5f] mb-1">{plan.name}</h3>
            <p class="text-gray-500 text-sm mb-5 min-h-[2.5rem]">{plan.description ?? ''}</p>

            <div class="mb-1">
              <span class="text-4xl font-extrabold text-[#1e3a5f]">
                {formatPrice(getPrice(plan))}
              </span>
              <span class="text-gray-400 text-sm ml-1">
                / {billingPeriod === 'yearly' ? 'año' : 'mes'}
              </span>
            </div>

            {#if plan.trial_days > 0}
              <p class="text-xs text-[#0acf97] font-semibold mb-5">
                <i class="mdi mdi-check-circle mr-1"></i>{plan.trial_days} días de prueba gratuita
              </p>
            {/if}

            <a
              href="/register"
              class="block w-full text-center font-bold text-sm py-3 rounded-xl transition-colors mt-2
                {featured
                  ? 'bg-[#2563eb] hover:bg-[#1d4ed8] text-white shadow-md shadow-blue-200'
                  : 'bg-[#eff6ff] hover:bg-[#2563eb] text-[#2563eb] hover:text-white'}"
            >
              Empezar gratis
            </a>
          </div>

          {#if planFeatures.length > 0}
            <div class="px-7 pb-7 mt-auto">
              <div class="border-t border-gray-100 pt-5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Incluye</p>
                <ul class="space-y-2">
                  {#each planFeatures as feat}
                    <li class="flex items-start gap-2 text-sm text-gray-600">
                      <i class="mdi mdi-check-circle text-[#0acf97] mt-0.5 shrink-0"></i>
                      {feat}
                    </li>
                  {/each}
                </ul>
              </div>
            </div>
          {/if}
        </div>
      {/each}
    </div>
  </div>
</section>
{/if}

<!-- ── CTA final ───────────────────────────────────────────────────────── -->
<section class="py-20 bg-gradient-to-br from-[#1e3a5f] to-[#1d4ed8]">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">
      ¿Listo para digitalizar tu empresa?
    </h2>
    <p class="text-blue-200 text-lg mb-8 max-w-xl mx-auto">
      Crea tu cuenta en minutos. Sin tarjeta de crédito. Sin contratos.
      Cancela cuando quieras.
    </p>
    <a
      href="/register"
      class="inline-flex items-center gap-2 bg-white hover:bg-blue-50 text-[#1e3a5f] font-bold text-base px-8 py-4 rounded-xl transition-colors shadow-xl"
    >
      <i class="mdi mdi-rocket-launch-outline text-lg"></i>
      Crear mi empresa ahora — es gratis
    </a>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────────────── -->
<footer class="bg-[#1e3a5f] border-t border-white/10 py-10">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col md:flex-row items-center justify-between gap-6">

      <!-- Logo -->
      <div class="flex items-center gap-2 text-white font-bold text-lg">
        <span class="w-8 h-8 bg-[#2563eb] rounded-lg flex items-center justify-center">
          <i class="mdi mdi-storefront-outline text-white text-base"></i>
        </span>
        NextPOS SaaS
      </div>

      <!-- Links -->
      <nav class="flex flex-wrap justify-center gap-6 text-sm text-blue-200">
        <a href="#caracteristicas" class="hover:text-white transition-colors">Características</a>
        <a href="#precios" class="hover:text-white transition-colors">Precios</a>
        <a href="/register" class="hover:text-white transition-colors">Registrarse</a>
        <a href="/admin/login" class="hover:text-white transition-colors">Admin</a>
      </nav>

      <!-- Copyright -->
      <p class="text-xs text-blue-300">
        &copy; {new Date().getFullYear()} NextPOS SaaS Colombia
      </p>
    </div>
  </div>
</footer>
