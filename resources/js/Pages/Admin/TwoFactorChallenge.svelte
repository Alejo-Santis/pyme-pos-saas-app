<script>
  import { useForm } from '@inertiajs/svelte'

  let { errors = {} } = $props()

  let useRecoveryCode = $state(false)

  const form = useForm({
    code: '',
    recovery_code: '',
  })

  function submit(e) {
    e.preventDefault()
    $form.post('/admin/two-factor')
  }

  function toggleRecovery() {
    useRecoveryCode = !useRecoveryCode
    $form.code = ''
    $form.recovery_code = ''
  }
</script>

<svelte:head><title>Verificación en dos pasos — PyME POS SaaS</title></svelte:head>

<div class="min-h-screen bg-gray-900 flex items-center justify-center p-4">
  <div class="absolute inset-0 pointer-events-none opacity-20"
       style="background: radial-gradient(ellipse at 50% 0%, #2563eb 0%, transparent 70%)"></div>

  <div class="relative w-full max-w-sm z-10">
    <div class="text-center mb-8">
      <div class="mx-auto mb-3 w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center">
        <i class="mdi mdi-shield-key-outline text-primary text-3xl"></i>
      </div>
      <h1 class="text-white text-xl font-bold">Verificación en dos pasos</h1>
      <p class="text-slate-400 text-sm mt-1">
        {useRecoveryCode ? 'Ingresa un código de recuperación' : 'Ingresa el código de tu app de autenticación'}
      </p>
    </div>

    <div class="bg-gray-800 rounded-2xl shadow-2xl border border-white/5 p-8">
      <form onsubmit={submit} class="space-y-5">
        {#if !useRecoveryCode}
          <div>
            <label class="block text-slate-300 text-sm font-medium mb-1.5">Código de 6 dígitos</label>
            <input
              type="text" inputmode="numeric" maxlength="6" autofocus
              bind:value={$form.code}
              placeholder="000000"
              class="w-full bg-gray-700 border rounded-lg px-4 py-2.5 text-center text-lg tracking-[0.5em] text-white
                     placeholder:text-slate-500 placeholder:tracking-normal focus:outline-none focus:ring-2
                     {errors.code ? 'border-red-500 focus:ring-red-500/30' : 'border-white/10 focus:ring-primary/40 focus:border-primary/60'}"
            />
            {#if errors.code}<p class="text-red-400 text-xs mt-1">{errors.code}</p>{/if}
          </div>
        {:else}
          <div>
            <label class="block text-slate-300 text-sm font-medium mb-1.5">Código de recuperación</label>
            <input
              type="text" autofocus
              bind:value={$form.recovery_code}
              placeholder="XXXX-XXXX"
              class="w-full bg-gray-700 border rounded-lg px-4 py-2.5 text-sm text-white
                     placeholder:text-slate-500 focus:outline-none focus:ring-2
                     {errors.code ? 'border-red-500 focus:ring-red-500/30' : 'border-white/10 focus:ring-primary/40 focus:border-primary/60'}"
            />
            {#if errors.code}<p class="text-red-400 text-xs mt-1">{errors.code}</p>{/if}
          </div>
        {/if}

        <button type="submit" disabled={$form.processing}
                class="w-full bg-primary hover:bg-primary-dark text-white font-semibold py-2.5 rounded-lg
                       text-sm transition-colors cursor-pointer disabled:opacity-60 flex items-center justify-center gap-2">
          {#if $form.processing}<i class="mdi mdi-loading mdi-spin text-base"></i>{/if}
          Verificar
        </button>

        <button type="button" onclick={toggleRecovery}
                class="w-full text-center text-xs text-slate-400 hover:text-slate-200 transition-colors">
          {useRecoveryCode ? 'Usar código de la app en su lugar' : '¿Perdiste el acceso? Usa un código de recuperación'}
        </button>
      </form>
    </div>
  </div>
</div>
