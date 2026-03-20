<script>
  import { useForm } from '@inertiajs/svelte'

  let { plans = [], errors = {} } = $props()

  // Plan seleccionado por defecto: el del medio (Profesional)
  const defaultPlanSlug = $derived(plans[1]?.slug ?? plans[0]?.slug ?? '')
  let selectedPlan = $state('')

  const form = useForm({
    company_name:     '',
    company_nit:      '',
    company_slug:     '',
    admin_name:       '',
    admin_email:      '',
    admin_password:   '',
    admin_password_confirmation: '',
    plan_slug:        '',
  })

  // Aplicar plan por defecto si el usuario aun no selecciona uno
  $effect(() => {
    if (!selectedPlan && defaultPlanSlug) {
      selectedPlan = defaultPlanSlug
    }
  })

  // Sincronizar plan al form cuando cambia
  $effect(() => {
    $form.plan_slug = selectedPlan
  })

  // Auto-generar slug desde nombre de empresa
  function generateSlug(name) {
    return name
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // quitar tildes
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .slice(0, 50)
  }

  function onCompanyNameInput(e) {
    $form.company_name = e.target.value
    $form.company_slug = generateSlug(e.target.value)
  }

  function submit(e) {
    e.preventDefault()
    $form.post('/register')
  }

  let loginSlug = $state('')

  function goToLogin() {
    const slug = loginSlug.trim()
    if (!slug) return
    // El register siempre está en el dominio central (sin subdominio),
    // por lo que window.location.hostname ES la base completa.
    const base = window.location.hostname
    window.location.href = `${window.location.protocol}//${slug}.${base}/login`
  }

  function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price)
  }
</script>

<!-- Fondo con círculos concéntricos SVG (consistente con AuthLayout) -->
<div class="relative min-h-screen bg-slate-100 flex items-center justify-center p-4 overflow-hidden">

  <!-- Fondo SVG círculos concéntricos -->
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

  <!-- Tarjeta de registro (más ancha) -->
  <div class="relative w-full max-w-2xl z-10 my-6">

    <!-- Cabecera: banda azul con logo -->
    <div class="bg-blue-600 rounded-t-2xl py-5 text-center shadow-md">
      <div class="flex items-center justify-center gap-2">
        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
          <i class="mdi mdi-lightning-bolt text-white text-lg"></i>
        </div>
        <span class="text-white text-2xl font-bold tracking-tight">NextPOS</span>
        <span class="text-blue-200 text-xl font-light">SaaS</span>
      </div>
      <p class="text-blue-200 text-xs mt-1.5">Crea tu empresa y empieza a facturar electrónicamente</p>
    </div>

    <!-- Cuerpo: fondo blanco -->
    <div class="bg-white rounded-b-2xl shadow-xl px-8 py-7">

      <div class="mb-6">
        <h1 class="text-slate-800 text-lg font-bold">Registro de empresa</h1>
        <p class="text-slate-500 text-sm mt-0.5">
          Comienza con {plans[0]?.trial_days ?? 15} días de prueba gratuita. Sin tarjeta de crédito.
        </p>
      </div>

      <!-- Error general -->
      {#if errors?.general}
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
          <i class="mdi mdi-alert-circle text-red-500 shrink-0"></i>
          <span>{errors.general}</span>
        </div>
      {/if}

      <form onsubmit={submit} class="space-y-6">

        <!-- ── Selección de plan ─────────────────────────────────────── -->
        <div>
          <h2 class="text-slate-600 text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <i class="mdi mdi-tag-outline text-blue-500"></i>Selecciona tu plan
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {#each plans as plan}
              <button
                type="button"
                onclick={() => selectedPlan = plan.slug}
                class="relative text-left p-4 rounded-xl border-2 transition cursor-pointer
                       {selectedPlan === plan.slug
                         ? 'border-blue-500 bg-blue-50'
                         : 'border-slate-200 bg-slate-50 hover:border-slate-300'}"
              >
                {#if selectedPlan === plan.slug}
                  <i class="mdi mdi-check-circle absolute top-3 right-3 text-blue-500"></i>
                {/if}
                <div class="text-slate-800 font-semibold text-sm">{plan.name}</div>
                <div class="text-blue-600 font-bold text-lg mt-1">
                  {formatPrice(plan.price_monthly)}
                  <span class="text-slate-400 font-normal text-xs">/mes</span>
                </div>
                <div class="text-slate-500 text-xs mt-1 leading-snug">{plan.description}</div>
              </button>
            {/each}
          </div>
          {#if errors?.plan_slug}
            <p class="text-red-500 text-xs mt-1">{errors.plan_slug}</p>
          {/if}
        </div>

        <!-- ── Datos de la empresa ───────────────────────────────────── -->
        <div>
          <h2 class="text-slate-600 text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <i class="mdi mdi-office-building-outline text-blue-500"></i>Datos de la empresa
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- Nombre empresa -->
            <div class="sm:col-span-2">
              <label for="company_name" class="block text-slate-700 text-sm font-medium mb-1">Nombre de la empresa *</label>
              <input
                id="company_name"
                type="text"
                oninput={onCompanyNameInput}
                value={$form.company_name}
                class="w-full border {errors.company_name ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="Mi Empresa S.A.S."
              />
              {#if errors?.company_name}
                <p class="text-red-500 text-xs mt-1">{errors.company_name}</p>
              {/if}
            </div>

            <!-- NIT -->
            <div>
              <label for="company_nit" class="block text-slate-700 text-sm font-medium mb-1">NIT *</label>
              <input
                id="company_nit"
                type="text"
                bind:value={$form.company_nit}
                class="w-full border {errors.company_nit ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="900123456-1"
              />
              {#if errors?.company_nit}
                <p class="text-red-500 text-xs mt-1">{errors.company_nit}</p>
              {/if}
            </div>

            <!-- Slug / subdominio -->
            <div>
              <label for="company_slug" class="block text-slate-700 text-sm font-medium mb-1">Identificador (subdominio) *</label>
              <input
                id="company_slug"
                type="text"
                bind:value={$form.company_slug}
                class="w-full border {errors.company_slug ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="mi-empresa"
              />
              <p class="text-slate-400 text-xs mt-1">
                Tu URL: <span class="text-blue-600 font-medium">{$form.company_slug || 'mi-empresa'}.pymepossaas-app.test</span>
              </p>
              {#if errors?.company_slug}
                <p class="text-red-500 text-xs mt-1">{errors.company_slug}</p>
              {/if}
            </div>

          </div>
        </div>

        <!-- ── Administrador ─────────────────────────────────────────── -->
        <div>
          <h2 class="text-slate-600 text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <i class="mdi mdi-account-tie-outline text-blue-500"></i>Administrador de la empresa
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
              <label for="admin_name" class="block text-slate-700 text-sm font-medium mb-1">Nombre completo *</label>
              <input
                id="admin_name"
                type="text"
                bind:value={$form.admin_name}
                class="w-full border {errors.admin_name ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="Juan Pérez"
              />
              {#if errors?.admin_name}
                <p class="text-red-500 text-xs mt-1">{errors.admin_name}</p>
              {/if}
            </div>

            <div>
              <label for="admin_email" class="block text-slate-700 text-sm font-medium mb-1">Correo electrónico *</label>
              <input
                id="admin_email"
                type="email"
                bind:value={$form.admin_email}
                class="w-full border {errors.admin_email ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="admin@empresa.com"
              />
              {#if errors?.admin_email}
                <p class="text-red-500 text-xs mt-1">{errors.admin_email}</p>
              {/if}
            </div>

            <div>
              <label for="admin_password" class="block text-slate-700 text-sm font-medium mb-1">Contraseña *</label>
              <input
                id="admin_password"
                type="password"
                bind:value={$form.admin_password}
                class="w-full border {errors.admin_password ? 'border-red-400' : 'border-slate-300'}
                       text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                placeholder="Mínimo 8 caracteres"
              />
              {#if errors?.admin_password}
                <p class="text-red-500 text-xs mt-1">{errors.admin_password}</p>
              {/if}
            </div>

            <div>
              <label for="admin_password_confirmation" class="block text-slate-700 text-sm font-medium mb-1">Confirmar contraseña *</label>
              <input
                id="admin_password_confirmation"
                type="password"
                bind:value={$form.admin_password_confirmation}
                class="w-full border border-slate-300 text-slate-800 placeholder-slate-400
                       rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-blue-500
                       focus:ring-1 focus:ring-blue-500 transition"
                placeholder="Repite la contraseña"
              />
            </div>

          </div>
        </div>

        <!-- Botón registrar -->
        <div class="pt-1 text-center">
          <button
            type="submit"
            disabled={$form.processing}
            class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed
                   text-white font-semibold rounded-lg px-10 py-3 text-sm transition
                   inline-flex items-center gap-2 cursor-pointer"
          >
            {#if $form.processing}
              <i class="mdi mdi-loading mdi-spin"></i> Creando empresa...
            {:else}
              <i class="mdi mdi-rocket-launch-outline"></i> Crear empresa y comenzar prueba gratuita
            {/if}
          </button>
        </div>

        <!-- Acceso a empresa existente -->
        <div class="border-t border-slate-200 pt-4">
          <p class="text-center text-slate-500 text-xs mb-3">¿Ya tienes una empresa registrada?</p>
          <div class="flex gap-2 max-w-sm mx-auto">
            <div class="flex-1 flex items-center border border-slate-300 rounded-lg px-3 py-2 gap-1
                        focus-within:border-blue-500 transition text-xs bg-white">
              <input
                type="text"
                placeholder="miempresa"
                bind:value={loginSlug}
                onkeydown={(e) => e.key === 'Enter' && goToLogin()}
                class="text-slate-700 placeholder-slate-400 outline-none w-24 min-w-0 bg-transparent"
              />
              <span class="text-slate-400 truncate">.pymepossaas-app.test</span>
            </div>
            <button
              type="button"
              onclick={goToLogin}
              class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700
                     rounded-lg px-3 py-2 text-xs transition cursor-pointer whitespace-nowrap font-medium"
            >
              <i class="mdi mdi-login"></i> Ir al login
            </button>
          </div>
        </div>

      </form>
    </div>

    <p class="text-center text-slate-400 text-xs mt-5">
      &copy; {new Date().getFullYear()} NextPOS SaaS · Todos los derechos reservados
    </p>
  </div>
</div>
