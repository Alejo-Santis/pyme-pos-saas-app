<script>
  import AppLayout from '@/Layouts/AppLayout.svelte'
  import { router, page } from '@inertiajs/svelte'

  let {
    company,
    environments,
    typeOrganizations,
    typeDocumentIdentifications,
    typeRegimes,
    typeLiabilities,
    countries,
    municipalities,
  } = $props()

  // Tabs
  let activeTab = $state('empresa')

  // Formulario reactivo
  let form = $state({
    identification_number:           company?.identification_number           ?? '',
    dv:                              company?.dv                              ?? '',
    business_name:                   company?.business_name                   ?? '',
    trade_name:                      company?.trade_name                      ?? '',
    email:                           company?.email                           ?? '',
    phone:                           company?.phone                           ?? '',
    address:                         company?.address                         ?? '',
    type_organization_id:            company?.type_organization_id            ?? '',
    type_document_identification_id: company?.type_document_identification_id ?? '',
    type_regime_id:                  company?.type_regime_id                  ?? '',
    type_liability_id:               company?.type_liability_id               ?? '',
    country_id:                      company?.country_id                      ?? '',
    municipality_id:                 company?.municipality_id                 ?? '',
    // DIAN
    type_environment_id:             company?.type_environment_id             ?? 2,
    dian_software_id:                company?.dian_software_id                ?? '',
    dian_software_security_code:     company?.dian_software_security_code     ?? '',
    dian_test_set_id:                company?.dian_test_set_id                ?? '',
    dian_provider:                   company?.dian_provider                   ?? '',
    api_path_fe:                     company?.api_path_fe                     ?? '',
    api_token_fe:                    company?.api_token_fe                    ?? '',
    // Flags
    electronic_documents:            company?.electronic_documents            ?? false,
    use_price_list:                  company?.use_price_list                  ?? false,
    prices_with_taxes_included:      company?.prices_with_taxes_included      ?? false,
  })

  let saving   = $state(false)
  let testingNextpyme = $state(false)
  let errors   = $derived($page.props.errors ?? {})
  let flash    = $derived($page.props.flash ?? {})

  function save() {
    saving = true
    router.put('/config/company', form, {
      preserveScroll: true,
      onFinish: () => { saving = false },
    })
  }

  function testNextpyme() {
    testingNextpyme = true
    router.post('/config/company/test-nextpyme', {}, {
      preserveScroll: true,
      onFinish: () => { testingNextpyme = false },
    })
  }

  const tabs = [
    { id: 'empresa',  label: 'Datos empresa',    icon: 'mdi-domain' },
    { id: 'dian',     label: 'Configuración DIAN', icon: 'mdi-shield-check-outline' },
    { id: 'opciones', label: 'Opciones',           icon: 'mdi-tune' },
  ]
</script>

<AppLayout>
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-bold text-slate-800">Configuración de Empresa</h1>
        <p class="text-sm text-slate-500 mt-0.5">Datos tributarios, DIAN y preferencias operativas</p>
      </div>
      <button
        onclick={save}
        disabled={saving}
        class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60 transition cursor-pointer"
      >
        <i class="mdi {saving ? 'mdi-loading mdi-spin' : 'mdi-content-save-outline'}"></i>
        {saving ? 'Guardando…' : 'Guardar cambios'}
      </button>
    </div>

    <!-- Flash -->
    {#if flash.success}
      <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
        <i class="mdi mdi-check-circle-outline text-base"></i>
        {flash.success}
      </div>
    {/if}
    {#if flash.error}
      <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
        <i class="mdi mdi-alert-circle-outline text-base"></i>
        {flash.error}
      </div>
    {/if}

    <!-- Tabs -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">

      <div class="flex border-b border-slate-200">
        {#each tabs as tab}
          <button
            onclick={() => activeTab = tab.id}
            class="flex items-center gap-2 px-5 py-3.5 text-sm font-medium border-b-2 transition cursor-pointer
              {activeTab === tab.id
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'}"
          >
            <i class="mdi {tab.icon} text-base"></i>
            {tab.label}
          </button>
        {/each}
      </div>

      <div class="p-6">

        <!-- ── Tab: Datos empresa ─────────────────────────────────── -->
        {#if activeTab === 'empresa'}
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <div class="md:col-span-2 grid grid-cols-3 gap-4">
              <div class="col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">NIT / Identificación *</label>
                <input bind:value={form.identification_number} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="900123456" />
                {#if errors.identification_number}<p class="text-red-500 text-xs mt-1">{errors.identification_number}</p>{/if}
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">DV *</label>
                <input bind:value={form.dv} type="text" maxlength="1" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="0" />
                {#if errors.dv}<p class="text-red-500 text-xs mt-1">{errors.dv}</p>{/if}
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-600 mb-1">Razón Social *</label>
              <input bind:value={form.business_name} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="Mi Empresa S.A.S." />
              {#if errors.business_name}<p class="text-red-500 text-xs mt-1">{errors.business_name}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Nombre Comercial</label>
              <input bind:value={form.trade_name} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="Nombre comercial" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Email *</label>
              <input bind:value={form.email} type="email" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="empresa@correo.com" />
              {#if errors.email}<p class="text-red-500 text-xs mt-1">{errors.email}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Teléfono</label>
              <input bind:value={form.phone} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="601 1234567" />
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Dirección *</label>
              <input bind:value={form.address} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="Calle 1 # 2-3" />
              {#if errors.address}<p class="text-red-500 text-xs mt-1">{errors.address}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de Organización *</label>
              <select bind:value={form.type_organization_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each typeOrganizations as org}
                  <option value={org.id}>{org.name}</option>
                {/each}
              </select>
              {#if errors.type_organization_id}<p class="text-red-500 text-xs mt-1">{errors.type_organization_id}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Tipo de Identificación *</label>
              <select bind:value={form.type_document_identification_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each typeDocumentIdentifications as t}
                  <option value={t.id}>{t.name}</option>
                {/each}
              </select>
              {#if errors.type_document_identification_id}<p class="text-red-500 text-xs mt-1">{errors.type_document_identification_id}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Régimen *</label>
              <select bind:value={form.type_regime_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each typeRegimes as r}
                  <option value={r.id}>{r.name}</option>
                {/each}
              </select>
              {#if errors.type_regime_id}<p class="text-red-500 text-xs mt-1">{errors.type_regime_id}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Responsabilidad tributaria *</label>
              <select bind:value={form.type_liability_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each typeLiabilities as l}
                  <option value={l.id}>{l.name}</option>
                {/each}
              </select>
              {#if errors.type_liability_id}<p class="text-red-500 text-xs mt-1">{errors.type_liability_id}</p>{/if}
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">País *</label>
              <select bind:value={form.country_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each countries as c}
                  <option value={c.id}>{c.name}</option>
                {/each}
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-600 mb-1">Municipio *</label>
              <select bind:value={form.municipality_id} class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Seleccionar…</option>
                {#each municipalities as m}
                  <option value={m.id}>{m.name}</option>
                {/each}
              </select>
              {#if errors.municipality_id}<p class="text-red-500 text-xs mt-1">{errors.municipality_id}</p>{/if}
            </div>

          </div>
        {/if}

        <!-- ── Tab: Configuración DIAN ────────────────────────────── -->
        {#if activeTab === 'dian'}
          <div class="space-y-6">

            <!-- Entorno -->
            <div>
              <label class="block text-xs font-medium text-slate-600 mb-2">Entorno DIAN</label>
              <div class="flex gap-3">
                {#each environments as env}
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      type="radio"
                      name="env"
                      value={env.id}
                      bind:group={form.type_environment_id}
                      class="text-blue-600"
                    />
                    <span class="text-sm text-slate-700">{env.name}</span>
                    {#if env.id === 2}
                      <span class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">Recomendado para inicio</span>
                    {/if}
                  </label>
                {/each}
              </div>
            </div>

            <!-- Proveedor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Proveedor tecnológico</label>
                <input bind:value={form.dian_provider} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="nextpyme" />
                <p class="text-xs text-slate-400 mt-1">Identificador del proveedor (ej: nextpyme)</p>
              </div>

              <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">URL API del proveedor</label>
                <input bind:value={form.api_path_fe} type="url" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white" placeholder="https://api.nextpyme.co/api/" />
                {#if errors.api_path_fe}<p class="text-red-500 text-xs mt-1">{errors.api_path_fe}</p>{/if}
              </div>

              <div class="md:col-span-2">
                <label class="block text-xs font-medium text-slate-600 mb-1">Token API (Bearer)</label>
                <input bind:value={form.api_token_fe} type="password" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono" placeholder="••••••••••••••••" />
                <p class="text-xs text-slate-400 mt-1">Token de autenticación para el proveedor FE. Se guarda cifrado.</p>
              </div>

              <div class="md:col-span-2 flex items-center justify-between gap-3 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
                <div>
                  <p class="text-sm font-medium text-blue-900">Prueba de conexión Nextpyme</p>
                  <p class="text-xs text-blue-700 mt-0.5">Usa la URL y token guardados. No emite documentos.</p>
                </div>
                <button
                  type="button"
                  onclick={testNextpyme}
                  disabled={testingNextpyme}
                  class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800 disabled:opacity-60"
                >
                  <i class="mdi {testingNextpyme ? 'mdi-loading mdi-spin' : 'mdi-lan-connect'}"></i>
                  {testingNextpyme ? 'Probando…' : 'Probar'}
                </button>
              </div>
            </div>

            <!-- Software DIAN -->
            <div class="border-t border-slate-100 pt-5">
              <h3 class="text-sm font-semibold text-slate-700 mb-4">Datos del software registrado en DIAN</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">ID Software DIAN</label>
                  <input bind:value={form.dian_software_id} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono text-sm" placeholder="uuid del software" />
                </div>

                <div>
                  <label class="block text-xs font-medium text-slate-600 mb-1">Código de Seguridad</label>
                  <input bind:value={form.dian_software_security_code} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono text-sm" placeholder="hash de seguridad" />
                </div>

                <div class="md:col-span-2">
                  <label class="block text-xs font-medium text-slate-600 mb-1">ID Set de Pruebas (TestSetId)</label>
                  <input bind:value={form.dian_test_set_id} type="text" class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white font-mono text-sm" placeholder="TestSetId DIAN habilitación" />
                  <p class="text-xs text-slate-400 mt-1">Solo necesario en entorno de habilitación.</p>
                </div>

              </div>
            </div>

          </div>
        {/if}

        <!-- ── Tab: Opciones ──────────────────────────────────────── -->
        {#if activeTab === 'opciones'}
          <div class="space-y-4">
            <p class="text-sm text-slate-500 mb-4">Habilitar o deshabilitar funcionalidades de la empresa.</p>

            {#each [
              { key: 'electronic_documents',       label: 'Facturación electrónica DIAN',             desc: 'Envía documentos a la DIAN automáticamente al crear facturas.' },
              { key: 'use_price_list',              label: 'Listas de precios',                        desc: 'Habilita múltiples listas de precios por artículo.' },
              { key: 'prices_with_taxes_included',  label: 'Precios con IVA incluido',                 desc: 'Los precios de los artículos incluyen los impuestos.' },
            ] as opt}
              <label class="flex items-start gap-4 p-4 border border-slate-200 rounded-lg cursor-pointer hover:bg-slate-50 transition">
                <div class="mt-0.5">
                  <input
                    type="checkbox"
                    bind:checked={form[opt.key]}
                    class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500"
                  />
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-700">{opt.label}</p>
                  <p class="text-xs text-slate-500 mt-0.5">{opt.desc}</p>
                </div>
              </label>
            {/each}
          </div>
        {/if}

      </div>
    </div>

  </div>
</AppLayout>
