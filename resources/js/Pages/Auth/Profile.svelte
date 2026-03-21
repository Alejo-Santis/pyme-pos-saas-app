<script>
  import { useForm } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { user, flash = {} } = $props()

  // ── Formulario de datos personales ───────────────────────────────────────
  const profileForm = useForm({
    name:  user.name  ?? '',
    email: user.email ?? '',
    phone: user.phone ?? '',
  })

  function saveProfile(e) {
    e.preventDefault()
    $profileForm.put('/profile', {
      preserveScroll: true,
      onSuccess: () => $profileForm.reset('password'),
    })
  }

  // ── Formulario de cambio de contraseña ────────────────────────────────────
  const passForm = useForm({
    current_password:       '',
    password:               '',
    password_confirmation:  '',
  })

  function savePassword(e) {
    e.preventDefault()
    $passForm.put('/profile/password', {
      preserveScroll: true,
      onSuccess: () => $passForm.reset(),
    })
  }

  // ── Cálculo de fortaleza de contraseña ────────────────────────────────────
  const passChecks = $derived({
    length:    $passForm.password.length >= 8,
    uppercase: /[A-Z]/.test($passForm.password),
    lowercase: /[a-z]/.test($passForm.password),
    number:    /[0-9]/.test($passForm.password),
  })
  const passScore = $derived(Object.values(passChecks).filter(Boolean).length)
  const passMatch = $derived(
    $passForm.password.length > 0 &&
    $passForm.password === $passForm.password_confirmation
  )

  const strengthLabels = ['', 'Muy débil', 'Débil', 'Regular', 'Fuerte', 'Muy fuerte']
  const strengthColors = ['', 'bg-red-400', 'bg-orange-400', 'bg-amber-400', 'bg-emerald-400', 'bg-emerald-500']

  // ── Iniciales del avatar ──────────────────────────────────────────────────
  const initials = $derived(
    (user.name ?? 'U')
      .split(' ')
      .slice(0, 2)
      .map(w => w[0]?.toUpperCase() ?? '')
      .join('')
  )

  // ── Toggle visibilidad contraseña ─────────────────────────────────────────
  let showCurrent = $state(false)
  let showNew     = $state(false)
  let showConfirm = $state(false)
</script>

<AppLayout>

  <!-- Encabezado -->
  <div class="mb-6">
    <h1 class="text-slate-800 text-xl font-bold">Mi Perfil</h1>
    <p class="text-slate-400 text-sm mt-0.5">Gestiona tu información personal y seguridad de acceso</p>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    <!-- ── Columna izquierda: avatar ────────────────────────────────────────── -->
    <div class="xl:col-span-1">
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 text-center">
        <div class="w-24 h-24 bg-primary rounded-full flex items-center justify-center mx-auto mb-4 shadow-md">
          <span class="text-white text-3xl font-bold">{initials}</span>
        </div>
        <h2 class="text-slate-800 font-bold text-lg">{user.name}</h2>
        <p class="text-slate-400 text-sm">{user.email}</p>
        {#if user.phone}
          <p class="text-slate-400 text-sm mt-0.5">{user.phone}</p>
        {/if}

        <div class="mt-5 pt-5 border-t border-slate-100 text-left space-y-2">
          <div class="flex items-center gap-2 text-sm text-slate-500">
            <i class="mdi mdi-shield-check-outline text-emerald-500 text-base"></i>
            <span>Cuenta activa</span>
          </div>
          <div class="flex items-center gap-2 text-sm text-slate-500">
            <i class="mdi mdi-identifier text-blue-400 text-base"></i>
            <span class="font-mono text-xs truncate">{user.id}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Columna derecha: formularios ─────────────────────────────────────── -->
    <div class="xl:col-span-2 space-y-5">

      <!-- ─ Datos personales ─────────────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
          <i class="mdi mdi-account-edit-outline text-primary text-lg"></i>
          <h3 class="text-slate-800 font-bold text-sm">Información personal</h3>
        </div>

        <form onsubmit={saveProfile} class="p-5 space-y-4">

          {#if flash?.success && !$passForm.recentlySuccessful}
            <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
              <i class="mdi mdi-check-circle text-emerald-500 text-base shrink-0"></i>
              <span>{flash.success}</span>
            </div>
          {/if}

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Nombre -->
            <div class="sm:col-span-2">
              <label class="block text-xs font-semibold text-slate-600 mb-1">
                Nombre completo <span class="text-red-400">*</span>
              </label>
              <input
                type="text"
                bind:value={$profileForm.name}
                class="w-full border rounded-lg px-3 py-2 text-sm text-slate-800 outline-none transition
                  {$profileForm.errors.name
                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-200'
                    : 'border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20'}"
                placeholder="Tu nombre completo"
              />
              {#if $profileForm.errors.name}
                <p class="text-red-500 text-xs mt-1">{$profileForm.errors.name}</p>
              {/if}
            </div>

            <!-- Email -->
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">
                Correo electrónico <span class="text-red-400">*</span>
              </label>
              <input
                type="email"
                bind:value={$profileForm.email}
                class="w-full border rounded-lg px-3 py-2 text-sm text-slate-800 outline-none transition
                  {$profileForm.errors.email
                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-200'
                    : 'border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20'}"
                placeholder="correo@empresa.com"
              />
              {#if $profileForm.errors.email}
                <p class="text-red-500 text-xs mt-1">{$profileForm.errors.email}</p>
              {/if}
            </div>

            <!-- Teléfono -->
            <div>
              <label class="block text-xs font-semibold text-slate-600 mb-1">
                Teléfono
              </label>
              <input
                type="tel"
                bind:value={$profileForm.phone}
                class="w-full border rounded-lg px-3 py-2 text-sm text-slate-800 outline-none transition
                  border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20"
                placeholder="+57 300 000 0000"
              />
            </div>
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              disabled={$profileForm.processing}
              class="flex items-center gap-2 bg-primary text-white px-5 py-2 rounded-lg text-sm font-semibold
                     hover:bg-primary/90 transition disabled:opacity-60 cursor-pointer"
            >
              {#if $profileForm.processing}
                <i class="mdi mdi-loading mdi-spin text-base"></i>
              {:else}
                <i class="mdi mdi-content-save-outline text-base"></i>
              {/if}
              Guardar cambios
            </button>
          </div>
        </form>
      </div>

      <!-- ─ Seguridad / Contraseña ───────────────────────────────────────────── -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
          <i class="mdi mdi-lock-outline text-amber-500 text-lg"></i>
          <h3 class="text-slate-800 font-bold text-sm">Cambiar contraseña</h3>
        </div>

        <form onsubmit={savePassword} class="p-5 space-y-4">

          {#if flash?.success && $passForm.recentlySuccessful}
            <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl px-4 py-3 text-sm">
              <i class="mdi mdi-check-circle text-emerald-500 text-base shrink-0"></i>
              <span>Contraseña actualizada correctamente.</span>
            </div>
          {/if}

          <!-- Contraseña actual -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">
              Contraseña actual <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <input
                type={showCurrent ? 'text' : 'password'}
                bind:value={$passForm.current_password}
                class="w-full border rounded-lg px-3 py-2 pr-10 text-sm text-slate-800 outline-none transition
                  {$passForm.errors.current_password
                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-200'
                    : 'border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20'}"
                placeholder="Tu contraseña actual"
                autocomplete="current-password"
              />
              <button
                type="button"
                onclick={() => showCurrent = !showCurrent}
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                aria-label={showCurrent ? 'Ocultar contraseña' : 'Mostrar contraseña'}
              >
                <i class="mdi {showCurrent ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-base"></i>
              </button>
            </div>
            {#if $passForm.errors.current_password}
              <p class="text-red-500 text-xs mt-1">{$passForm.errors.current_password}</p>
            {/if}
          </div>

          <!-- Nueva contraseña -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">
              Nueva contraseña <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <input
                type={showNew ? 'text' : 'password'}
                bind:value={$passForm.password}
                class="w-full border rounded-lg px-3 py-2 pr-10 text-sm text-slate-800 outline-none transition
                  {$passForm.errors.password
                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-200'
                    : 'border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20'}"
                placeholder="Mínimo 8 caracteres"
                autocomplete="new-password"
              />
              <button
                type="button"
                onclick={() => showNew = !showNew}
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                aria-label={showNew ? 'Ocultar contraseña' : 'Mostrar contraseña'}
              >
                <i class="mdi {showNew ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-base"></i>
              </button>
            </div>
            {#if $passForm.errors.password}
              <p class="text-red-500 text-xs mt-1">{$passForm.errors.password}</p>
            {/if}

            <!-- Barra de fortaleza -->
            {#if $passForm.password.length > 0}
              <div class="mt-2">
                <div class="flex gap-1 mb-1">
                  {#each [1,2,3,4] as i}
                    <div class="h-1.5 flex-1 rounded-full transition-all duration-300
                      {passScore >= i ? strengthColors[passScore] : 'bg-slate-200'}"></div>
                  {/each}
                </div>
                <p class="text-xs text-slate-500">{strengthLabels[passScore] ?? ''}</p>
              </div>

              <!-- Checklist -->
              <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
                {#each [
                  { key: 'length',    label: 'Mínimo 8 caracteres' },
                  { key: 'uppercase', label: 'Una mayúscula'        },
                  { key: 'lowercase', label: 'Una minúscula'        },
                  { key: 'number',    label: 'Un número'            },
                ] as c}
                  <li class="flex items-center gap-1.5 text-xs {passChecks[c.key] ? 'text-emerald-600' : 'text-slate-400'}">
                    <i class="mdi {passChecks[c.key] ? 'mdi-check-circle' : 'mdi-circle-outline'} text-sm"></i>
                    {c.label}
                  </li>
                {/each}
              </ul>
            {/if}
          </div>

          <!-- Confirmar contraseña -->
          <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">
              Confirmar nueva contraseña <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <input
                type={showConfirm ? 'text' : 'password'}
                bind:value={$passForm.password_confirmation}
                class="w-full border rounded-lg px-3 py-2 pr-10 text-sm text-slate-800 outline-none transition
                  {$passForm.password_confirmation.length > 0 && !passMatch
                    ? 'border-red-400 bg-red-50 focus:ring-2 focus:ring-red-200'
                    : passMatch
                      ? 'border-emerald-400 bg-emerald-50 focus:ring-2 focus:ring-emerald-200'
                      : 'border-slate-300 focus:border-primary focus:ring-2 focus:ring-primary/20'}"
                placeholder="Repite la nueva contraseña"
                autocomplete="new-password"
              />
              <button
                type="button"
                onclick={() => showConfirm = !showConfirm}
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                aria-label={showConfirm ? 'Ocultar contraseña' : 'Mostrar contraseña'}
              >
                <i class="mdi {showConfirm ? 'mdi-eye-off-outline' : 'mdi-eye-outline'} text-base"></i>
              </button>
            </div>
            {#if $passForm.password_confirmation.length > 0}
              <p class="text-xs mt-1 {passMatch ? 'text-emerald-600' : 'text-red-500'}">
                <i class="mdi {passMatch ? 'mdi-check-circle' : 'mdi-close-circle'} text-sm"></i>
                {passMatch ? 'Las contraseñas coinciden' : 'Las contraseñas no coinciden'}
              </p>
            {/if}
          </div>

          <div class="flex justify-end">
            <button
              type="submit"
              disabled={$passForm.processing || !passMatch || passScore < 2}
              class="flex items-center gap-2 bg-amber-500 text-white px-5 py-2 rounded-lg text-sm font-semibold
                     hover:bg-amber-600 transition disabled:opacity-60 cursor-pointer"
            >
              {#if $passForm.processing}
                <i class="mdi mdi-loading mdi-spin text-base"></i>
              {:else}
                <i class="mdi mdi-lock-reset text-base"></i>
              {/if}
              Cambiar contraseña
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

</AppLayout>
