<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    item                = null,
    categories          = [],
    unitMeasures        = [],
    taxes               = [],
    itemClassifications = [],
  } = $props()

  const isEdit = $derived(!!item)

  let f = $state({
    internal_code:      item?.internal_code      ?? '',
    name:               item?.name               ?? '',
    short_name:         item?.short_name         ?? '',
    reference:          item?.reference          ?? '',
    note:               item?.note               ?? '',
    type:               item?.type               ?? 'product',
    item_category_id:   item?.item_category_id   ?? '',
    clasification_id:   item?.clasification_id   ?? '',
    tax_category_id:    item?.tax_category_id     ?? '',
    unit_measure_id:    item?.unit_measure_id     ?? '',
    last_purchase_price: item?.last_purchase_price ?? 0,
    average_cost:       item?.average_cost        ?? 0,
    default_sale_price: item?.default_sale_price  ?? 0,
    consumption_tax:    item?.consumption_tax     ?? 0,
    barcode_one:        item?.barcode_one         ?? '',
    barcode_two:        item?.barcode_two         ?? '',
    minimum_existence:  item?.minimum_existence   ?? 0,
    maximum_existence:  item?.maximum_existence   ?? 0,
    is_active:          item?.is_active           ?? true,
    manages_stock:      item?.manages_stock       ?? true,
    is_service:         item?.is_service          ?? false,
  })

  let errors  = $state({})
  let loading = $state(false)

  // Si es servicio → desactiva manejo de stock automáticamente
  $effect(() => {
    if (f.type === 'service') {
      f.manages_stock = false
      f.is_service    = true
    } else {
      f.is_service = false
    }
  })

  function submit() {
    loading = true
    errors  = {}
    const url    = isEdit ? `/inventory/${item.id}` : '/inventory'
    const method = isEdit ? 'put' : 'post'
    router[method](url, f, {
      onSuccess: () => { loading = false },
      onError:   (e) => { errors = e; loading = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-4xl mx-auto space-y-6">

    <!-- Cabecera -->
    <div class="flex items-center gap-3">
      <a use:inertia href="/inventory" class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-slate-800">
          {isEdit ? 'Editar artículo' : 'Nuevo artículo'}
        </h1>
        <p class="text-sm text-slate-500 mt-0.5">
          {isEdit ? item.name : 'Producto o servicio del inventario'}
        </p>
      </div>
    </div>

    <!-- ── Sección 1: Información básica ──────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-package-variant-closed text-primary"></i>
          Información básica
        </h2>
      </div>
      <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">

        <!-- Tipo -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo</label>
          <div class="flex gap-3">
            {#each [['product','Producto','mdi-package-variant'], ['service','Servicio','mdi-wrench-outline'], ['combo','Combo','mdi-layers-outline']] as [val, lbl, icon]}
              <label class="flex-1 cursor-pointer">
                <input bind:group={f.type} type="radio" value={val} class="sr-only">
                <div class="flex items-center gap-2 border-2 rounded-lg px-3 py-2.5 transition
                  {f.type === val
                    ? 'border-primary bg-primary/5 text-primary'
                    : 'border-slate-200 text-slate-500 hover:border-slate-300'}">
                  <i class="mdi {icon} text-lg"></i>
                  <span class="text-sm font-medium">{lbl}</span>
                </div>
              </label>
            {/each}
          </div>
        </div>

        <!-- Código interno -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Código interno
            <span class="text-slate-400 font-normal ml-1">(auto si vacío)</span>
          </label>
          <input bind:value={f.internal_code} type="text" placeholder="ART-0001"
            class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.internal_code ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.internal_code}
            <p class="text-xs text-red-500 mt-1">{errors.internal_code}</p>
          {/if}
        </div>

        <!-- Referencia -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Referencia / SKU</label>
          <input bind:value={f.reference} type="text" placeholder="REF-001"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Nombre -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Nombre <span class="text-red-500">*</span>
          </label>
          <input bind:value={f.name} type="text" placeholder="Nombre completo del artículo"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
              {errors.name ? 'border-red-400' : 'border-slate-300'}">
          {#if errors.name}<p class="text-xs text-red-500 mt-1">{errors.name}</p>{/if}
        </div>

        <!-- Nombre corto -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre corto</label>
          <input bind:value={f.short_name} type="text" placeholder="Nombre en ticket POS"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <!-- Categoría -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Categoría</label>
          <select bind:value={f.item_category_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Sin categoría</option>
            {#each categories as cat}
              <option value={cat.id}>
                {cat.parent ? `${cat.parent.name} › ${cat.name}` : cat.name}
              </option>
            {/each}
          </select>
        </div>

        <!-- Unidad de medida -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Unidad de medida</label>
          <select bind:value={f.unit_measure_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Seleccionar…</option>
            {#each unitMeasures as um}
              <option value={um.id}>{um.name} {um.code ? `(${um.code})` : ''}</option>
            {/each}
          </select>
        </div>

        <!-- Categoría tributaria (IVA) -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Categoría de impuesto</label>
          <select bind:value={f.tax_category_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            <option value="">Sin impuesto</option>
            {#each taxes as tax}
              <option value={tax.id}>{tax.name}{tax.code ? ` (${tax.code})` : ''}</option>
            {/each}
          </select>
        </div>

        <!-- Clasificación DIAN -->
        {#if itemClassifications.length > 0}
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Clasificación DIAN</label>
            <select bind:value={f.clasification_id}
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
              <option value="">Sin clasificación</option>
              {#each itemClassifications as cl}
                <option value={cl.id}>{cl.name}</option>
              {/each}
            </select>
          </div>
        {/if}

        <!-- Nota -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">Notas / descripción</label>
          <textarea bind:value={f.note} rows="2" placeholder="Información adicional…"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none">
          </textarea>
        </div>

      </div>
    </div>

    <!-- ── Sección 2: Precios ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-currency-usd text-primary"></i>
          Precios y costos
        </h2>
      </div>
      <div class="p-5 grid grid-cols-2 sm:grid-cols-3 gap-4">

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Precio de venta ($)</label>
          <input bind:value={f.default_sale_price} type="number" min="0" step="0.01"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Costo promedio ($)</label>
          <input bind:value={f.average_cost} type="number" min="0" step="0.01"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Último precio compra ($)</label>
          <input bind:value={f.last_purchase_price} type="number" min="0" step="0.01"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Impuesto consumo ($)</label>
          <input bind:value={f.consumption_tax} type="number" min="0" step="0.0001"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>

      </div>
    </div>

    <!-- ── Sección 3: Inventario y códigos de barras ──────────────────────── -->
    {#if f.type !== 'service'}
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-100">
          <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i class="mdi mdi-warehouse text-primary"></i>
            Control de inventario
          </h2>
        </div>
        <div class="p-5 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Stock mínimo</label>
              <input bind:value={f.minimum_existence} type="number" min="0" step="0.01"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Stock máximo</label>
              <input bind:value={f.maximum_existence} type="number" min="0" step="0.01"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Código de barras 1</label>
              <input bind:value={f.barcode_one} type="text" placeholder="EAN-13 / UPC"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Código de barras 2</label>
              <input bind:value={f.barcode_two} type="text" placeholder="Código alterno"
                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
            </div>
          </div>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.manages_stock} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-700">Maneja stock (afecta kardex)</span>
          </label>
        </div>
      </div>
    {/if}

    <!-- ── Acciones ────────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-between pb-6">
      <div class="flex items-center gap-3">
        <a use:inertia href="/inventory"
          class="px-4 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
          Cancelar
        </a>
        {#if isEdit}
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input bind:checked={f.is_active} type="checkbox"
              class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
            <span class="text-sm text-slate-600">Activo</span>
          </label>
        {/if}
      </div>
      <button onclick={submit} disabled={loading}
        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm rounded-lg font-medium hover:bg-primary-dark transition disabled:opacity-60 cursor-pointer">
        {#if loading}
          <i class="mdi mdi-loading mdi-spin text-base"></i>
          Guardando…
        {:else}
          <i class="mdi mdi-content-save-outline text-base"></i>
          {isEdit ? 'Actualizar artículo' : 'Crear artículo'}
        {/if}
      </button>
    </div>

  </div>
</AppLayout>
