<script>
  import { page, useForm, router } from '@inertiajs/svelte'
  import AdminLayout from '@/Layouts/AdminLayout.svelte'

  let { twoFactorEnabled, twoFactorConfirmedAt, setup } = $props()

  const flash = $derived($page.props.flash ?? {})
  const recoveryCodes = $derived($page.props.recoveryCodes)

  let showDisableConfirm = $state(false)

  const confirmForm = useForm({ code: '' })
  const disableForm = useForm({ password: '' })

  function startSetup() {
    router.post('/admin/security/enable')
  }

  function confirmSetup(e) {
    e.preventDefault()
    $confirmForm.post('/admin/security/confirm', {
      onSuccess: () => $confirmForm.reset(),
    })
  }

  function disable(e) {
    e.preventDefault()
    $disableForm.post('/admin/security/disable', {
      onSuccess: () => { showDisableConfirm = false; $disableForm.reset() },
    })
  }

  function fmtDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric' })
  }
</script>

<AdminLayout>
  <div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-800 mb-1">Seguridad</h1>
    <p class="text-sm text-slate-500 mb-6">Autenticación de dos factores para tu cuenta del panel</p>

    {#if flash.success}
      <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3 mb-6">
        {flash.success}
      </div>
    {/if}

    {#if recoveryCodes}
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
        <h2 class="font-semibold text-amber-900 mb-1">Guarda tus códigos de recuperación</h2>
        <p class="text-sm text-amber-800 mb-3">
          Cada uno sirve una sola vez para entrar si pierdes acceso a tu app de autenticación.
          No se volverán a mostrar.
        </p>
        <div class="grid grid-cols-2 gap-2 font-mono text-sm">
          {#each recoveryCodes as code}
            <div class="bg-white border border-amber-200 rounded px-3 py-1.5 text-center">{code}</div>
          {/each}
        </div>
      </div>
    {/if}

    <div class="bg-white rounded-xl border border-slate-200 p-6">
      {#if twoFactorEnabled}
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
            <i class="mdi mdi-shield-check text-green-600 text-xl"></i>
          </div>
          <div>
            <p class="font-semibold text-slate-800">2FA activado</p>
            <p class="text-xs text-slate-500">Desde {fmtDate(twoFactorConfirmedAt)}</p>
          </div>
        </div>

        {#if !showDisableConfirm}
          <button onclick={() => showDisableConfirm = true}
                  class="text-sm text-red-600 hover:text-red-800 font-medium">
            Desactivar 2FA
          </button>
        {:else}
          <form onsubmit={disable} class="space-y-3 mt-2 border-t border-slate-100 pt-4">
            <label class="block text-sm font-medium text-slate-600">Confirma tu contraseña para desactivar</label>
            <input type="password" bind:value={$disableForm.password}
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500"/>
            {#if $disableForm.errors.password}
              <p class="text-sm text-red-600">{$disableForm.errors.password}</p>
            {/if}
            <div class="flex gap-2">
              <button type="submit" disabled={$disableForm.processing}
                      class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg">
                Desactivar
              </button>
              <button type="button" onclick={() => showDisableConfirm = false}
                      class="text-sm text-slate-500 hover:text-slate-700 px-3 py-2">
                Cancelar
              </button>
            </div>
          </form>
        {/if}

      {:else if setup}
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
            <i class="mdi mdi-shield-alert-outline text-amber-600 text-xl"></i>
          </div>
          <p class="font-semibold text-slate-800">Termina de configurar 2FA</p>
        </div>

        <p class="text-sm text-slate-600 mb-4">
          Escanea este código con Google Authenticator, Authy o cualquier app TOTP, luego ingresa el código de 6 dígitos.
        </p>

        <div class="flex justify-center mb-4">
          {@html setup.qrCode}
        </div>

        <p class="text-xs text-slate-500 text-center mb-4">
          ¿No puedes escanear? Ingresa este código manualmente:
          <code class="bg-slate-100 px-2 py-0.5 rounded font-mono">{setup.secret}</code>
        </p>

        <form onsubmit={confirmSetup} class="space-y-3">
          <input type="text" inputmode="numeric" maxlength="6" bind:value={$confirmForm.code}
                 placeholder="000000"
                 class="w-full border border-slate-300 rounded-lg px-4 py-2.5 text-center text-lg tracking-[0.5em] focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          {#if $confirmForm.errors.code}
            <p class="text-sm text-red-600 text-center">{$confirmForm.errors.code}</p>
          {/if}
          <button type="submit" disabled={$confirmForm.processing}
                  class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium py-2.5 rounded-lg">
            Confirmar y activar
          </button>
        </form>

      {:else}
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center">
            <i class="mdi mdi-shield-off-outline text-slate-500 text-xl"></i>
          </div>
          <div>
            <p class="font-semibold text-slate-800">2FA desactivado</p>
            <p class="text-xs text-slate-500">Tu cuenta solo pide correo y contraseña</p>
          </div>
        </div>

        <button onclick={startSetup}
                class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
          Activar autenticación de dos factores
        </button>
      {/if}
    </div>
  </div>
</AdminLayout>
