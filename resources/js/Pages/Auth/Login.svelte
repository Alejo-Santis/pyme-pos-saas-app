<script>
  import { useForm } from '@inertiajs/svelte'
  import AuthLayout from '@/Layouts/AuthLayout.svelte'

  let { errors = {}, flash = {} } = $props()

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  })

  function submit(e) {
    e.preventDefault()
    $form.post('/login', {
      onFinish: () => $form.reset('password'),
    })
  }
</script>

<AuthLayout>

  <h1 class="text-white text-2xl font-semibold text-center mb-1">Bienvenido</h1>
  <p class="text-slate-400 text-sm text-center mb-6">Inicia sesión en tu empresa</p>

  <!-- Mensaje de éxito (ej: redirigido desde registro) -->
  {#if flash?.success}
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg p-3 mb-4 text-sm">
      <i class="mdi mdi-check-circle mr-1"></i>{flash.success}
    </div>
  {/if}

  <!-- Error general -->
  {#if errors?.email}
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg p-3 mb-4 text-sm">
      <i class="mdi mdi-alert-circle mr-1"></i>{errors.email}
    </div>
  {/if}

  <form onsubmit={submit} class="space-y-4">

    <!-- Email -->
    <div>
      <label for="email" class="block text-slate-300 text-sm font-medium mb-1">
        Correo electrónico
      </label>
      <div class="relative">
        <i class="mdi mdi-email-outline absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input
          id="email"
          type="email"
          autocomplete="email"
          bind:value={$form.email}
          class="w-full bg-white/5 border {errors.email ? 'border-red-500/50' : 'border-white/10'}
                 text-white placeholder-slate-500 rounded-lg pl-10 pr-4 py-2.5 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="tu@empresa.com"
        />
      </div>
    </div>

    <!-- Contraseña -->
    <div>
      <label for="password" class="block text-slate-300 text-sm font-medium mb-1">
        Contraseña
      </label>
      <div class="relative">
        <i class="mdi mdi-lock-outline absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input
          id="password"
          type="password"
          autocomplete="current-password"
          bind:value={$form.password}
          class="w-full bg-white/5 border border-white/10 text-white placeholder-slate-500
                 rounded-lg pl-10 pr-4 py-2.5 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="••••••••"
        />
      </div>
    </div>

    <!-- Recordarme -->
    <div class="flex items-center justify-between">
      <label class="flex items-center gap-2 cursor-pointer">
        <input
          type="checkbox"
          bind:checked={$form.remember}
          class="rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500"
        />
        <span class="text-slate-400 text-sm">Recordarme</span>
      </label>
      <span class="text-blue-400 text-sm cursor-pointer hover:text-blue-300 transition">
        ¿Olvidaste tu contraseña?
      </span>\n    </div>

    <!-- Botón -->
    <button
      type="submit"
      disabled={$form.processing}
      class="w-full bg-blue-600 hover:bg-blue-500 disabled:bg-blue-600/50 disabled:cursor-not-allowed
             text-white font-semibold rounded-lg py-2.5 text-sm transition flex items-center justify-center gap-2 cursor-pointer"
    >
      {#if $form.processing}
        <i class="mdi mdi-loading mdi-spin"></i> Ingresando...
      {:else}
        <i class="mdi mdi-login"></i> Ingresar
      {/if}
    </button>

  </form>

</AuthLayout>
