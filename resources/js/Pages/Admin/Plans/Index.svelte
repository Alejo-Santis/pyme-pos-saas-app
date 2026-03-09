<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router } from '@inertiajs/svelte'

  let { plans = [] } = $props()

  const featureLabel = {
    dian_fe:      'FE DIAN',
    pos:          'POS',
    accounting:   'Contabilidad',
    inventory:    'Inventario',
    payroll:      'Nómina',
    multi_branch: 'Multi-sede',
    api_access:   'API',
  }

  function toggle(id) {
    router.patch(`/admin/plans/${id}/toggle`)
  }
</script>

<AdminLayout>
  <div class="space-y-5">

    <div>
      <h2 class="text-xl font-bold text-slate-800">Planes</h2>
      <p class="text-slate-500 text-sm">Gestión de planes de suscripción de la plataforma</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
      {#each plans as plan}
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden
          {plan.is_active ? 'border-slate-100' : 'border-slate-100 opacity-60'}">

          <!-- Cabecera -->
          <div class="p-5 border-b border-slate-50">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-bold text-slate-800 text-base">{plan.name}</h3>
                <p class="text-slate-400 text-xs font-mono">{plan.slug}</p>
              </div>
              <!-- Toggle activo/inactivo -->
              <button
                onclick={() => toggle(plan.id)}
                class="relative w-10 h-5 rounded-full transition-colors cursor-pointer
                  {plan.is_active ? 'bg-primary' : 'bg-slate-200'}"
                title="{plan.is_active ? 'Desactivar' : 'Activar'} plan"
              >
                <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-all
                  {plan.is_active ? 'left-5' : 'left-0.5'}"></span>
              </button>
            </div>

            {#if plan.description}
              <p class="text-slate-500 text-xs mt-2">{plan.description}</p>
            {/if}
          </div>

          <!-- Precios -->
          <div class="px-5 py-4 flex gap-4 border-b border-slate-50">
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Mensual</p>
              <p class="text-2xl font-bold text-slate-800">
                ${parseFloat(plan.price_monthly ?? 0).toLocaleString('es-CO')}
              </p>
            </div>
            <div class="border-l border-slate-100 pl-4">
              <p class="text-xs text-slate-400 mb-0.5">Anual</p>
              <p class="text-2xl font-bold text-slate-800">
                ${parseFloat(plan.price_yearly ?? 0).toLocaleString('es-CO')}
              </p>
            </div>
          </div>

          <!-- Límites -->
          <div class="px-5 py-3 space-y-1.5 border-b border-slate-50">
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Usuarios</span>
              <span class="font-medium text-slate-700">{plan.max_users ?? '∞'}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Productos</span>
              <span class="font-medium text-slate-700">{plan.max_products ?? '∞'}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Facturas/mes</span>
              <span class="font-medium text-slate-700">{plan.max_invoices_monthly ?? '∞'}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-slate-500">Trial</span>
              <span class="font-medium text-slate-700">{plan.trial_days} días</span>
            </div>
          </div>

          <!-- Features -->
          <div class="px-5 py-3">
            <p class="text-xs text-slate-400 mb-2">Módulos incluidos</p>
            <div class="flex flex-wrap gap-1.5">
              {#each Object.entries(plan.features ?? {}) as [key, enabled]}
                {#if enabled}
                  <span class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium">
                    {featureLabel[key] ?? key}
                  </span>
                {/if}
              {/each}
            </div>
          </div>

          <!-- Footer: empresas usando este plan -->
          <div class="px-5 py-3 bg-slate-50 border-t border-slate-100">
            <p class="text-xs text-slate-500">
              <i class="mdi mdi-domain text-slate-400 mr-1"></i>
              <strong class="text-slate-700">{plan.tenants_count}</strong> empresa{plan.tenants_count !== 1 ? 's' : ''} en este plan
            </p>
          </div>

        </div>
      {/each}

      {#if plans.length === 0}
        <div class="col-span-3 bg-white rounded-xl border border-slate-100 p-12 text-center">
          <i class="mdi mdi-layers-off text-4xl text-slate-300 block mb-3"></i>
          <p class="text-slate-400">No hay planes creados aún</p>
        </div>
      {/if}
    </div>

  </div>
</AdminLayout>
