<script>
  import { useForm } from '@inertiajs/svelte'
  import BrandMark from '@/Components/UI/BrandMark.svelte'

  let { errors = {} } = $props()

  const form = useForm({
    email:    '',
    password: '',
    remember: false,
  })

  function submit(e) {
    e.preventDefault()
    $form.post('/admin/login', {
      onFinish: () => $form.reset('password'),
    })
  }
</script>

<svelte:head><title>Admin — PyME POS SaaS</title></svelte:head>

<div class="min-h-screen bg-gray-900 flex items-center justify-center p-4">

  <!-- Fondo sutil -->
  <div class="absolute inset-0 pointer-events-none opacity-20"
       style="background: radial-gradient(ellipse at 50% 0%, #2563eb 0%, transparent 70%)"></div>

  <div class="relative w-full max-w-sm z-10">

    <!-- Logo -->
    <div class="text-center mb-8">
      <div class="mx-auto mb-3 w-fit">
        <BrandMark size={56} rounded="rounded-2xl" />
      </div>
      <h1 class="text-white text-xl font-bold">Panel de Administración</h1>
      <p class="text-slate-400 text-sm mt-1">PyME POS SaaS</p>
    </div>

    <!-- Card login -->
    <div class="bg-gray-800 rounded-2xl shadow-2xl border border-white/5 p-8">

      <form onsubmit={submit} class="space-y-5">

        <!-- Email -->
        <div>
          <label class="block text-slate-300 text-sm font-medium mb-1.5">
            Correo electrónico
          </label>
          <div class="relative">
            <i class="mdi mdi-email-outline absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg"></i>
            <input
              type="email"
              bind:value={$form.email}
              placeholder="admin@nextpossaas.com"
              autocomplete="email"
              onkeydown={(e) => e.key === 'Enter' && submit(e)}
              class="w-full bg-gray-700 border rounded-lg pl-10 pr-4 py-2.5 text-sm text-white
                     placeholder:text-slate-500 focus:outline-none focus:ring-2
                     {errors.email ? 'border-red-500 focus:ring-red-500/30' : 'border-white/10 focus:ring-primary/40 focus:border-primary/60'}"
            />
          </div>
          {#if errors.email}
            <p class="text-red-400 text-xs mt-1">{errors.email}</p>
          {/if}
        </div>

        <!-- Password -->
        <div>
          <label class="block text-slate-300 text-sm font-medium mb-1.5">
            Contraseña
          </label>
          <div class="relative">
            <i class="mdi mdi-lock-outline absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg"></i>
            <input
              type="password"
              bind:value={$form.password}
              placeholder="••••••••"
              autocomplete="current-password"
              class="w-full bg-gray-700 border rounded-lg pl-10 pr-4 py-2.5 text-sm text-white
                     placeholder:text-slate-500 focus:outline-none focus:ring-2
                     {errors.password ? 'border-red-500 focus:ring-red-500/30' : 'border-white/10 focus:ring-primary/40 focus:border-primary/60'}"
            />
          </div>
          {#if errors.password}
            <p class="text-red-400 text-xs mt-1">{errors.password}</p>
          {/if}
        </div>

        <!-- Submit -->
        <button
          type="submit"
          disabled={$form.processing}
          class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 rounded-lg
                 text-sm transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2"
        >
          {#if $form.processing}
            <i class="mdi mdi-loading mdi-spin text-base"></i>
          {/if}
          Ingresar al panel
        </button>

      </form>
    </div>

    <p class="text-center text-slate-600 text-xs mt-6">
      Acceso restringido — Solo personal autorizado
    </p>
  </div>
</div>
