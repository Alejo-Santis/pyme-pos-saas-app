<script>
  import AdminLayout from '@/Layouts/AdminLayout.svelte'
  import { router } from '@inertiajs/svelte'
  import ConfirmModal from '@/Components/UI/ConfirmModal.svelte'
  import { toast } from '@/Stores/toast.svelte.js'

  let { plans = [], allFeatures = [] } = $props()

  const fmt = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })

  const featureLabel = {
    dian_fe:      { label: 'FE DIAN',      icon: 'mdi-file-certificate-outline' },
    pos:          { label: 'POS',           icon: 'mdi-point-of-sale' },
    accounting:   { label: 'Contabilidad',  icon: 'mdi-calculator-variant-outline' },
    inventory:    { label: 'Inventario',    icon: 'mdi-package-variant-closed' },
    payroll:      { label: 'Nómina',        icon: 'mdi-account-cash-outline' },
    multi_branch: { label: 'Multi-sede',    icon: 'mdi-domain' },
    api_access:   { label: 'API',           icon: 'mdi-api' },
  }

  // ── Modal ────────────────────────────────────────────────────────────────
  let showModal   = $state(false)
  let editingPlan = $state(null)
  let submitting  = $state(false)
  let errors      = $state({})

  const emptyForm = () => ({
    name:                 '',
    description:          '',
    price_monthly:        0,
    price_yearly:         0,
    max_users:            '',
    max_products:         '',
    max_invoices_monthly: '',
    trial_days:           14,
    is_active:            true,
    features:             Object.fromEntries(allFeatures.map(k => [k, false])),
  })

  let form = $state(emptyForm())

  function openCreate() {
    editingPlan = null
    form        = emptyForm()
    errors      = {}
    showModal   = true
  }

  function openEdit(plan) {
    editingPlan = plan
    form = {
      name:                 plan.name,
      description:          plan.description ?? '',
      price_monthly:        plan.price_monthly,
      price_yearly:         plan.price_yearly,
      max_users:            plan.max_users ?? '',
      max_products:         plan.max_products ?? '',
      max_invoices_monthly: plan.max_invoices_monthly ?? '',
      trial_days:           plan.trial_days,
      is_active:            plan.is_active,
      features:             { ...Object.fromEntries(allFeatures.map(k => [k, false])), ...(plan.features ?? {}) },
    }
    errors    = {}
    showModal = true
  }

  function closeModal() {
    showModal   = false
    editingPlan = null
    errors      = {}
  }

  function submit() {
    submitting = true
    errors     = {}
    const url    = editingPlan ? `/admin/plans/${editingPlan.id}` : '/admin/plans'
    const method = editingPlan ? 'put' : 'post'
    router[method](url, form, {
      onError:   (e) => { errors = e; submitting = false },
      onSuccess: () => { closeModal(); submitting = false },
      onFinish:  () => { submitting = false },
    })
  }

  // ── Eliminar ──────────────────────────────────────────────────────────────
  let deleting = $state(null)
  let confirmDeletePlan = $state({ open: false, plan: null })

  function destroy(plan) {
    if (plan.tenants_count > 0) {
      toast.error(`No se puede eliminar: ${plan.tenants_count} empresa(s) usan este plan.`)
      return
    }
    confirmDeletePlan = { open: true, plan }
  }

  function executeDeletePlan() {
    deleting = confirmDeletePlan.plan.id
    router.delete(`/admin/plans/${confirmDeletePlan.plan.id}`, {
      preserveScroll: true,
      onFinish: () => { deleting = null },
    })
  }

  function toggle(id) {
    router.patch(`/admin/plans/${id}/toggle`, {}, { preserveScroll: true })
  }
</script>

<AdminLayout>
  <div class="space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-slate-800">Planes de suscripción</h2>
        <p class="text-slate-500 text-sm mt-0.5">Gestiona los planes disponibles para las empresas clientes</p>
      </div>
      <button onclick={openCreate}
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm rounded-xl font-medium hover:bg-blue-700 transition cursor-pointer shadow-sm">
        <i class="mdi mdi-plus text-base"></i>
        Nuevo plan
      </button>
    </div>

    <!-- Tarjetas -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
      {#each plans as plan}
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden flex flex-col transition hover:shadow-md
          {plan.is_active ? 'border-slate-200' : 'border-slate-200 opacity-60'}">

          <!-- Cabecera de la tarjeta -->
          <div class="p-5 border-b border-slate-100">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <h3 class="font-bold text-slate-800 text-base truncate">{plan.name}</h3>
                <p class="text-slate-400 text-xs font-mono mt-0.5">{plan.slug}</p>
              </div>
              <button onclick={() => toggle(plan.id)}
                class="relative w-10 h-5 rounded-full transition-colors cursor-pointer flex-shrink-0 mt-0.5
                  {plan.is_active ? 'bg-blue-600' : 'bg-slate-200'}"
                title="{plan.is_active ? 'Desactivar' : 'Activar'} plan">
                <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-all
                  {plan.is_active ? 'left-5' : 'left-0.5'}"></span>
              </button>
            </div>
            {#if plan.description}
              <p class="text-slate-500 text-xs mt-2 leading-relaxed">{plan.description}</p>
            {/if}
          </div>

          <!-- Precios -->
          <div class="px-5 py-4 flex gap-6 border-b border-slate-100">
            <div>
              <p class="text-xs text-slate-400 mb-0.5">Mensual</p>
              <p class="text-xl font-bold text-slate-800">${fmt(plan.price_monthly)}</p>
            </div>
            <div class="border-l border-slate-100 pl-6">
              <p class="text-xs text-slate-400 mb-0.5">Anual</p>
              <p class="text-xl font-bold text-slate-800">${fmt(plan.price_yearly)}</p>
            </div>
            <div class="border-l border-slate-100 pl-6">
              <p class="text-xs text-slate-400 mb-0.5">Trial</p>
              <p class="text-xl font-bold text-slate-800">{plan.trial_days}d</p>
            </div>
          </div>

          <!-- Límites -->
          <div class="px-5 py-3 space-y-1.5 border-b border-slate-100">
            {#each [['Usuarios', plan.max_users], ['Productos', plan.max_products], ['Facturas/mes', plan.max_invoices_monthly]] as [label, val]}
              <div class="flex justify-between text-xs">
                <span class="text-slate-500">{label}</span>
                <span class="font-medium text-slate-700">{val ?? '∞ ilimitado'}</span>
              </div>
            {/each}
          </div>

          <!-- Módulos -->
          <div class="px-5 py-3 flex-1">
            <p class="text-xs text-slate-400 mb-2">Módulos</p>
            <div class="flex flex-wrap gap-1.5">
              {#each allFeatures as key}
                {@const info = featureLabel[key] ?? { label: key, icon: 'mdi-check' }}
                <span class="text-xs px-2 py-0.5 rounded-full font-medium flex items-center gap-1
                  {plan.features?.[key] ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-400 line-through'}">
                  <i class="mdi {info.icon} text-xs"></i>
                  {info.label}
                </span>
              {/each}
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500">
              <i class="mdi mdi-domain text-slate-400 mr-1"></i>
              <strong class="text-slate-700">{plan.tenants_count}</strong>
              empresa{plan.tenants_count !== 1 ? 's' : ''}
            </p>
            <div class="flex items-center gap-3">
              <button onclick={() => openEdit(plan)}
                class="text-xs text-slate-500 hover:text-blue-600 transition cursor-pointer flex items-center gap-1">
                <i class="mdi mdi-pencil-outline"></i> Editar
              </button>
              {#if plan.tenants_count === 0}
                <button onclick={() => destroy(plan)} disabled={deleting === plan.id}
                  class="text-xs text-slate-400 hover:text-red-600 transition cursor-pointer flex items-center gap-1 disabled:opacity-50">
                  <i class="mdi mdi-trash-can-outline"></i> Eliminar
                </button>
              {/if}
            </div>
          </div>
        </div>
      {/each}

      <!-- Tarjeta vacía para crear -->
      <button onclick={openCreate}
        class="bg-white rounded-xl border-2 border-dashed border-slate-200 p-8 flex flex-col items-center justify-center gap-3
               hover:border-blue-300 hover:bg-blue-50/30 transition cursor-pointer group min-h-48">
        <div class="w-12 h-12 rounded-xl bg-slate-100 group-hover:bg-blue-100 flex items-center justify-center transition">
          <i class="mdi mdi-plus text-2xl text-slate-400 group-hover:text-blue-600 transition"></i>
        </div>
        <p class="text-sm font-medium text-slate-400 group-hover:text-blue-600 transition">Crear nuevo plan</p>
      </button>
    </div>

  </div>
</AdminLayout>

<ConfirmModal
  bind:open={confirmDeletePlan.open}
  title="Eliminar plan"
  message={confirmDeletePlan.plan ? `¿Eliminar el plan "${confirmDeletePlan.plan.name}"? Esta acción no se puede deshacer.` : ''}
  confirmLabel="Eliminar"
  danger={true}
  onConfirm={executeDeletePlan}
/>

<!-- ── Modal Crear / Editar Plan ─────────────────────────────────────────────── -->
{#if showModal}
  <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
    onclick={(e) => { if (e.target === e.currentTarget) closeModal() }}>

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col z-50">

      <!-- Cabecera -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center">
            <i class="mdi mdi-layers-outline text-blue-600 text-lg"></i>
          </div>
          <h3 class="font-bold text-slate-800">
            {editingPlan ? `Editar: ${editingPlan.name}` : 'Nuevo plan de suscripción'}
          </h3>
        </div>
        <button onclick={closeModal} class="text-slate-400 hover:text-slate-600 transition cursor-pointer">
          <i class="mdi mdi-close text-xl"></i>
        </button>
      </div>

      <!-- Cuerpo -->
      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-6">

        <!-- Información general -->
        <div class="space-y-4">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Información general</p>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">
              Nombre <span class="text-red-500">*</span>
            </label>
            <input type="text" bind:value={form.name} placeholder="Ej: Plan Profesional"
              class="w-full border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-300
                {errors.name ? 'border-red-400 bg-red-50' : 'border-slate-200'}" />
            {#if errors.name}<p class="text-red-500 text-xs mt-1">{errors.name}</p>{/if}
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1.5">Descripción</label>
            <textarea bind:value={form.description} rows="2"
              placeholder="Describe brevemente qué incluye este plan..."
              class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm resize-none focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-300"></textarea>
          </div>

          <label class="flex items-center gap-3 cursor-pointer select-none">
            <div onclick={() => form.is_active = !form.is_active}
              class="relative w-10 h-5 rounded-full transition-colors cursor-pointer
                {form.is_active ? 'bg-blue-600' : 'bg-slate-200'}">
              <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-all
                {form.is_active ? 'left-5' : 'left-0.5'}"></span>
            </div>
            <span class="text-sm text-slate-600">Plan activo (visible para nuevas suscripciones)</span>
          </label>
        </div>

        <!-- Precios -->
        <div class="space-y-3">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Precios (COP)</p>
          <div class="grid grid-cols-3 gap-4">
            {#each [['price_monthly', 'Mensual'], ['price_yearly', 'Anual'], ['trial_days', 'Trial (días)']] as [field, label]}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">
                  {label} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  {#if field !== 'trial_days'}
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                  {/if}
                  <input type="number" min="0" step={field === 'trial_days' ? 1 : 1000}
                    bind:value={form[field]}
                    class="w-full border border-slate-200 rounded-xl py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-300
                      {field !== 'trial_days' ? 'pl-7 pr-3' : 'px-3'}" />
                </div>
                {#if errors[field]}<p class="text-red-500 text-xs mt-1">{errors[field]}</p>{/if}
              </div>
            {/each}
          </div>
        </div>

        <!-- Límites -->
        <div class="space-y-3">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
            Límites <span class="font-normal normal-case text-slate-400">— dejar vacío = ilimitado</span>
          </p>
          <div class="grid grid-cols-3 gap-4">
            {#each [['max_users', 'Usuarios'], ['max_products', 'Productos'], ['max_invoices_monthly', 'Facturas/mes']] as [field, label]}
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">{label}</label>
                <input type="number" min="1" bind:value={form[field]} placeholder="∞"
                  class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-300" />
              </div>
            {/each}
          </div>
        </div>

        <!-- Módulos -->
        <div class="space-y-3">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Módulos incluidos</p>
          <div class="grid grid-cols-2 gap-2">
            {#each allFeatures as key}
              {@const info = featureLabel[key] ?? { label: key, icon: 'mdi-check' }}
              <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition select-none
                {form.features[key] ? 'border-blue-200 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'}">
                <input type="checkbox" bind:checked={form.features[key]}
                  class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 cursor-pointer" />
                <div class="flex items-center gap-2">
                  <i class="mdi {info.icon} text-sm {form.features[key] ? 'text-blue-600' : 'text-slate-400'}"></i>
                  <span class="text-sm {form.features[key] ? 'text-blue-700 font-medium' : 'text-slate-600'}">
                    {info.label}
                  </span>
                </div>
              </label>
            {/each}
          </div>
        </div>

        {#if errors.plan}
          <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
            <i class="mdi mdi-alert-circle-outline flex-shrink-0"></i>
            {errors.plan}
          </div>
        {/if}

      </div>

      <!-- Pie -->
      <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
        <button onclick={closeModal} disabled={submitting}
          class="px-4 py-2 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition cursor-pointer disabled:opacity-50">
          Cancelar
        </button>
        <button onclick={submit} disabled={submitting}
          class="flex items-center gap-2 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition cursor-pointer disabled:opacity-60">
          {#if submitting}
            <i class="mdi mdi-loading mdi-spin text-base"></i> Guardando...
          {:else}
            <i class="mdi mdi-content-save-outline text-base"></i>
            {editingPlan ? 'Guardar cambios' : 'Crear plan'}
          {/if}
        </button>
      </div>

    </div>
  </div>
{/if}
