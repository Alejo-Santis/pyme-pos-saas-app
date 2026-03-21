<script>
  import { useForm } from '@inertiajs/svelte'
  import AuthLayout from '@/Layouts/AuthLayout.svelte'

  let { token = '', email = '' } = $props()

  const form = useForm({
    token,
    email,
    password: '',
    password_confirmation: '',
  })

  let showPassword  = $state(false)
  let showConfirm   = $state(false)

  // Fortaleza
  const checks = $derived({
    length:    $form.password.length >= 8,
    uppercase: /[A-Z]/.test($form.password),
    lowercase: /[a-z]/.test($form.password),
    number:    /[0-9]/.test($form.password),
  })
  const score = $derived(Object.values(checks).filter(Boolean).length)
  const strength = $derived(
    score <= 1 ? { label: 'Muy débil',  color: 'bg-red-500',    text: 'text-red-500'    } :
    score === 2 ? { label: 'Débil',      color: 'bg-orange-400', text: 'text-orange-500' } :
    score === 3 ? { label: 'Regular',    color: 'bg-yellow-400', text: 'text-yellow-600' } :
                  { label: 'Buena',      color: 'bg-green-500',  text: 'text-green-600'  }
  )

  const match    = $derived($form.password_confirmation.length > 0 && $form.password === $form.password_confirmation)
  const mismatch = $derived($form.password_confirmation.length > 0 && $form.password !== $form.password_confirmation)

  function submit(e) {
    e.preventDefault()
    $form.post('/reset-password')
  }
</script>

<AuthLayout>

  <div class="text-center mb-6">
    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
      <i class="mdi mdi-lock-check text-blue-600 text-2xl"></i>
    </div>
    <h4 class="text-slate-800 text-xl font-bold">Nueva contraseña</h4>
    <p class="text-slate-500 text-sm mt-1">Elige una contraseña segura para tu cuenta.</p>
  </div>

  {#if $form.errors.email}
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
      <i class="mdi mdi-alert-circle text-red-500 shrink-0"></i>
      <span>{$form.errors.email}</span>
    </div>
  {/if}

  <form onsubmit={submit} class="space-y-4">

    <!-- Email (readonly, viene del link) -->
    <div>
      <label class="block text-slate-700 text-sm font-medium mb-1">Correo electrónico</label>
      <input type="email" value={$form.email} readonly
        class="w-full border border-slate-200 bg-slate-50 text-slate-500 rounded-lg px-4 py-2.5 text-sm" />
    </div>

    <!-- Nueva contraseña -->
    <div>
      <label for="password" class="block text-slate-700 text-sm font-medium mb-1">
        Nueva contraseña <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input id="password"
          type={showPassword ? 'text' : 'password'}
          bind:value={$form.password}
          class="w-full border {$form.errors.password ? 'border-red-400' : 'border-slate-300'}
                 text-slate-800 placeholder-slate-400 rounded-lg px-4 pr-11 py-2.5 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="Mínimo 8 caracteres" />
        <button type="button"
          onclick={() => showPassword = !showPassword}
          class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition cursor-pointer"
          tabindex="-1">
          <i class="mdi {showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-lg"></i>
        </button>
      </div>
      {#if $form.errors.password}
        <p class="text-red-500 text-xs mt-1">{$form.errors.password}</p>
      {/if}

      {#if $form.password.length > 0}
        <div class="mt-2 space-y-1.5">
          <div class="flex gap-1">
            {#each [1,2,3,4] as s}
              <div class="h-1.5 flex-1 rounded-full {score >= s ? strength.color : 'bg-slate-200'} transition-colors duration-300"></div>
            {/each}
          </div>
          <span class="text-xs font-semibold {strength.text}">{strength.label}</span>
          <div class="grid grid-cols-2 gap-0.5">
            {#each [
              { ok: checks.length,    label: 'Mín. 8 caracteres' },
              { ok: checks.uppercase, label: 'Mayúscula (A-Z)'   },
              { ok: checks.lowercase, label: 'Minúscula (a-z)'   },
              { ok: checks.number,    label: 'Número (0-9)'      },
            ] as r}
              <div class="flex items-center gap-1">
                <i class="mdi text-sm {r.ok ? 'mdi-check-circle text-green-500' : 'mdi-circle-outline text-slate-300'}"></i>
                <span class="text-xs {r.ok ? 'text-green-600' : 'text-slate-400'}">{r.label}</span>
              </div>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Confirmar contraseña -->
    <div>
      <label for="confirm" class="block text-slate-700 text-sm font-medium mb-1">
        Confirmar contraseña <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <input id="confirm"
          type={showConfirm ? 'text' : 'password'}
          bind:value={$form.password_confirmation}
          class="w-full border
                 {mismatch ? 'border-red-400' : match ? 'border-green-400' : 'border-slate-300'}
                 text-slate-800 placeholder-slate-400 rounded-lg px-4 pr-11 py-2.5 text-sm
                 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          placeholder="Repite la contraseña" />
        <button type="button"
          onclick={() => showConfirm = !showConfirm}
          class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition cursor-pointer"
          tabindex="-1">
          <i class="mdi {showConfirm ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-lg"></i>
        </button>
      </div>
      {#if match}
        <p class="text-green-600 text-xs mt-1 flex items-center gap-1">
          <i class="mdi mdi-check-circle"></i> Las contraseñas coinciden
        </p>
      {:else if mismatch}
        <p class="text-red-500 text-xs mt-1 flex items-center gap-1">
          <i class="mdi mdi-alert-circle"></i> Las contraseñas no coinciden
        </p>
      {/if}
    </div>

    <button type="submit"
      disabled={$form.processing || mismatch}
      class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed
             text-white font-semibold rounded-lg py-2.5 text-sm transition
             flex items-center justify-center gap-2 cursor-pointer">
      {#if $form.processing}
        <i class="mdi mdi-loading mdi-spin"></i> Guardando...
      {:else}
        <i class="mdi mdi-lock-check-outline"></i> Establecer nueva contraseña
      {/if}
    </button>

  </form>

</AuthLayout>
