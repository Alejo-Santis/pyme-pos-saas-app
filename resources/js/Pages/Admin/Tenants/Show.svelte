<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router, inertia } from '@inertiajs/svelte'

  let { tenant = {}, subscriptions = [], plans = [], activeSubscription = null, supportSummary = {}, tenantUsers = [] } = $props()

  const today = new Date().toISOString().slice(0, 10)

  let selectedStatus = $state(tenant.status)
  let selectedPlan = $state(tenant.plan_id)
  let domain = $state(tenant.domain_input ?? '')
  let trialDays = $state(7)
  let notificationForm = $state({
    subject: '',
    message: '',
    action_label: '',
    action_url: tenant.login_url ?? '',
  })
  let impersonationForm = $state({
    tenant_user_id: tenantUsers[0]?.id ?? '',
    reason: '',
  })
  let subscriptionForm = $state({
    subscription_id: activeSubscription?.id ?? '',
    plan_id: activeSubscription?.plan_id ?? tenant.plan_id ?? plans[0]?.id ?? '',
    status: activeSubscription?.status ?? (tenant.status === 'trial' ? 'trial' : 'active'),
    billing_period: activeSubscription?.billing_period ?? 'monthly',
    price: activeSubscription?.price ?? plans.find((plan) => plan.id === tenant.plan_id)?.price_monthly ?? 0,
    trial_ends_at: activeSubscription?.trial_ends_at ?? tenant.trial_ends_at_input ?? '',
    starts_at: activeSubscription?.starts_at ?? today,
    ends_at: activeSubscription?.ends_at ?? '',
  })

  const selectedSubscriptionPlan = $derived(
    plans.find((plan) => plan.id === subscriptionForm.plan_id)
  )

  function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '—'

    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      maximumFractionDigits: 0,
    }).format(Number(value))
  }

  function updateStatus(status = selectedStatus) {
    selectedStatus = status
    router.patch(`/admin/tenants/${tenant.id}/status`, { status }, { preserveScroll: true })
  }

  function updatePlan() {
    router.patch(`/admin/tenants/${tenant.id}/plan`, { plan_id: selectedPlan }, { preserveScroll: true })
  }

  function updateDomain() {
    router.patch(`/admin/tenants/${tenant.id}/domain`, { domain }, { preserveScroll: true })
  }

  function syncSubscriptionPrice() {
    if (!selectedSubscriptionPlan) return

    subscriptionForm.price = subscriptionForm.billing_period === 'yearly'
      ? selectedSubscriptionPlan.price_yearly
      : selectedSubscriptionPlan.price_monthly
  }

  function updateSubscription() {
    router.patch(`/admin/tenants/${tenant.id}/subscription`, subscriptionForm, { preserveScroll: true })
  }

  function extendTrial() {
    router.post(`/admin/tenants/${tenant.id}/extend-trial`, { days: trialDays }, { preserveScroll: true })
  }

  function sendNotification() {
    router.post(`/admin/tenants/${tenant.id}/notification`, notificationForm, { preserveScroll: true })
  }

  function runTechnicalAction(action) {
    router.post(`/admin/tenants/${tenant.id}/technical-action`, { action }, { preserveScroll: true })
  }

  function impersonateTenant() {
    router.post(`/admin/tenants/${tenant.id}/impersonate`, impersonationForm, { preserveScroll: true })
  }

  const statusColor = {
    active: 'bg-emerald-100 text-emerald-700',
    trial: 'bg-blue-100 text-blue-700',
    suspended: 'bg-amber-100 text-amber-700',
    cancelled: 'bg-red-100 text-red-700',
  }

  const statusLabel = {
    active: 'Activo',
    trial: 'Trial',
    suspended: 'Suspendido',
    cancelled: 'Cancelado',
  }

  const subStatusColor = {
    active: 'bg-emerald-50 text-emerald-700',
    trial: 'bg-blue-50 text-blue-700',
    past_due: 'bg-amber-50 text-amber-700',
    cancelled: 'bg-red-50 text-red-700',
    expired: 'bg-slate-100 text-slate-500',
  }

  const subStatusLabel = {
    active: 'Activa',
    trial: 'Trial',
    past_due: 'En mora',
    cancelled: 'Cancelada',
    expired: 'Expirada',
  }
</script>

<AdminLayout>
  <div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-start gap-4">
        <a use:inertia href="/admin/tenants" class="mt-1 text-slate-400 hover:text-slate-600 transition" title="Volver">
          <i class="mdi mdi-arrow-left text-xl"></i>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-bold text-slate-800">{tenant.name}</h2>
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold {statusColor[tenant.status] ?? 'bg-slate-100 text-slate-600'}">
              {statusLabel[tenant.status] ?? tenant.status}
            </span>
          </div>
          <p class="text-slate-500 text-sm mt-1">{tenant.email} · {tenant.domain}</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        {#if tenant.login_url}
          <a
            href={tenant.login_url}
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-primary/30 hover:text-primary transition"
          >
            <i class="mdi mdi-login-variant"></i>
            Abrir login tenant
          </a>
        {/if}
        <button onclick={() => updateStatus('active')} class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 transition">
          <i class="mdi mdi-check-circle-outline"></i>
          Activar
        </button>
        <button onclick={() => updateStatus('suspended')} class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600 transition">
          <i class="mdi mdi-pause-circle-outline"></i>
          Suspender
        </button>
        <button onclick={() => updateStatus('cancelled')} class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 transition">
          <i class="mdi mdi-close-circle-outline"></i>
          Cancelar
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <div class="space-y-4">
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Información general</h3>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-slate-500">ID</dt>
              <dd class="text-slate-700 font-mono text-xs truncate">{tenant.id}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-slate-500">Dominio</dt>
              <dd class="text-slate-700 font-mono text-xs truncate">{tenant.domain}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Plan actual</dt>
              <dd class="text-slate-700 font-medium">{tenant.plan}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Trial hasta</dt>
              <dd class="text-slate-700">{tenant.trial_ends_at ?? '—'}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Registrado</dt>
              <dd class="text-slate-700">{tenant.created_at}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Actualizado</dt>
              <dd class="text-slate-700">{tenant.updated_at}</dd>
            </div>
          </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Dominio</h3>
          <label for="tenant-domain" class="block text-xs font-medium text-slate-600 mb-1">Dominio principal</label>
          <input
            id="tenant-domain"
            type="text"
            bind:value={domain}
            placeholder="empresa.pymepossaas-app.test"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          />
          <button
            onclick={updateDomain}
            class="w-full bg-slate-700 text-white py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition"
          >
            Actualizar dominio
          </button>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Estado del tenant</h3>
          <select
            bind:value={selectedStatus}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            <option value="trial">Trial</option>
            <option value="active">Activo</option>
            <option value="suspended">Suspendido</option>
            <option value="cancelled">Cancelado</option>
          </select>
          <button
            onclick={() => updateStatus()}
            class="w-full bg-primary text-white py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition"
          >
            Actualizar estado
          </button>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-3">Plan del tenant</h3>
          <select
            bind:value={selectedPlan}
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm mb-3 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
          >
            {#each plans as plan}
              <option value={plan.id}>{plan.name} · {formatCurrency(plan.price_monthly)}/mes</option>
            {/each}
          </select>
          <button
            onclick={updatePlan}
            class="w-full bg-slate-700 text-white py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition"
          >
            Cambiar plan
          </button>
        </div>
      </div>

      <div class="xl:col-span-2 space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-medium uppercase text-slate-400">Suscripción</p>
            <p class="mt-2 text-lg font-bold text-slate-800">{activeSubscription?.plan ?? tenant.plan}</p>
            <span class="mt-2 inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {subStatusColor[activeSubscription?.status] ?? 'bg-slate-100 text-slate-500'}">
              {subStatusLabel[activeSubscription?.status] ?? 'Sin estado'}
            </span>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-medium uppercase text-slate-400">Precio actual</p>
            <p class="mt-2 text-lg font-bold text-slate-800">{formatCurrency(activeSubscription?.price)}</p>
            <p class="text-xs text-slate-400 mt-1">{activeSubscription?.billing_period === 'yearly' ? 'Facturación anual' : 'Facturación mensual'}</p>
          </div>
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <p class="text-xs font-medium uppercase text-slate-400">Fin de periodo</p>
            <p class="mt-2 text-lg font-bold text-slate-800">{activeSubscription?.ends_at ?? activeSubscription?.trial_ends_at ?? tenant.trial_ends_at_input ?? '—'}</p>
            <p class="text-xs text-slate-400 mt-1">Fecha editable desde suscripción</p>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="text-sm font-semibold text-slate-700">Soporte rápido</h3>
              <p class="text-xs text-slate-400 mt-0.5">Lectura básica del schema del tenant.</p>
            </div>
            {#if supportSummary.error}
              <span class="text-xs px-2 py-1 rounded-full bg-red-50 text-red-700 font-semibold">Error schema</span>
            {/if}
          </div>

          {#if supportSummary.error}
            <p class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{supportSummary.error}</p>
          {:else}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
              <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs text-slate-400">Usuarios</p>
                <p class="text-xl font-bold text-slate-800">{supportSummary.users_count ?? '—'}</p>
              </div>
              <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs text-slate-400">Activos</p>
                <p class="text-xl font-bold text-emerald-600">{supportSummary.active_users_count ?? '—'}</p>
              </div>
              <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs text-slate-400">Documentos</p>
                <p class="text-xl font-bold text-slate-800">{supportSummary.documents_count ?? '—'}</p>
              </div>
              <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs text-slate-400">Empresas</p>
                <p class="text-xl font-bold text-slate-800">{supportSummary.companies_count ?? '—'}</p>
              </div>
              <div class="rounded-lg border border-slate-100 p-3">
                <p class="text-xs text-slate-400">Errores API</p>
                <p class="text-xl font-bold text-red-600">{supportSummary.api_errors_count ?? '—'}</p>
              </div>
            </div>
          {/if}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Entrar como soporte</h3>
            <p class="text-xs text-slate-400 mb-4">Genera un acceso temporal de 5 minutos y consumo único.</p>

            {#if tenantUsers.length === 0}
              <p class="text-sm text-slate-400 rounded-lg border border-slate-100 px-3 py-4 text-center">No hay usuarios activos para impersonar.</p>
            {:else}
              <div class="space-y-3">
                <div>
                  <label for="impersonation-user" class="block text-xs font-medium text-slate-600 mb-1">Usuario</label>
                  <select
                    id="impersonation-user"
                    bind:value={impersonationForm.tenant_user_id}
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                  >
                    {#each tenantUsers as user}
                      <option value={user.id}>{user.name} · {user.email}</option>
                    {/each}
                  </select>
                </div>

                <div>
                  <label for="impersonation-reason" class="block text-xs font-medium text-slate-600 mb-1">Motivo</label>
                  <textarea
                    id="impersonation-reason"
                    bind:value={impersonationForm.reason}
                    rows="3"
                    placeholder="Ej: revisar configuración de facturación electrónica"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                  ></textarea>
                </div>

                <button onclick={impersonateTenant} class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition">
                  <i class="mdi mdi-account-switch-outline"></i>
                  Entrar al tenant
                </button>
              </div>
            {/if}
          </div>

          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Notificar tenant</h3>
            <p class="text-xs text-slate-400 mb-4">Envía un aviso al correo principal de la empresa.</p>

            <div class="space-y-3">
              <div>
                <label for="notification-subject" class="block text-xs font-medium text-slate-600 mb-1">Asunto</label>
                <input
                  id="notification-subject"
                  bind:value={notificationForm.subject}
                  type="text"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                />
              </div>
              <div>
                <label for="notification-message" class="block text-xs font-medium text-slate-600 mb-1">Mensaje</label>
                <textarea
                  id="notification-message"
                  bind:value={notificationForm.message}
                  rows="4"
                  class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                ></textarea>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label for="notification-action-label" class="block text-xs font-medium text-slate-600 mb-1">Texto botón</label>
                  <input
                    id="notification-action-label"
                    bind:value={notificationForm.action_label}
                    type="text"
                    placeholder="Ir al portal"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                  />
                </div>
                <div>
                  <label for="notification-action-url" class="block text-xs font-medium text-slate-600 mb-1">URL botón</label>
                  <input
                    id="notification-action-url"
                    bind:value={notificationForm.action_url}
                    type="url"
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                  />
                </div>
              </div>
              <button onclick={sendNotification} class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark transition">
                <i class="mdi mdi-email-send-outline"></i>
                Enviar notificación
              </button>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5 lg:col-span-2">
            <h3 class="text-sm font-semibold text-slate-700 mb-1">Herramientas técnicas</h3>
            <p class="text-xs text-slate-400 mb-4">Acciones controladas para reparar o actualizar este tenant.</p>

            <div class="space-y-3">
              <button onclick={() => runTechnicalAction('migrate')} class="w-full flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-left hover:border-primary/40 hover:bg-blue-50/40 transition">
                <span>
                  <span class="block text-sm font-semibold text-slate-700">Ejecutar migraciones</span>
                  <span class="block text-xs text-slate-400 mt-0.5">Corre tenants:migrate solo para este tenant.</span>
                </span>
                <i class="mdi mdi-database-sync-outline text-primary text-xl"></i>
              </button>

              <button onclick={() => runTechnicalAction('seed_defaults')} class="w-full flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-left hover:border-primary/40 hover:bg-blue-50/40 transition">
                <span>
                  <span class="block text-sm font-semibold text-slate-700">Sembrar defaults</span>
                  <span class="block text-xs text-slate-400 mt-0.5">Reintenta resoluciones y terceros base.</span>
                </span>
                <i class="mdi mdi-seed-outline text-primary text-xl"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
              <h3 class="text-sm font-semibold text-slate-700">Suscripción y trial</h3>
              <p class="text-xs text-slate-400 mt-0.5">Ajusta el plan contratado, ciclo, precio y vigencia.</p>
            </div>
            <div class="flex items-center gap-2">
              <input
                type="number"
                min="1"
                max="365"
                bind:value={trialDays}
                class="w-20 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
              <button onclick={extendTrial} class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
                <i class="mdi mdi-calendar-plus-outline"></i>
                Extender trial
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="subscription-plan" class="block text-xs font-medium text-slate-600 mb-1">Plan</label>
              <select
                id="subscription-plan"
                bind:value={subscriptionForm.plan_id}
                onchange={syncSubscriptionPrice}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              >
                {#each plans as plan}
                  <option value={plan.id}>{plan.name}</option>
                {/each}
              </select>
            </div>

            <div>
              <label for="subscription-status" class="block text-xs font-medium text-slate-600 mb-1">Estado suscripción</label>
              <select
                id="subscription-status"
                bind:value={subscriptionForm.status}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              >
                <option value="trial">Trial</option>
                <option value="active">Activa</option>
                <option value="past_due">En mora</option>
                <option value="cancelled">Cancelada</option>
                <option value="expired">Expirada</option>
              </select>
            </div>

            <div>
              <label for="subscription-billing" class="block text-xs font-medium text-slate-600 mb-1">Ciclo</label>
              <select
                id="subscription-billing"
                bind:value={subscriptionForm.billing_period}
                onchange={syncSubscriptionPrice}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              >
                <option value="monthly">Mensual</option>
                <option value="yearly">Anual</option>
              </select>
            </div>

            <div>
              <label for="subscription-price" class="block text-xs font-medium text-slate-600 mb-1">Precio</label>
              <input
                id="subscription-price"
                type="number"
                min="0"
                step="100"
                bind:value={subscriptionForm.price}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
            </div>

            <div>
              <label for="subscription-starts" class="block text-xs font-medium text-slate-600 mb-1">Inicio</label>
              <input
                id="subscription-starts"
                type="date"
                bind:value={subscriptionForm.starts_at}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
            </div>

            <div>
              <label for="subscription-ends" class="block text-xs font-medium text-slate-600 mb-1">Fin</label>
              <input
                id="subscription-ends"
                type="date"
                bind:value={subscriptionForm.ends_at}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
            </div>

            <div>
              <label for="subscription-trial" class="block text-xs font-medium text-slate-600 mb-1">Trial hasta</label>
              <input
                id="subscription-trial"
                type="date"
                bind:value={subscriptionForm.trial_ends_at}
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
              />
            </div>
          </div>

          <div class="flex justify-end mt-5">
            <button onclick={updateSubscription} class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark transition">
              <i class="mdi mdi-content-save-outline"></i>
              Guardar suscripción
            </button>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">Historial de suscripciones</h3>

          {#if subscriptions.length === 0}
            <p class="text-slate-400 text-sm text-center py-8">Sin suscripciones registradas</p>
          {:else}
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-slate-50">
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Plan</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Estado</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Precio</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Inicio</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Fin</th>
                    <th class="text-left text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2">Trial</th>
                  </tr>
                </thead>
                <tbody>
                  {#each subscriptions as sub}
                    <tr class="border-t border-slate-50 hover:bg-slate-50 transition-colors">
                      <td class="px-3 py-2.5 font-medium text-slate-700">{sub.plan}</td>
                      <td class="px-3 py-2.5">
                        <span class="text-xs px-2 py-1 rounded-full font-semibold {subStatusColor[sub.status] ?? 'bg-slate-100 text-slate-500'}">
                          {subStatusLabel[sub.status] ?? sub.status}
                        </span>
                      </td>
                      <td class="px-3 py-2.5 text-slate-600">{formatCurrency(sub.price)}</td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.starts_at ?? '—'}</td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.ends_at ?? '—'}</td>
                      <td class="px-3 py-2.5 text-slate-500 text-xs">{sub.trial_ends ?? '—'}</td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </div>
      </div>
    </div>
  </div>
</AdminLayout>
