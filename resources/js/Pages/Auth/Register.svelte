<script>
  import { useForm } from '@inertiajs/svelte'
  import AuthLayout from '@/Layouts/AuthLayout.svelte'

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

  function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price)
  }
</script>

<div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 flex items-center justify-center p-4">
  <div class="w-full max-w-2xl">

    <!-- Logo -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center gap-2 mb-2">
        <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
          <i class="mdi mdi-lightning-bolt text-white text-xl"></i>
        </div>
        <span class="text-white text-2xl font-bold tracking-tight">NextPOS</span>
        <span class="text-blue-400 text-2xl font-light">SaaS</span>
      </div>
      <p class="text-slate-400 text-sm">Crea tu empresa y empieza a facturar electrónicamente</p>
    </div>

    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-8 shadow-2xl">

      <h1 class="text-white text-xl font-semibold mb-1">Registro de empresa</h1>
      <p class="text-slate-400 text-sm mb-6">
        Comienza con {plans[0]?.trial_days ?? 15} días de prueba gratuita. Sin tarjeta de crédito.
      </p>

      <!-- Error general -->
      {#if errors?.general}
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg p-3 mb-4 text-sm">
          <i class="mdi mdi-alert-circle mr-1"></i>{errors.general}
        </div>
      {/if}

      <form onsubmit={submit} class="space-y-6">

        <!-- ── Selección de plan ───────────────────────────────────────── -->
        <div>
          <h2 class="text-slate-300 text-sm font-semibold uppercase tracking-wider mb-3">
            <i class="mdi mdi-tag-outline mr-1"></i>Selecciona tu plan
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {#each plans as plan}
              <button
                type="button"
                onclick={() => selectedPlan = plan.slug}
                class="relative text-left p-4 rounded-xl border transition
                       {selectedPlan === plan.slug
                         ? 'border-blue-500 bg-blue-500/10'
                         : 'border-white/10 bg-white/5 hover:border-white/20'}"
              >
                {#if selectedPlan === plan.slug}
                  <i class="mdi mdi-check-circle absolute top-3 right-3 text-blue-400"></i>
                {/if}
                <div class="text-white font-semibold text-sm">{plan.name}</div>
                <div class="text-blue-400 font-bold text-lg mt-1">
                  {formatPrice(plan.price_monthly)}
                  <span class="text-slate-400 font-normal text-xs">/mes</span>
                </div>
                <div class="text-slate-400 text-xs mt-1 leading-snug">{plan.description}</div>
              </button>
            {/each}
          </div>
          {#if errors?.plan_slug}
            <p class="text-red-400 text-xs mt-1">{errors.plan_slug}</p>
          {/if}
        </div>

        <!-- ── Datos de la empresa ─────────────────────────────────────── -->
        <div>
          <h2 class="text-slate-300 text-sm font-semibold uppercase tracking-wider mb-3">
            <i class="mdi mdi-office-building-outline mr-1"></i>Datos de la empresa
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <!-- Nombre empresa -->
            <div class="sm:col-span-2">
              <label for="company_name" class="block text-slate-300 text-sm mb-1">Nombre de la empresa *</label>
              <input
                id="company_name"
                type="text"
                oninput={onCompanyNameInput}
                value={$form.company_name}
                class="w-full bg-white/5 border {errors.company_name ? 'border-red-500/50' : 'border-white/10'}
                       text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 transition"
                placeholder="Mi Empresa S.A.S."
              />
              {#if errors?.company_name}
                <p class="text-red-400 text-xs mt-1">{errors.company_name}</p>
              {/if}
            </div>

            <!-- NIT -->
            <div>
              <label for="company_nit" class="block text-slate-300 text-sm mb-1">NIT *</label>
              <input
                id="company_nit"
                type="text"
                bind:value={$form.company_nit}
                class="w-full bg-white/5 border {errors.company_nit ? 'border-red-500/50' : 'border-white/10'}
                       text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 transition"
                placeholder="900123456-1"
              />
              {#if errors?.company_nit}
                <p class="text-red-400 text-xs mt-1">{errors.company_nit}</p>
              {/if}
            </div>

            <!-- Slug / subdominio -->
            <div>
              <label for="company_slug" class="block text-slate-300 text-sm mb-1">Identificador (subdominio) *</label>
              <div class="relative">
                <input
                  id="company_slug"
                  type="text"
                  bind:value={$form.company_slug}
                  class="w-full bg-white/5 border {errors.company_slug ? 'border-red-500/50' : 'border-white/10'}
                         text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                         focus:outline-none focus:border-blue-500 transition"
                  placeholder="mi-empresa"
                />
              </div>
              <p class="text-slate-500 text-xs mt-1">
                Tu URL: <span class="text-blue-400">{$form.company_slug || 'mi-empresa'}.nextpossaas.test</span>
              </p>
              {#if errors?.company_slug}
                <p class="text-red-400 text-xs mt-1">{errors.company_slug}</p>
              {/if}
            </div>

          </div>
        </div>

        <!-- ── Administrador ──────────────────────────────────────────── -->
        <div>
          <h2 class="text-slate-300 text-sm font-semibold uppercase tracking-wider mb-3">
            <i class="mdi mdi-account-tie-outline mr-1"></i>Administrador de la empresa
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

            <div>
              <label for="admin_name" class="block text-slate-300 text-sm mb-1">Nombre completo *</label>
              <input
                id="admin_name"
                type="text"
                bind:value={$form.admin_name}
                class="w-full bg-white/5 border {errors.admin_name ? 'border-red-500/50' : 'border-white/10'}
                       text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 transition"
                placeholder="Juan Pérez"
              />
              {#if errors?.admin_name}
                <p class="text-red-400 text-xs mt-1">{errors.admin_name}</p>
              {/if}
            </div>

            <div>
              <label for="admin_email" class="block text-slate-300 text-sm mb-1">Correo electrónico *</label>
              <input
                id="admin_email"
                type="email"
                bind:value={$form.admin_email}
                class="w-full bg-white/5 border {errors.admin_email ? 'border-red-500/50' : 'border-white/10'}
                       text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 transition"
                placeholder="admin@empresa.com"
              />
              {#if errors?.admin_email}
                <p class="text-red-400 text-xs mt-1">{errors.admin_email}</p>
              {/if}
            </div>

            <div>
              <label for="admin_password" class="block text-slate-300 text-sm mb-1">Contraseña *</label>
              <input
                id="admin_password"
                type="password"
                bind:value={$form.admin_password}
                class="w-full bg-white/5 border {errors.admin_password ? 'border-red-500/50' : 'border-white/10'}
                       text-white placeholder-slate-500 rounded-lg px-4 py-2.5 text-sm
                       focus:outline-none focus:border-blue-500 transition"
                placeholder="Mínimo 8 caracteres"
              />
              {#if errors?.admin_password}
                <p class="text-red-400 text-xs mt-1">{errors.admin_password}</p>
              {/if}
            </div>

            <div>
              <label for="admin_password_confirmation" class="block text-slate-300 text-sm mb-1">Confirmar contraseña *</label>
              <input
                id="admin_password_confirmation"
                type="password"
                bind:value={$form.admin_password_confirmation}
                class="w-full bg-white/5 border border-white/10 text-white placeholder-slate-500
                       rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-blue-500 transition"
                placeholder="Repite la contraseña"
              />
            </div>

          </div>
        </div>

        <!-- Botón registrar -->
        <button
          type="submit"
          disabled={$form.processing}
          class="w-full bg-blue-600 hover:bg-blue-500 disabled:bg-blue-600/50 disabled:cursor-not-allowed
             text-white font-semibold rounded-lg py-3 text-sm transition flex items-center justify-center gap-2 cursor-pointer"
        >
          {#if $form.processing}
            <i class="mdi mdi-loading mdi-spin"></i> Creando empresa...
          {:else}
            <i class="mdi mdi-rocket-launch-outline"></i> Crear empresa y comenzar prueba gratuita
          {/if}
        </button>

        <p class="text-center text-slate-500 text-xs">
          ¿Ya tienes cuenta?
          <a href="http://tuempresa.nextpossaas-app.test/login" class="text-blue-400 hover:text-blue-300">
            Inicia sesión en tu empresa
          </a>
        </p>

      </form>
    </div>

    <p class="text-center text-slate-500 text-xs mt-6">
      &copy; {new Date().getFullYear()} NextPOS SaaS · Todos los derechos reservados
    </p>
  </div>
</div>
