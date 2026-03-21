<script>
  import { useForm } from '@inertiajs/svelte'
  import AuthLayout from '@/Layouts/AuthLayout.svelte'

  let { flash = {} } = $props()

  const form = useForm({ email: '' })

  let sent = $state(false)

  function submit(e) {
    e.preventDefault()
    $form.post('/forgot-password', {
      onSuccess: () => { sent = true },
    })
  }
</script>

<AuthLayout>

  <div class="text-center mb-6">
    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
      <i class="mdi mdi-lock-reset text-blue-600 text-2xl"></i>
    </div>
    <h4 class="text-slate-800 text-xl font-bold">Recuperar contraseña</h4>
    <p class="text-slate-500 text-sm mt-1">Ingresa tu correo y te enviaremos un enlace.</p>
  </div>

  {#if sent || flash?.success}
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg p-4 mb-5">
      <div class="flex items-start gap-3">
        <i class="mdi mdi-check-circle text-emerald-600 text-xl shrink-0 mt-0.5"></i>
        <div>
          <p class="font-semibold text-sm">Correo enviado</p>
          <p class="text-xs text-emerald-700 mt-0.5">
            Si el correo está registrado, recibirás un enlace para restablecer tu contraseña en los próximos minutos.
          </p>
        </div>
      </div>
    </div>

    <div class="text-center">
      <a href="/login" class="text-blue-600 text-sm hover:underline">
        <i class="mdi mdi-arrow-left text-sm"></i> Volver al inicio de sesión
      </a>
    </div>

  {:else}
    <form onsubmit={submit} class="space-y-4">

      <div>
        <label for="email" class="block text-slate-700 text-sm font-medium mb-1">
          Correo electrónico
        </label>
        <input
          id="email"
          type="email"
          autocomplete="email"
          bind:value={$form.email}
          class="w-full border {$form.errors.email ? 'border-red-400' : 'border-slate-300'}
                 text-slate-800 placeholder-slate-400 rounded-lg px-4 py-2.5 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="correo@empresa.com"
        />
        {#if $form.errors.email}
          <p class="text-red-500 text-xs mt-1">{$form.errors.email}</p>
        {/if}
      </div>

      <button
        type="submit"
        disabled={$form.processing}
        class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed
               text-white font-semibold rounded-lg py-2.5 text-sm transition
               flex items-center justify-center gap-2 cursor-pointer">
        {#if $form.processing}
          <i class="mdi mdi-loading mdi-spin"></i> Enviando...
        {:else}
          <i class="mdi mdi-email-fast-outline"></i> Enviar enlace de recuperación
        {/if}
      </button>

      <div class="text-center pt-1">
        <a href="/login" class="text-slate-500 text-sm hover:text-slate-700 transition">
          <i class="mdi mdi-arrow-left text-sm"></i> Volver al inicio de sesión
        </a>
      </div>

    </form>
  {/if}

</AuthLayout>
