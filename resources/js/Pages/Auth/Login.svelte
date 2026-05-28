<script>
  import { useForm } from '@inertiajs/svelte'
  import AuthLayout from '@/Layouts/AuthLayout.svelte'

  let { errors = {}, flash = {}, justRegistered = false, centralDomain = '' } = $props()

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  })

  let showPassword = $state(false)

  const submit = (e) => {
    e.preventDefault()
    $form.post('/login', {
      onFinish: () => $form.reset('password'),
    })
  }

  const goToLanding = () => {
    if (!centralDomain) return
    window.location.href = `${window.location.protocol}//${centralDomain}/`
  }
</script>

<AuthLayout>

  <!-- Botón volver a landing -->
  {#if centralDomain}
    <button
      type="button"
      onclick={goToLanding}
      class="mb-4 inline-flex items-center gap-1.5 text-slate-600 hover:text-blue-600 text-sm transition"
    >
      <i class="mdi mdi-arrow-left"></i>
      Volver a PymePOS
    </button>
  {/if}

  <!-- Encabezado -->
  <div class="text-center mb-6">
    <h4 class="text-slate-800 text-xl font-bold">Ingresar</h4>
    <p class="text-slate-500 text-sm mt-1">Software administrativo y contable.</p>
  </div>

  <!-- Banner de empresa creada exitosamente (viene de ?registered=1) -->
  {#if justRegistered}
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4 mb-5">
      <div class="flex items-start gap-3">
        <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
          <i class="mdi mdi-check-bold text-emerald-600 text-base"></i>
        </div>
        <div>
          <p class="font-semibold text-sm">¡Empresa creada exitosamente!</p>
          <p class="text-xs text-emerald-700 mt-0.5">
            Tu período de prueba gratuita está activo. Inicia sesión con las credenciales que registraste.
          </p>
        </div>
      </div>
    </div>
  {/if}

  <!-- Flash de éxito genérico -->
  {#if flash?.success}
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
      <i class="mdi mdi-check-circle text-green-500 shrink-0"></i>
      <span>{flash.success}</span>
    </div>
  {/if}

  <!-- Error de credenciales -->
  {#if errors?.email}
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
      <i class="mdi mdi-alert-circle text-red-500 shrink-0"></i>
      <span>{errors.email}</span>
    </div>
  {/if}

  <form onsubmit={submit} class="space-y-4">

    <!-- Correo -->
    <div>
      <label for="email" class="block text-slate-700 text-sm font-medium mb-1">
        Correo Electrónico
      </label>
      <input
        id="email"
        type="email"
        autocomplete="email"
        bind:value={$form.email}
        class="w-full border {errors.email ? 'border-red-400' : 'border-slate-300'}
               text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
               focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
        placeholder="Ingrese su correo"
      />
    </div>

    <!-- Contraseña -->
    <div>
      <div class="flex items-center justify-between mb-1">
        <label for="password" class="text-slate-700 text-sm font-medium">Contraseña</label>
        <a href="/forgot-password" class="text-blue-600 text-xs hover:text-blue-700 transition">
          Olvidé mi contraseña
        </a>
      </div>
      <div class="relative">
        <input
          id="password"
          type={showPassword ? 'text' : 'password'}
          autocomplete="current-password"
          bind:value={$form.password}
          onkeydown={(e) => e.key === 'Enter' && submit(e)}
          class="w-full border border-slate-300 text-slate-800 placeholder-slate-400
                 rounded-lg px-4 py-2.5 pr-11 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="Ingrese su contraseña"
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
      {#if errors?.password}
        <p class="text-red-500 text-xs mt-1">{errors.password}</p>
      {/if}
    </div>

    <!-- Botón ingresar -->
    <div class="pt-1 text-center">
      <button
        type="submit"
        disabled={$form.processing}
        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed
               text-white font-semibold rounded-lg px-8 py-2.5 text-sm transition
               flex items-center justify-center gap-2 cursor-pointer mx-auto"
      >
        {#if $form.processing}
          <i class="mdi mdi-loading mdi-spin"></i> Ingresando...
        {:else}
          <i class="mdi mdi-account-arrow-right"></i> Ingresar
        {/if}
      </button>
    </div>

  </form>

</AuthLayout>
