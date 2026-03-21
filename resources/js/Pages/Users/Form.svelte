<script>
  import { useForm, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { user = null, roles = [] } = $props()

  const isEdit = $derived(!!user)

  const form = useForm({
    name:      user?.name      ?? '',
    email:     user?.email     ?? '',
    phone:     user?.phone     ?? '',
    password:  '',
    role:      user?.role      ?? '',
    is_active: user?.is_active ?? true,
  })

  // ── Visibilidad contraseña ─────────────────────────────────────────────────
  let showPassword = $state(false)

  // ── Fortaleza contraseña ───────────────────────────────────────────────────
  const checks = $derived({
    length:    $form.password.length >= 8,
    uppercase: /[A-Z]/.test($form.password),
    lowercase: /[a-z]/.test($form.password),
    number:    /[0-9]/.test($form.password),
  })

  const strengthScore = $derived(Object.values(checks).filter(Boolean).length)

  const strength = $derived(
    strengthScore <= 1 ? { label: 'Muy débil',  color: 'bg-red-500',    text: 'text-red-500'    } :
    strengthScore === 2 ? { label: 'Débil',      color: 'bg-orange-400', text: 'text-orange-500' } :
    strengthScore === 3 ? { label: 'Regular',    color: 'bg-yellow-400', text: 'text-yellow-600' } :
                          { label: 'Buena',      color: 'bg-green-500',  text: 'text-green-600'  }
  )

  const roleLabels = {
    admin:       'Administrador — acceso total',
    contador:    'Contador — facturación y contabilidad',
    vendedor:    'Vendedor — ventas y POS',
    cajero:      'Cajero — solo POS',
    almacenista: 'Almacenista — inventario y compras',
  }

  function submit(e) {
    e.preventDefault()
    if (isEdit) {
      $form.put(`/users/${user.id}`, { preserveScroll: true })
    } else {
      $form.post('/users')
    }
  }
</script>

<AppLayout>
  <div class="max-w-2xl mx-auto space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center gap-3">
      <a use:inertia href="/users"
        class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-slate-800">
          {isEdit ? 'Editar usuario' : 'Nuevo usuario'}
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {isEdit ? `Modificando a ${user.name}` : 'Crear un nuevo acceso al sistema'}
        </p>
      </div>
    </div>

    <form onsubmit={submit} class="space-y-5">

      <!-- Datos personales -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-widest flex items-center gap-2">
          <i class="mdi mdi-account-outline text-primary"></i>
          Datos personales
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

          <!-- Nombre -->
          <div class="sm:col-span-2">
            <label for="name" class="block text-sm font-medium text-slate-700 mb-1">
              Nombre completo <span class="text-red-500">*</span>
            </label>
            <input id="name" type="text" bind:value={$form.name}
              class="w-full border {$form.errors.name ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2
                     focus:ring-primary/30 focus:border-primary transition"
              placeholder="Juan Pérez" />
            {#if $form.errors.name}
              <p class="text-red-500 text-xs mt-1">{$form.errors.name}</p>
            {/if}
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
              Correo electrónico <span class="text-red-500">*</span>
            </label>
            <input id="email" type="email" bind:value={$form.email}
              class="w-full border {$form.errors.email ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2
                     focus:ring-primary/30 focus:border-primary transition"
              placeholder="usuario@empresa.com" />
            {#if $form.errors.email}
              <p class="text-red-500 text-xs mt-1">{$form.errors.email}</p>
            {/if}
          </div>

          <!-- Teléfono -->
          <div>
            <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
            <input id="phone" type="text" bind:value={$form.phone}
              class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm
                     focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
              placeholder="3001234567" />
          </div>

        </div>
      </div>

      <!-- Contraseña -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-widest flex items-center gap-2">
          <i class="mdi mdi-lock-outline text-primary"></i>
          Contraseña
          {#if isEdit}
            <span class="text-xs font-normal text-slate-400 normal-case">(dejar vacío para no cambiar)</span>
          {/if}
        </h2>

        <div>
          <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
            {isEdit ? 'Nueva contraseña' : 'Contraseña'} {#if !isEdit}<span class="text-red-500">*</span>{/if}
          </label>
          <div class="relative">
            <input id="password"
              type={showPassword ? 'text' : 'password'}
              bind:value={$form.password}
              class="w-full border {$form.errors.password ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-4 pr-11 py-2.5 text-sm focus:outline-none focus:ring-2
                     focus:ring-primary/30 focus:border-primary transition"
              placeholder={isEdit ? 'Dejar vacío para no cambiar' : 'Mínimo 8 caracteres'} />
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

          <!-- Medidor de fortaleza -->
          {#if $form.password.length > 0}
            <div class="mt-3 space-y-2">
              <div class="flex gap-1">
                {#each [1, 2, 3, 4] as step}
                  <div class="h-1.5 flex-1 rounded-full transition-colors duration-300
                    {strengthScore >= step ? strength.color : 'bg-slate-200'}"></div>
                {/each}
              </div>
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold {strength.text}">{strength.label}</span>
              </div>
              <div class="grid grid-cols-2 gap-1">
                {#each [
                  { ok: checks.length,    label: 'Mín. 8 caracteres' },
                  { ok: checks.uppercase, label: 'Mayúscula (A-Z)'   },
                  { ok: checks.lowercase, label: 'Minúscula (a-z)'   },
                  { ok: checks.number,    label: 'Número (0-9)'      },
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
      </div>

      <!-- Rol y estado -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-widest flex items-center gap-2">
          <i class="mdi mdi-shield-account-outline text-primary"></i>
          Rol y permisos
        </h2>

        <!-- Selector de rol tipo tarjeta -->
        <div class="space-y-2">
          <label class="block text-sm font-medium text-slate-700">
            Rol <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-1 gap-2">
            {#each roles as r}
              <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition
                {$form.role === r.name
                  ? 'border-primary bg-primary/5'
                  : 'border-slate-200 hover:border-slate-300'}">
                <input type="radio" name="role" value={r.name} bind:group={$form.role} class="sr-only" />
                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0
                  {$form.role === r.name ? 'border-primary' : 'border-slate-300'}">
                  {#if $form.role === r.name}
                    <div class="w-2 h-2 rounded-full bg-primary"></div>
                  {/if}
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700 capitalize">{r.name}</p>
                  <p class="text-xs text-slate-400">{roleLabels[r.name] ?? ''}</p>
                </div>
              </label>
            {/each}
          </div>
          {#if $form.errors.role}
            <p class="text-red-500 text-xs mt-1">{$form.errors.role}</p>
          {/if}
        </div>

        <!-- Estado activo/inactivo -->
        {#if isEdit}
          <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50 border border-slate-200">
            <div>
              <p class="text-sm font-medium text-slate-700">Estado del usuario</p>
              <p class="text-xs text-slate-400">Los usuarios inactivos no pueden iniciar sesión</p>
            </div>
            <button type="button"
              onclick={() => $form.is_active = !$form.is_active}
              class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent
                     transition-colors duration-200 ease-in-out focus:outline-none
                     {$form.is_active ? 'bg-primary' : 'bg-slate-300'}">
              <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow
                           transition duration-200 ease-in-out
                           {$form.is_active ? 'translate-x-5' : 'translate-x-0'}"></span>
            </button>
          </div>
        {/if}
      </div>

      <!-- Acciones -->
      <div class="flex items-center justify-end gap-3">
        <a use:inertia href="/users"
          class="px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-300
                 rounded-lg hover:bg-slate-50 transition">
          Cancelar
        </a>
        <button type="submit" disabled={$form.processing}
          class="px-6 py-2.5 text-sm font-semibold text-white bg-primary rounded-lg
                 hover:bg-primary-dark disabled:opacity-60 disabled:cursor-not-allowed
                 flex items-center gap-2 transition cursor-pointer">
          {#if $form.processing}
            <i class="mdi mdi-loading mdi-spin text-base"></i> Guardando...
          {:else}
            <i class="mdi mdi-content-save-outline text-base"></i>
            {isEdit ? 'Guardar cambios' : 'Crear usuario'}
          {/if}
        </button>
      </div>

    </form>
  </div>
</AppLayout>
