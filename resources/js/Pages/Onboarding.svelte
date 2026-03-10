<script>
  import { useForm, router, inertia } from '@inertiajs/svelte'

  let {
    organizations  = [],
    regimes        = [],
    liabilities    = [],
    docTypes       = [],
    departments    = [],
    municipalities: allMunicipalities = [],
    countries      = [],
    company        = null,
  } = $props()

  // ─── Estado del stepper ────────────────────────────────────────────────
  let step = $state(company ? 2 : 1)

  // ─── Formulario Paso 1 — $state puro (sin useForm) ───────────────────
  let f1 = $state({
    identification_number:           company?.identification_number ?? '',
    dv:                              company?.dv ?? '',
    business_name:                   company?.business_name ?? '',
    trade_name:                      company?.trade_name ?? '',
    type_document_identification_id: company?.type_document_identification_id ?? '',
    type_organization_id:            company?.type_organization_id ?? '',
    type_regime_id:                  company?.type_regime_id ?? '',
    type_liability_id:               company?.type_liability_id ?? '',
    country_id:                      company?.country_id ?? (countries[0]?.id ?? ''),
    municipality_id:                 company?.municipality_id ?? '',
    email:                           company?.email ?? '',
    phone:                           company?.phone ?? '',
    address:                         company?.address ?? '',
  })
  let errors1     = $state({})
  let processing1 = $state(false)

  // ─── DV calculado automáticamente ────────────────────────────────────
  const PRIMES = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71]

  function calculateDV(nit) {
    const digits = String(nit).replace(/\D/g, '')
    if (!digits) return ''
    let sum = 0
    for (let i = 0; i < digits.length; i++) {
      sum += parseInt(digits[digits.length - 1 - i]) * PRIMES[i]
    }
    const rem = sum % 11
    return String(rem > 1 ? 11 - rem : rem)
  }

  const dvDisplay = $derived(
    f1.identification_number && /^\d+$/.test(f1.identification_number)
      ? calculateDV(f1.identification_number)
      : ''
  )

  // ─── Departamento → filtrar municipios en cliente (sin fetch) ─────────
  let selectedDeptId = $state('')

  const filteredMunicipalities = $derived(
    selectedDeptId
      ? allMunicipalities.filter(m => m.department_id == selectedDeptId)
      : []
  )

  function handleDeptChange() {
    f1.municipality_id = ''
  }

  // ─── Submit Paso 1 ────────────────────────────────────────────────────
  function submitCompany(e) {
    e.preventDefault()
    processing1 = true
    errors1 = {}
    router.post('/onboarding/company', { ...f1, dv: dvDisplay }, {
      onSuccess: () => { step = 2 },
      onError:   (errs) => { errors1 = errs },
      onFinish:  () => { processing1 = false },
    })
  }

  // ─── Formulario Paso 2: Resolución DIAN (simple, useForm funciona bien) ─
  const form2 = useForm({
    resolution:      '',
    resolution_date: '',
    prefix:          '',
    from:            '',
    to:              '',
    date_from:       '',
    date_to:         '',
  })

  function submitResolution(e) {
    e.preventDefault()
    $form2.post('/onboarding/resolution', {
      onSuccess: () => { step = 3 },
    })
  }

  function skipDian() {
    router.post('/onboarding/complete')
  }
</script>

<!-- ─── Fondo ─────────────────────────────────────────────────────────── -->
<div class="min-h-screen bg-body flex flex-col items-center justify-center px-4 py-10">

  <!-- Logo / título -->
  <div class="flex items-center gap-2 mb-8">
    <div class="w-9 h-9 bg-primary rounded-xl flex items-center justify-center shadow">
      <i class="mdi mdi-point-of-sale text-white text-xl"></i>
    </div>
    <div>
      <span class="text-slate-800 text-xl font-bold">NextPOS</span>
      <span class="text-slate-400 text-sm ml-2">SaaS</span>
    </div>
  </div>

  <!-- Tarjeta principal -->
  <div class="bg-white rounded-2xl shadow-lg border border-slate-100 w-full max-w-2xl overflow-hidden">

    <!-- Stepper header -->
    <div class="bg-primary px-8 py-5">
      <h2 class="text-white text-lg font-bold mb-4">Configuración inicial de tu empresa</h2>
      <div class="flex items-center gap-0">

        <!-- Paso 1 -->
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
            {step >= 1 ? 'bg-white text-primary' : 'bg-primary-darker text-blue-200'}">
            {#if step > 1}
              <i class="mdi mdi-check text-sm"></i>
            {:else}
              1
            {/if}
          </div>
          <span class="text-sm font-medium {step === 1 ? 'text-white' : 'text-blue-200'}">
            Datos de empresa
          </span>
        </div>

        <div class="flex-1 mx-3 h-px {step > 1 ? 'bg-white/60' : 'bg-primary-darker'}"></div>

        <!-- Paso 2 -->
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
            {step >= 2 ? 'bg-white text-primary' : 'bg-primary-darker text-blue-200'}">
            {#if step > 2}
              <i class="mdi mdi-check text-sm"></i>
            {:else}
              2
            {/if}
          </div>
          <span class="text-sm font-medium {step === 2 ? 'text-white' : 'text-blue-200'}">
            Resolución DIAN
          </span>
        </div>

        <div class="flex-1 mx-3 h-px {step > 2 ? 'bg-white/60' : 'bg-primary-darker'}"></div>

        <!-- Paso 3 -->
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0
            {step >= 3 ? 'bg-white text-primary' : 'bg-primary-darker text-blue-200'}">
            3
          </div>
          <span class="text-sm font-medium {step === 3 ? 'text-white' : 'text-blue-200'}">
            ¡Listo!
          </span>
        </div>

      </div>
    </div>

    <!-- ─── PASO 1: Datos de empresa ────────────────────────────────────── -->
    {#if step === 1}
      <form onsubmit={submitCompany} class="p-8 space-y-5">

        <p class="text-slate-500 text-sm -mt-2">
          Ingresa los datos tributarios de tu empresa. Esta información se usa para generar documentos electrónicos ante la DIAN.
        </p>

        <!-- NIT + DV -->
        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2">
            <label class="block text-slate-700 text-sm font-medium mb-1">
              NIT
              <span class="text-slate-400 font-normal">(sin dígito de verificación)</span>
            </label>
            <input
              type="text"
              inputmode="numeric"
              bind:value={f1.identification_number}
              placeholder="900123456"
              class="w-full border {errors1.identification_number ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            />
            {#if errors1.identification_number}
              <p class="text-red-500 text-xs mt-1">{errors1.identification_number}</p>
            {/if}
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">
              DV
              {#if dvDisplay}
                <span class="text-emerald-500 text-xs font-normal ml-1">calculado</span>
              {/if}
            </label>
            <div class="relative">
              <input
                type="text"
                maxlength="1"
                readonly
                value={dvDisplay}
                placeholder="—"
                class="w-full border {errors1.dv ? 'border-red-400' : 'border-slate-200'}
                       rounded-lg px-3 py-2 text-sm font-bold text-center
                       {dvDisplay ? 'text-emerald-600 bg-emerald-50' : 'text-slate-400 bg-slate-50'}
                       cursor-default select-none"
              />
              {#if dvDisplay}
                <i class="mdi mdi-check-circle text-emerald-500 absolute right-2 top-1/2 -translate-y-1/2 text-sm"></i>
              {/if}
            </div>
          </div>
        </div>

        <!-- Razón social + Nombre comercial -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Razón social *</label>
            <input
              type="text"
              bind:value={f1.business_name}
              placeholder="Mi Empresa SAS"
              class="w-full border {errors1.business_name ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            />
            {#if errors1.business_name}
              <p class="text-red-500 text-xs mt-1">{errors1.business_name}</p>
            {/if}
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Nombre comercial</label>
            <input
              type="text"
              bind:value={f1.trade_name}
              placeholder="(opcional)"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                     focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            />
          </div>
        </div>

        <!-- Tipo documento + Tipo organización -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Tipo de documento *</label>
            <select
              bind:value={f1.type_document_identification_id}
              class="w-full border {errors1.type_document_identification_id ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">Selecciona...</option>
              {#each docTypes as dt}
                <option value={dt.id}>{dt.name}</option>
              {/each}
            </select>
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Tipo organización *</label>
            <select
              bind:value={f1.type_organization_id}
              class="w-full border {errors1.type_organization_id ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">Selecciona...</option>
              {#each organizations as org}
                <option value={org.id}>{org.name}</option>
              {/each}
            </select>
          </div>
        </div>

        <!-- Régimen + Responsabilidad -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Régimen tributario *</label>
            <select
              bind:value={f1.type_regime_id}
              class="w-full border {errors1.type_regime_id ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">Selecciona...</option>
              {#each regimes as r}
                <option value={r.id}>{r.name}</option>
              {/each}
            </select>
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Responsabilidad tributaria *</label>
            <select
              bind:value={f1.type_liability_id}
              class="w-full border {errors1.type_liability_id ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">Selecciona...</option>
              {#each liabilities as l}
                <option value={l.id}>{l.name}</option>
              {/each}
            </select>
          </div>
        </div>

        <!-- Departamento + Municipio -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Departamento *</label>
            <select
              bind:value={selectedDeptId}
              onchange={handleDeptChange}
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                     focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">Selecciona...</option>
              {#each departments as dep}
                <option value={dep.id}>{dep.name}</option>
              {/each}
            </select>
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Municipio *</label>
            <select
              bind:value={f1.municipality_id}
              disabled={!selectedDeptId}
              class="w-full border {errors1.municipality_id ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 disabled:opacity-50
                     focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            >
              <option value="">
                {!selectedDeptId ? 'Primero el departamento' : 'Selecciona...'}
              </option>
              {#each filteredMunicipalities as mun}
                <option value={mun.id}>{mun.name}</option>
              {/each}
            </select>
            {#if errors1.municipality_id}
              <p class="text-red-500 text-xs mt-1">{errors1.municipality_id}</p>
            {/if}
          </div>
        </div>

        <!-- Email + Teléfono -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Correo electrónico *</label>
            <input
              type="email"
              bind:value={f1.email}
              placeholder="empresa@correo.com"
              class="w-full border {errors1.email ? 'border-red-400' : 'border-slate-300'}
                     rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            />
            {#if errors1.email}
              <p class="text-red-500 text-xs mt-1">{errors1.email}</p>
            {/if}
          </div>
          <div>
            <label class="block text-slate-700 text-sm font-medium mb-1">Teléfono</label>
            <input
              type="text"
              bind:value={f1.phone}
              placeholder="6017654321"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                     focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
            />
          </div>
        </div>

        <!-- Dirección -->
        <div>
          <label class="block text-slate-700 text-sm font-medium mb-1">Dirección *</label>
          <input
            type="text"
            bind:value={f1.address}
            placeholder="Calle 123 # 45-67"
            class="w-full border {errors1.address ? 'border-red-400' : 'border-slate-300'}
                   rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
          />
          {#if errors1.address}
            <p class="text-red-500 text-xs mt-1">{errors1.address}</p>
          {/if}
        </div>

        <!-- Botón -->
        <div class="flex justify-end pt-2">
          <button
            type="submit"
            disabled={processing1}
            class="bg-primary hover:bg-primary-dark disabled:opacity-60 disabled:cursor-not-allowed
                   text-white font-semibold rounded-lg px-6 py-2.5 text-sm transition flex items-center gap-2 cursor-pointer"
          >
            {#if processing1}
              <i class="mdi mdi-loading mdi-spin"></i> Guardando...
            {:else}
              Continuar <i class="mdi mdi-arrow-right"></i>
            {/if}
          </button>
        </div>

      </form>
    {/if}

    <!-- ─── PASO 2: Resolución DIAN ──────────────────────────────────────── -->
    {#if step === 2}
      <div class="p-8">

        <!-- Aviso informativo -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 flex gap-3">
          <i class="mdi mdi-information-outline text-blue-500 text-xl shrink-0 mt-0.5"></i>
          <div class="text-sm text-blue-800">
            <p class="font-semibold mb-1">¿Qué es la resolución DIAN?</p>
            <p class="text-blue-700">
              Es el número de autorización que la DIAN otorga para facturar electrónicamente.
              Puedes configurarla ahora o hacerlo más tarde desde <strong>Configuración → Resoluciones</strong>.
            </p>
          </div>
        </div>

        <form onsubmit={submitResolution} class="space-y-5">

          <!-- N° Resolución + Fecha resolución -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">N° Resolución *</label>
              <input
                type="text"
                bind:value={$form2.resolution}
                placeholder="18760000001"
                class="w-full border {$form2.errors.resolution ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
              {#if $form2.errors.resolution}
                <p class="text-red-500 text-xs mt-1">{$form2.errors.resolution}</p>
              {/if}
            </div>
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Fecha de resolución *</label>
              <input
                type="date"
                bind:value={$form2.resolution_date}
                class="w-full border {$form2.errors.resolution_date ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
            </div>
          </div>

          <!-- Prefijo + Rango desde/hasta -->
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Prefijo</label>
              <input
                type="text"
                maxlength="10"
                bind:value={$form2.prefix}
                placeholder="FV (opcional)"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800
                       focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
            </div>
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Desde *</label>
              <input
                type="number"
                min="1"
                bind:value={$form2.from}
                placeholder="1"
                class="w-full border {$form2.errors.from ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
            </div>
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Hasta *</label>
              <input
                type="number"
                min="1"
                bind:value={$form2.to}
                placeholder="1000"
                class="w-full border {$form2.errors.to ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
              {#if $form2.errors.to}
                <p class="text-red-500 text-xs mt-1">{$form2.errors.to}</p>
              {/if}
            </div>
          </div>

          <!-- Vigencia desde/hasta -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Vigencia desde *</label>
              <input
                type="date"
                bind:value={$form2.date_from}
                class="w-full border {$form2.errors.date_from ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
            </div>
            <div>
              <label class="block text-slate-700 text-sm font-medium mb-1">Vigencia hasta *</label>
              <input
                type="date"
                bind:value={$form2.date_to}
                class="w-full border {$form2.errors.date_to ? 'border-red-400' : 'border-slate-300'}
                       rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary transition"
              />
            </div>
          </div>

          <!-- Botones -->
          <div class="flex items-center justify-between pt-2">
            <button
              type="button"
              onclick={skipDian}
              class="text-slate-400 hover:text-slate-600 text-sm transition cursor-pointer flex items-center gap-1"
            >
              <i class="mdi mdi-skip-next"></i>
              Configurar después
            </button>
            <button
              type="submit"
              disabled={$form2.processing}
              class="bg-primary hover:bg-primary-dark disabled:opacity-60 disabled:cursor-not-allowed
                     text-white font-semibold rounded-lg px-6 py-2.5 text-sm transition flex items-center gap-2 cursor-pointer"
            >
              {#if $form2.processing}
                <i class="mdi mdi-loading mdi-spin"></i> Guardando...
              {:else}
                Finalizar <i class="mdi mdi-check-circle-outline"></i>
              {/if}
            </button>
          </div>

        </form>
      </div>
    {/if}

    <!-- ─── PASO 3: Completado ────────────────────────────────────────────── -->
    {#if step === 3}
      <div class="p-8 text-center">
        <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
          <i class="mdi mdi-check-circle text-emerald-500 text-4xl"></i>
        </div>
        <h3 class="text-slate-800 text-xl font-bold mb-2">¡Configuración completada!</h3>
        <p class="text-slate-500 text-sm mb-6">
          Tu empresa está lista. Ahora puedes comenzar a gestionar tus ventas, inventario y facturación electrónica.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8">
          <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
            <i class="mdi mdi-receipt-text text-primary text-2xl mb-2 block"></i>
            <p class="text-slate-700 text-xs font-semibold">Facturación</p>
            <p class="text-slate-400 text-xs">DIAN electrónica</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
            <i class="mdi mdi-point-of-sale text-primary text-2xl mb-2 block"></i>
            <p class="text-slate-700 text-xs font-semibold">POS</p>
            <p class="text-slate-400 text-xs">Punto de venta</p>
          </div>
          <div class="bg-slate-50 rounded-xl p-4 text-center border border-slate-100">
            <i class="mdi mdi-package-variant text-primary text-2xl mb-2 block"></i>
            <p class="text-slate-700 text-xs font-semibold">Inventario</p>
            <p class="text-slate-400 text-xs">Multi-bodega</p>
          </div>
        </div>

        <a
          use:inertia
          href="/dashboard"
          class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white
                 font-semibold rounded-lg px-8 py-3 text-sm transition"
        >
          <i class="mdi mdi-view-dashboard-outline"></i>
          Ir al Dashboard
        </a>
      </div>
    {/if}

  </div>

  <p class="text-slate-400 text-xs mt-6">NextPOS SaaS © {new Date().getFullYear()}</p>
</div>
