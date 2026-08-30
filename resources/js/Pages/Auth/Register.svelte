<script>
  import { useForm } from '@inertiajs/svelte'
  import BrandLogo from '@/Components/BrandLogo.svelte'

  let {
    plans = [],
    errors = {},
    tenantDomainMode = 'subdomain',
    tenantDomainSuffix = window.location.hostname,
    centralDomain = '',
  } = $props()

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

  $effect(() => {
    if (!selectedPlan && defaultPlanSlug) selectedPlan = defaultPlanSlug
  })

  $effect(() => { $form.plan_slug = selectedPlan })

  // ── Visibilidad de contraseñas ─────────────────────────────────────────────
  let showPassword        = $state(false)
  let showConfirmPassword = $state(false)

  // ── Fortaleza de contraseña ────────────────────────────────────────────────
  const checks = $derived({
    length:    $form.admin_password.length >= 8,
    uppercase: /[A-Z]/.test($form.admin_password),
    lowercase: /[a-z]/.test($form.admin_password),
    number:    /[0-9]/.test($form.admin_password),
    symbol:    /[^A-Za-z0-9]/.test($form.admin_password),
  })

  const strengthScore = $derived(
    Object.values(checks).filter(Boolean).length
  )

  const strength = $derived(
    strengthScore <= 1 ? { label: 'Muy débil',  color: 'bg-red-500',    text: 'text-red-500'    } :
    strengthScore === 2 ? { label: 'Débil',      color: 'bg-orange-400', text: 'text-orange-500' } :
    strengthScore === 3 ? { label: 'Regular',    color: 'bg-yellow-400', text: 'text-yellow-600' } :
    strengthScore === 4 ? { label: 'Buena',      color: 'bg-blue-500',   text: 'text-blue-600'   } :
                          { label: 'Muy fuerte', color: 'bg-green-500',  text: 'text-green-600'  }
  )

  const passwordsMatch = $derived(
    $form.admin_password_confirmation.length > 0 &&
    $form.admin_password === $form.admin_password_confirmation
  )

  const passwordsMismatch = $derived(
    $form.admin_password_confirmation.length > 0 &&
    $form.admin_password !== $form.admin_password_confirmation
  )

  // ── Slug y form ────────────────────────────────────────────────────────────
  function generateSlug(name) {
    return name
      .toLowerCase()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .trim().replace(/\s+/g, '-').replace(/-+/g, '-')
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

  const goToLanding = () => {
    if (!centralDomain) return
    window.location.href = `${window.location.protocol}//${centralDomain}/`
  }

  let loginSlug = $state('')

  function goToLogin() {
    const slug = loginSlug.trim()
    if (!slug) return
    const base = tenantDomainMode === 'suffix' ? tenantDomainSuffix : window.location.hostname
    const port = window.location.port ? `:${window.location.port}` : ''
    window.location.href = `${window.location.protocol}//${slug}.${base}${port}/login`
  }

  function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(price)
  }

  const tenantDomainPreview = $derived(
    $form.company_slug
      ? `${$form.company_slug}.${tenantDomainSuffix}`
      : `empresa.${tenantDomainSuffix}`
  )
</script>

<div class="relative min-h-screen bg-slate-100 flex items-center justify-center p-4 overflow-hidden">

  <!-- Fondo SVG -->
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

  <div class="relative w-full max-w-2xl z-10 my-6">

    <!-- Header -->
    <div class="bg-blue-600 rounded-t-2xl py-5 text-center shadow-md">
      <BrandLogo tone="light" size="md" class="justify-center" />
      <p class="text-blue-200 text-xs mt-1.5">Crea tu empresa y empieza a facturar electrónicamente</p>
    </div>

    <!-- Body -->
    <div class="bg-white rounded-b-2xl shadow-xl px-8 py-7">

      <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h1 class="text-slate-800 text-lg font-bold">Registro de empresa</h1>
            <p class="text-slate-500 text-sm mt-0.5">
              Comienza con {plans[0]?.trial_days ?? 15} días de prueba gratuita. Sin tarjeta de crédito.
            </p>
          </div>
          {#if centralDomain}
            <button
              type="button"
              onclick={goToLanding}
              class="text-slate-600 hover:text-blue-600 transition p-1 rounded-lg hover:bg-slate-100"
              title="Volver a PymePOS"
            >
              <i class="mdi mdi-close text-xl"></i>
            </button>
          {/if}
        </div>
      </div>

      {#if errors?.general}
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
          <i class="mdi mdi-alert-circle text-red-500 shrink-0"></i>
          <span>{errors.general}</span>
        </div>
      {/if}

      <form onsubmit={submit} class="space-y-6">

        <!-- Plan -->
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

        <!-- Datos empresa -->
        <div>
          <h2 class="text-slate-600 text-xs font-semibold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <i class="mdi mdi-office-building-outline text-blue-500"></i>Datos de la empresa
          </h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

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

            <div>
              <label for="company_slug" class="block text-slate-700 text-sm font-medium mb-1">Identificador de empresa *</label>
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
                Tu URL: <span class="text-blue-600 font-medium">{tenantDomainPreview}</span>
              </p>
              {#if errors?.company_slug}
                <p class="text-red-500 text-xs mt-1">{errors.company_slug}</p>
              {/if}
            </div>

          </div>
        </div>

        <!-- Administrador -->
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

            <!-- Contraseña con toggle + medidor -->
            <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">

              <!-- Campo contraseña -->
              <div>
                <label for="admin_password" class="block text-slate-700 text-sm font-medium mb-1">Contraseña *</label>
                <div class="relative">
                  <input
                    id="admin_password"
                    type={showPassword ? 'text' : 'password'}
                    bind:value={$form.admin_password}
                    class="w-full border {errors.admin_password ? 'border-red-400' : 'border-slate-300'}
                           text-slate-800 placeholder-slate-400 rounded-lg pl-4 pr-10 py-2.5 text-sm
                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                    placeholder="Mínimo 8 caracteres"
                  />
                  <button
                    type="button"
                    onclick={() => showPassword = !showPassword}
                    aria-label={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition cursor-pointer"
                    tabindex="-1"
                  >
                    <i class="mdi {showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-lg"></i>
                  </button>
                </div>
                {#if errors?.admin_password}
                  <p class="text-red-500 text-xs mt-1">{errors.admin_password}</p>
                {/if}

                <!-- Medidor de fortaleza (solo cuando hay texto) -->
                {#if $form.admin_password.length > 0}
                  <div class="mt-2.5 space-y-2">

                    <!-- Barras de fortaleza -->
                    <div class="flex gap-1">
                      {#each [1, 2, 3, 4, 5] as step}
                        <div class="h-1.5 flex-1 rounded-full transition-colors duration-300
                          {strengthScore >= step ? strength.color : 'bg-slate-200'}">
                        </div>
                      {/each}
                    </div>

                    <!-- Label fortaleza -->
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-semibold {strength.text}">{strength.label}</span>
                    </div>

                    <!-- Checklist de requisitos -->
                    <div class="grid grid-cols-1 gap-1 pt-0.5">
                      {#each [
                        { ok: checks.length,    label: 'Mínimo 8 caracteres'       },
                        { ok: checks.uppercase, label: 'Una mayúscula (A-Z)'        },
                        { ok: checks.lowercase, label: 'Una minúscula (a-z)'        },
                        { ok: checks.number,    label: 'Un número (0-9)'            },
                        { ok: checks.symbol,    label: 'Un símbolo (!@#$...)'       },
                      ] as req}
                        <div class="flex items-center gap-1.5">
                          <i class="mdi text-sm {req.ok ? 'mdi-check-circle text-green-500' : 'mdi-circle-outline text-slate-300'}"></i>
                          <span class="text-xs {req.ok ? 'text-green-600' : 'text-slate-400'}">{req.label}</span>
                        </div>
                      {/each}
                    </div>

                  </div>
                {/if}
              </div>

              <!-- Campo confirmar contraseña -->
              <div>
                <label for="admin_password_confirmation" class="block text-slate-700 text-sm font-medium mb-1">Confirmar contraseña *</label>
                <div class="relative">
                  <input
                    id="admin_password_confirmation"
                    type={showConfirmPassword ? 'text' : 'password'}
                    bind:value={$form.admin_password_confirmation}
                    class="w-full border
                           {passwordsMismatch ? 'border-red-400' : passwordsMatch ? 'border-green-400' : 'border-slate-300'}
                           text-slate-800 placeholder-slate-400 rounded-lg pl-4 pr-10 py-2.5 text-sm
                           focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                    placeholder="Repite la contraseña"
                  />
                  <button
                    type="button"
                    onclick={() => showConfirmPassword = !showConfirmPassword}
                    aria-label={showConfirmPassword ? 'Ocultar confirmación' : 'Mostrar confirmación'}
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition cursor-pointer"
                    tabindex="-1"
                  >
                    <i class="mdi {showConfirmPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-lg"></i>
                  </button>
                </div>

                <!-- Feedback de coincidencia -->
                {#if passwordsMatch}
                  <p class="text-green-600 text-xs mt-1.5 flex items-center gap-1">
                    <i class="mdi mdi-check-circle"></i> Las contraseñas coinciden
                  </p>
                {:else if passwordsMismatch}
                  <p class="text-red-500 text-xs mt-1.5 flex items-center gap-1">
                    <i class="mdi mdi-alert-circle"></i> Las contraseñas no coinciden
                  </p>
                {/if}
              </div>

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
              <span class="text-slate-400 truncate">.{tenantDomainSuffix}</span>
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
      &copy; {new Date().getFullYear()} PyME POS SaaS · Todos los derechos reservados
    </p>
  </div>
</div>
