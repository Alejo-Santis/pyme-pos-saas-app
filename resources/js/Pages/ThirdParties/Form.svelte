<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    third        = null,
    organizations = [],
    regimes       = [],
    liabilities   = [],
    docTypes      = [],
    thirds        = [],      // type_thirds (Natural, Jurídica, etc.)
    departments   = [],
    municipalities: allMunicipalities = [],
  } = $props()

  const isEdit = $derived(!!third)

  // ── DV calculator ──────────────────────────────────────────────────────────
  const PRIMES = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71]

  function calculateDV(nit) {
    const digits = String(nit).replace(/\D/g, '')
    if (!digits) return ''
    const reversed = digits.split('').reverse()
    const sum = reversed.reduce((acc, d, i) => acc + parseInt(d) * PRIMES[i], 0)
    const rem = sum % 11
    return rem > 1 ? String(11 - rem) : String(rem)
  }

  // ── Form state ─────────────────────────────────────────────────────────────
  let f = $state({
    type_document_identification_id: third?.type_document_identification_id ?? '',
    identification_number:           third?.identification_number           ?? '',
    type_organization_id:            third?.type_organization_id            ?? '',
    type_regime_id:                  third?.type_regime_id                  ?? '',
    type_liability_id:               third?.type_liability_id               ?? '',
    type_third_id:                   third?.type_third_id                   ?? '',
    name:                            third?.name                            ?? '',
    surname:                         third?.surname                         ?? '',
    email:                           third?.email                           ?? '',
    phone:                           third?.phone                           ?? '',
    address:                         third?.address                         ?? '',
    municipality_id:                 third?.municipality_id                 ?? '',
    excluded_from_taxes:             third?.excluded_from_taxes             ?? false,
    great_contributor:               third?.great_contributor               ?? false,
    self_retaining:                  third?.self_retaining                  ?? false,
    seller:                          third?.seller                          ?? false,
    credit_limit:                    third?.credit_limit                    ?? 0,
    payment_days:                    third?.payment_days                    ?? 0,
    is_active:                       third?.is_active                       ?? true,
    customer:                        third?.linkage?.customer               ?? false,
    provider:                        third?.linkage?.provider               ?? false,
  })

  let errors  = $state({})
  let loading = $state(false)

  // DV auto-calculado
  const dvDisplay = $derived(
    f.identification_number && /^\d+$/.test(f.identification_number)
      ? calculateDV(f.identification_number)
      : ''
  )

  // Municipios filtrados por departamento
  let selectedDeptId = $state(() => {
    if (!third?.municipality_id) return ''
    const mun = allMunicipalities.find(m => m.id == third.municipality_id)
    return mun ? String(mun.department_id) : ''
  })

  const filteredMunicipalities = $derived(
    selectedDeptId
      ? allMunicipalities.filter(m => m.department_id == selectedDeptId)
      : []
  )

  function handleDeptChange() {
    f.municipality_id = ''
  }

  // ── Submit ─────────────────────────────────────────────────────────────────
  function submit() {
    loading = true
    errors  = {}

    const payload = { ...f, dv: dvDisplay }
    const url     = isEdit ? `/third-parties/${third.id}` : '/third-parties'
    const method  = isEdit ? 'put' : 'post'

    router[method](url, payload, {
      onSuccess: () => { loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-3xl mx-auto space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center gap-3">
      <a use:inertia href="/third-parties"
        class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-slate-800">
          {isEdit ? 'Editar tercero' : 'Nuevo tercero'}
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {isEdit ? `Modificando: ${third.name}` : 'Clientes, proveedores y otros contactos'}
        </p>
      </div>
    </div>

    <!-- ── Sección 1: Identificación ──────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-card-account-details-outline text-primary"></i>
          Identificación
        </h2>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Tipo de documento -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Tipo de documento <span class="text-red-500">*</span>
          </label>
          <select bind:value={f.type_document_identification_id}
            class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.type_document_identification_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each docTypes as dt}
              <option value={dt.id}>{dt.name}</option>
            {/each}
          </select>
          {#if errors.type_document_identification_id}
            <p class="text-xs text-red-500 mt-1">{errors.type_document_identification_id}</p>
          {/if}
        </div>

        <!-- NIT / Documento + DV -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            N° Identificación <span class="text-red-500">*</span>
          </label>
          <div class="flex gap-2">
            <input bind:value={f.identification_number} type="text"
              placeholder="900123456"
              class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
                {errors.identification_number ? 'border-red-400' : 'border-slate-300'}">
            {#if dvDisplay !== ''}
              <div class="flex items-center gap-1.5 border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 min-w-16">
                <span class="text-xs text-slate-500">DV</span>
                <span class="font-bold text-slate-800">{dvDisplay}</span>
              </div>
            {/if}
          </div>
          {#if errors.identification_number}
            <p class="text-xs text-red-500 mt-1">{errors.identification_number}</p>
          {/if}
        </div>

        <!-- Tipo de organización -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Tipo de organización <span class="text-red-500">*</span>
          </label>
          <select bind:value={f.type_organization_id}
            class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.type_organization_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each organizations as o}
              <option value={o.id}>{o.name}</option>
            {/each}
          </select>
        </div>

        <!-- Tipo de régimen -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Régimen tributario <span class="text-red-500">*</span>
          </label>
          <select bind:value={f.type_regime_id}
            class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.type_regime_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each regimes as r}
              <option value={r.id}>{r.name}</option>
            {/each}
          </select>
        </div>

        <!-- Tipo de responsabilidad -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Responsabilidad DIAN <span class="text-red-500">*</span>
          </label>
          <select bind:value={f.type_liability_id}
            class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.type_liability_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each liabilities as l}
              <option value={l.id}>{l.name}</option>
            {/each}
          </select>
        </div>

        <!-- Tipo de tercero -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Tipo de persona <span class="text-red-500">*</span>
          </label>
          <select bind:value={f.type_third_id}
            class="w-full border rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.type_third_id ? 'border-red-400' : 'border-slate-300'}">
            <option value="">Seleccionar…</option>
            {#each thirds as tt}
              <option value={tt.id}>{tt.name}</option>
            {/each}
          </select>
        </div>

      </div>
    </div>

    <!-- ── Sección 2: Datos personales / empresa ───────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-account-outline text-primary"></i>
          Datos del tercero
        </h2>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Nombre / Razón social -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre / Razón social <span class="text-red-500">*</span>
          </label>
          <input bind:value={f.name} type="text" placeholder="Empresa S.A.S. / Juan"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>

        <!-- Apellido (solo persona natural) -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Apellido</label>
          <input bind:value={f.surname} type="text" placeholder="Solo persona natural"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <input bind:value={f.email} type="email" placeholder="contacto@empresa.com"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
          {#if errors.email}<p class="text-xs text-red-500 mt-1">{errors.email}</p>{/if}
        </div>

        <!-- Teléfono -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Teléfono</label>
          <input bind:value={f.phone} type="text" placeholder="601 234 5678"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Departamento -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Departamento</label>
          <select bind:value={selectedDeptId} onchange={handleDeptChange}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Seleccionar…</option>
            {#each departments as dep}
              <option value={String(dep.id)}>{dep.name}</option>
            {/each}
          </select>
        </div>

        <!-- Municipio -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Municipio</label>
          <select bind:value={f.municipality_id}
            disabled={filteredMunicipalities.length === 0}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary disabled:bg-slate-50 disabled:text-slate-400">
            <option value="">
              {filteredMunicipalities.length === 0 ? 'Selecciona un departamento' : 'Seleccionar…'}
            </option>
            {#each filteredMunicipalities as mun}
              <option value={mun.id}>{mun.name}</option>
            {/each}
          </select>
        </div>

        <!-- Dirección -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Dirección</label>
          <input bind:value={f.address} type="text" placeholder="Calle 1 # 2-3, Bogotá"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

      </div>
    </div>

    <!-- ── Sección 3: Tipo de vínculo ─────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-tag-multiple-outline text-primary"></i>
          Vínculo comercial
        </h2>
      </div>
      <div class="p-5">
        <div class="flex flex-wrap gap-6">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.customer} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Cliente</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.provider} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Proveedor</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.seller} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Vendedor</span>
          </label>
        </div>
      </div>
    </div>

    <!-- ── Sección 4: Configuración tributaria ────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-calculator-variant-outline text-primary"></i>
          Configuración tributaria
        </h2>
      </div>
      <div class="p-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.excluded_from_taxes} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Excluido de impuestos</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.great_contributor} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Gran contribuyente</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.self_retaining} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Autorretenedor</span>
          </label>
        </div>

        <!-- Crédito y días de pago -->
        <div class="grid grid-cols-2 gap-4 mt-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Cupo de crédito ($)</label>
            <input bind:value={f.credit_limit} type="number" min="0" step="1000"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Días de pago</label>
            <input bind:value={f.payment_days} type="number" min="0"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Acciones ────────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between pb-6">
      <a use:inertia href="/third-parties"
        class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
        Cancelar
      </a>
      <button onclick={submit} disabled={loading}
        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition disabled:opacity-60 cursor-pointer">
        {#if loading}
          <i class="mdi mdi-loading mdi-spin text-base"></i>
          Guardando…
        {:else}
          <i class="mdi mdi-content-save-outline text-base"></i>
          {isEdit ? 'Actualizar tercero' : 'Crear tercero'}
        {/if}
      </button>
    </div>

  </div>
</AppLayout>
