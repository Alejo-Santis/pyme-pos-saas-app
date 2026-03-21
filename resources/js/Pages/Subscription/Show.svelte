<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { subscription = null, plans = [], tenant = null } = $props()

  const fmt = (n) => new Intl.NumberFormat('es-CO', {
    style: 'currency', currency: 'COP', maximumFractionDigits: 0
  }).format(n ?? 0)

  const statusLabel = {
    trial:     'Prueba gratuita',
    active:    'Activa',
    cancelled: 'Cancelada',
    expired:   'Vencida',
    past_due:  'Pago vencido',
  }

  const statusColor = {
    trial:     'bg-blue-50 text-blue-700 border-blue-200',
    active:    'bg-green-50 text-green-700 border-green-200',
    cancelled: 'bg-red-50 text-red-600 border-red-200',
    expired:   'bg-red-50 text-red-600 border-red-200',
    past_due:  'bg-amber-50 text-amber-700 border-amber-200',
  }

  const status = subscription?.status ?? 'expired'

  // Días restantes del trial
  const trialDaysLeft = $derived(() => {
    if (!tenant?.trial_ends_at) return 0
    const end  = new Date(tenant.trial_ends_at)
    const now  = new Date()
    const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
  })

  const trialPct = $derived(() => {
    if (!subscription?.plan?.trial_days) return 0
    return Math.min(100, Math.round((1 - trialDaysLeft() / (subscription.plan.trial_days || 15)) * 100))
  })
</script>

<AppLayout>
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Cabecera -->
    <div>
      <h1 class="text-xl font-bold text-slate-800">Mi suscripción</h1>
      <p class="text-sm text-slate-500 mt-0.5">Plan actual, estado y opciones de la cuenta</p>
    </div>

    <!-- Estado actual -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">

        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center">
            <i class="mdi mdi-crown-outline text-primary text-3xl"></i>
          </div>
          <div>
            <p class="text-xs text-slate-400 uppercase tracking-widest font-semibold">Plan actual</p>
            <h2 class="text-2xl font-bold text-slate-800 mt-0.5">
              {subscription?.plan?.name ?? 'Sin plan'}
            </h2>
            <span class="inline-block mt-1 text-xs font-semibold border rounded-full px-3 py-0.5
              {statusColor[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'}">
              {statusLabel[status] ?? status}
            </span>
          </div>
        </div>

        <div class="text-right">
          {#if subscription?.price && subscription.price > 0}
            <p class="text-3xl font-bold text-slate-800">{fmt(subscription.price)}</p>
            <p class="text-xs text-slate-400">/ {subscription.billing_period === 'yearly' ? 'año' : 'mes'}</p>
          {:else}
            <p class="text-2xl font-bold text-slate-400">Gratis</p>
          {/if}
        </div>

      </div>

      <!-- Barra de progreso del trial -->
      {#if status === 'trial' && tenant?.trial_ends_at}
        <div class="mt-6 pt-5 border-t border-slate-100">
          <div class="flex justify-between items-center mb-2">
            <p class="text-sm font-medium text-slate-600">Período de prueba</p>
            <p class="text-sm font-semibold {trialDaysLeft() <= 3 ? 'text-red-600' : 'text-slate-700'}">
              {trialDaysLeft()} {trialDaysLeft() === 1 ? 'día' : 'días'} restantes
            </p>
          </div>
          <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500
              {trialDaysLeft() <= 3 ? 'bg-red-500' : trialDaysLeft() <= 7 ? 'bg-amber-400' : 'bg-primary'}"
              style="width: {trialPct()}%">
            </div>
          </div>
          <p class="text-xs text-slate-400 mt-2">Vence el {tenant.trial_ends_at}</p>
        </div>
      {/if}

      <!-- Fechas -->
      {#if subscription}
        <div class="mt-5 pt-5 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-4">
          {#if subscription.starts_at}
            <div>
              <p class="text-xs text-slate-400">Inicio</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{subscription.starts_at}</p>
            </div>
          {/if}
          {#if subscription.ends_at}
            <div>
              <p class="text-xs text-slate-400">Renovación</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{subscription.ends_at}</p>
            </div>
          {/if}
          {#if subscription.trial_ends_at}
            <div>
              <p class="text-xs text-slate-400">Fin del trial</p>
              <p class="text-sm font-semibold text-slate-700 mt-0.5">{subscription.trial_ends_at}</p>
            </div>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Características del plan actual -->
    {#if subscription?.plan?.features?.length > 0}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
          <i class="mdi mdi-check-decagram-outline text-primary"></i>
          Incluido en tu plan
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          {#each subscription.plan.features as feature}
            <div class="flex items-center gap-2 text-sm text-slate-600">
              <i class="mdi mdi-check-circle text-green-500 shrink-0"></i>
              <span>{feature}</span>
            </div>
          {/each}
        </div>
      </div>
    {/if}

    <!-- Planes disponibles (para upgrade) -->
    {#if plans.length > 0}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-1 flex items-center gap-2">
          <i class="mdi mdi-lightning-bolt text-amber-500"></i>
          Planes disponibles
        </h3>
        <p class="text-xs text-slate-400 mb-5">Para cambiar de plan contacta a soporte o escríbenos.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          {#each plans as plan}
            {@const isCurrent = plan.slug === subscription?.plan?.slug}
            <div class="relative rounded-xl border-2 p-5 transition
              {isCurrent ? 'border-primary bg-primary/5' : 'border-slate-200'}">

              {#if isCurrent}
                <span class="absolute -top-3 left-4 bg-primary text-white text-xs font-bold px-3 py-0.5 rounded-full">
                  Plan actual
                </span>
              {/if}

              <p class="font-bold text-slate-800 text-base">{plan.name}</p>
              <p class="text-2xl font-extrabold text-primary mt-2">
                {fmt(plan.price_monthly)}
                <span class="text-slate-400 text-sm font-normal">/mes</span>
              </p>
              {#if plan.price_yearly}
                <p class="text-xs text-green-600 mt-0.5">
                  {fmt(plan.price_yearly)}/año — ahorra {fmt(plan.price_monthly * 12 - plan.price_yearly)}
                </p>
              {/if}
              <p class="text-xs text-slate-500 mt-2 leading-snug">{plan.description ?? ''}</p>

              {#if plan.features?.length > 0}
                <ul class="mt-3 space-y-1.5">
                  {#each plan.features.slice(0, 4) as feat}
                    <li class="flex items-center gap-1.5 text-xs text-slate-600">
                      <i class="mdi mdi-check text-green-500"></i>
                      {feat}
                    </li>
                  {/each}
                  {#if plan.features.length > 4}
                    <li class="text-xs text-slate-400">+{plan.features.length - 4} más…</li>
                  {/if}
                </ul>
              {/if}

              {#if !isCurrent}
                <a href="mailto:ventas@pymepossaas.co?subject=Upgrade a plan {plan.name}"
                  class="mt-4 flex items-center justify-center gap-1.5 w-full py-2 rounded-lg
                         bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition">
                  <i class="mdi mdi-arrow-up-circle-outline"></i>
                  Contratar
                </a>
              {/if}
            </div>
          {/each}
        </div>

        <p class="text-center text-xs text-slate-400 mt-5">
          ¿Necesitas más información?
          <a href="mailto:ventas@pymepossaas.co" class="text-primary hover:underline">Contáctanos</a>
        </p>
      </div>
    {/if}

    <!-- Aviso si está por vencer o vencido -->
    {#if !tenant?.can_operate}
      <div class="bg-red-50 border border-red-200 rounded-xl p-5 flex items-start gap-4">
        <i class="mdi mdi-alert-circle text-red-500 text-2xl shrink-0 mt-0.5"></i>
        <div>
          <p class="font-semibold text-red-800 text-sm">Suscripción inactiva</p>
          <p class="text-xs text-red-700 mt-1 leading-relaxed">
            Tu período de prueba ha vencido o la suscripción fue cancelada.
            Para seguir usando el sistema debes activar un plan.
            Contáctanos en <a href="mailto:ventas@pymepossaas.co" class="underline">ventas@pymepossaas.co</a>
          </p>
        </div>
      </div>
    {:else if status === 'trial' && trialDaysLeft() <= 5}
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-4">
        <i class="mdi mdi-clock-alert-outline text-amber-500 text-2xl shrink-0 mt-0.5"></i>
        <div>
          <p class="font-semibold text-amber-800 text-sm">Tu período de prueba está por vencer</p>
          <p class="text-xs text-amber-700 mt-1 leading-relaxed">
            Te quedan <strong>{trialDaysLeft()} días</strong>. Activa un plan para no perder el acceso.
          </p>
        </div>
      </div>
    {/if}

  </div>
</AppLayout>
