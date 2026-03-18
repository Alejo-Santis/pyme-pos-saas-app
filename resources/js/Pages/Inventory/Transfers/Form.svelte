<script>
  import { router, inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let {
    warehouses = [],
    items      = [],
  } = $props()

  const fmt  = (n) => Number(n ?? 0).toLocaleString('es-CO', { minimumFractionDigits: 0 })
  const today = new Date().toISOString().slice(0, 10)

  let form = $state({
    warehouse_origin_id:      '',
    warehouse_destination_id: '',
    transfer_date:            today,
    notes:                    '',
    items:                    [],
  })

  let errors    = $state({})
  let submitting = $state(false)
  let itemSearch = $state('')

  // Ítems filtrados para el buscador
  const filteredItems = $derived(
    itemSearch.trim().length < 2
      ? []
      : items.filter(i =>
          i.name.toLowerCase().includes(itemSearch.toLowerCase()) ||
          i.internal_code?.toLowerCase().includes(itemSearch.toLowerCase())
        ).slice(0, 8)
  )

  function addItem(item) {
    const exists = form.items.find(l => l.item_id === item.id)
    if (exists) { itemSearch = ''; return }
    form.items = [...form.items, {
      item_id:     item.id,
      item_name:   item.name,
      item_code:   item.internal_code,
      quantity:    1,
      cost:        Number(item.average_cost ?? 0),
      line_total:  Number(item.average_cost ?? 0),
    }]
    itemSearch = ''
  }

  function removeItem(idx) {
    form.items = form.items.filter((_, i) => i !== idx)
  }

  function updateLine(idx, field, value) {
    form.items = form.items.map((l, i) => {
      if (i !== idx) return l
      const updated = { ...l, [field]: value }
      updated.line_total = Number(updated.quantity) * Number(updated.cost)
      return updated
    })
  }

  const total = $derived(form.items.reduce((s, l) => s + Number(l.line_total), 0))

  function submit() {
    errors    = {}
    submitting = true
    router.post('/inventory/transfers', {
      ...form,
      items: form.items.map(l => ({
        item_id:  l.item_id,
        quantity: l.quantity,
        cost:     l.cost,
      })),
    }, {
      onError: (e) => { errors = e; submitting = false },
      onFinish: () => { submitting = false },
    })
  }
</script>

<AppLayout>
  <div class="max-w-4xl mx-auto space-y-5">

    <!-- Cabecera -->
    <div class="flex items-center gap-3">
      <a use:inertia href="/inventory/transfers"
        class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-xl font-bold text-slate-800">Nuevo traslado</h1>
        <p class="text-sm text-slate-500">Mueve ítems entre dos bodegas</p>
      </div>
    </div>

    <!-- Datos generales -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
        <i class="mdi mdi-information-outline text-primary"></i>
        Datos del traslado
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Bodega origen -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1.5">
            Bodega origen <span class="text-red-500">*</span>
          </label>
          <select bind:value={form.warehouse_origin_id}
            class="w-full border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30
              {errors.warehouse_origin_id ? 'border-red-400 bg-red-50' : 'border-slate-200'}">
            <option value="">Seleccionar bodega...</option>
            {#each warehouses as wh}
              {#if wh.id !== form.warehouse_destination_id}
                <option value={wh.id}>{wh.name}</option>
              {/if}
            {/each}
          </select>
          {#if errors.warehouse_origin_id}
            <p class="text-red-500 text-xs mt-1">{errors.warehouse_origin_id}</p>
          {/if}
        </div>

        <!-- Bodega destino -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1.5">
            Bodega destino <span class="text-red-500">*</span>
          </label>
          <select bind:value={form.warehouse_destination_id}
            class="w-full border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30
              {errors.warehouse_destination_id ? 'border-red-400 bg-red-50' : 'border-slate-200'}">
            <option value="">Seleccionar bodega...</option>
            {#each warehouses as wh}
              {#if wh.id !== form.warehouse_origin_id}
                <option value={wh.id}>{wh.name}</option>
              {/if}
            {/each}
          </select>
          {#if errors.warehouse_destination_id}
            <p class="text-red-500 text-xs mt-1">{errors.warehouse_destination_id}</p>
          {/if}
        </div>

        <!-- Fecha -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1.5">Fecha <span class="text-red-500">*</span></label>
          <input type="date" bind:value={form.transfer_date}
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
        </div>

        <!-- Notas -->
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-1.5">Observaciones</label>
          <input type="text" bind:value={form.notes} placeholder="Opcional..."
            class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
        </div>
      </div>
    </div>

    <!-- Ítems -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
          <i class="mdi mdi-package-variant text-primary"></i>
          Ítems a trasladar
        </h2>
      </div>

      <!-- Buscador de ítems -->
      <div class="px-5 py-3 border-b border-slate-100 relative">
        <div class="relative">
          <i class="mdi mdi-magnify absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <input type="text" bind:value={itemSearch}
            placeholder="Buscar ítem por nombre o código..."
            class="w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30" />
        </div>
        {#if filteredItems.length > 0}
          <div class="absolute left-5 right-5 bg-white border border-slate-200 rounded-xl shadow-lg z-20 mt-1 overflow-hidden">
            {#each filteredItems as item}
              <button onclick={() => addItem(item)}
                class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-primary/5 transition text-left cursor-pointer">
                <div>
                  <p class="font-medium text-slate-700 text-sm">{item.name}</p>
                  <p class="text-xs text-slate-400">{item.internal_code ?? ''}</p>
                </div>
                <span class="text-xs text-slate-500 tabular-nums">Costo: ${fmt(item.average_cost)}</span>
              </button>
            {/each}
          </div>
        {/if}
      </div>

      <!-- Tabla de ítems -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="text-left px-4 py-2.5 text-xs font-medium text-slate-500">Ítem</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Cantidad</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-32">Costo unitario</th>
              <th class="text-right px-4 py-2.5 text-xs font-medium text-slate-500 w-28">Total línea</th>
              <th class="w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each form.items as line, idx}
              <tr class="hover:bg-slate-50/50">
                <td class="px-4 py-3">
                  <p class="font-medium text-slate-700">{line.item_name}</p>
                  {#if line.item_code}
                    <p class="text-xs text-slate-400">{line.item_code}</p>
                  {/if}
                </td>
                <td class="px-4 py-3 text-right">
                  <input type="number" min="0.001" step="1"
                    value={line.quantity}
                    oninput={(e) => updateLine(idx, 'quantity', e.target.value)}
                    class="w-24 text-right border border-slate-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 tabular-nums" />
                </td>
                <td class="px-4 py-3 text-right">
                  <input type="number" min="0" step="0.01"
                    value={line.cost}
                    oninput={(e) => updateLine(idx, 'cost', e.target.value)}
                    class="w-28 text-right border border-slate-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/30 tabular-nums" />
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-700 tabular-nums">
                  ${fmt(line.line_total)}
                </td>
                <td class="px-4 py-3 text-center">
                  <button onclick={() => removeItem(idx)}
                    class="text-red-400 hover:text-red-600 transition cursor-pointer">
                    <i class="mdi mdi-trash-can-outline text-lg"></i>
                  </button>
                </td>
              </tr>
            {/each}
            {#if form.items.length === 0}
              <tr>
                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-400">
                  <i class="mdi mdi-package-variant-remove text-3xl text-slate-200 block mb-2"></i>
                  Busca ítems arriba para agregarlos al traslado
                </td>
              </tr>
            {/if}
          </tbody>
          {#if form.items.length > 0}
            <tfoot class="bg-slate-50 border-t border-slate-200">
              <tr>
                <td colspan="3" class="px-4 py-3 text-sm font-semibold text-slate-700">Total traslado</td>
                <td class="px-4 py-3 text-right font-bold text-slate-800 tabular-nums">${fmt(total)}</td>
                <td></td>
              </tr>
            </tfoot>
          {/if}
        </table>
      </div>
    </div>

    {#if errors.items}
      <div class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
        <i class="mdi mdi-alert-circle-outline"></i>
        {errors.items}
      </div>
    {/if}

    <!-- Acciones -->
    <div class="flex justify-end gap-3 pb-6">
      <a use:inertia href="/inventory/transfers"
        class="px-4 py-2.5 text-sm border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 transition">
        Cancelar
      </a>
      <button onclick={submit} disabled={submitting || form.items.length === 0}
        class="flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary-dark transition cursor-pointer disabled:opacity-60">
        {#if submitting}
          <i class="mdi mdi-loading mdi-spin"></i> Guardando...
        {:else}
          <i class="mdi mdi-content-save-outline"></i> Crear traslado
        {/if}
      </button>
    </div>

  </div>
</AppLayout>
