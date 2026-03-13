<script>
  import { useForm, router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { suppliers, warehouses, items } = $props()

  // ── Formulario ──────────────────────────────────────────────────────────────
  const form = useForm({
    third_party_id: '',
    reference:      '',
    issue_date:     new Date().toISOString().split('T')[0],
    notes:          '',
    lines:          [],
  })

  // ── Búsqueda de ítem ────────────────────────────────────────────────────────
  let itemSearch   = $state('')
  let showDropdown = $state(false)

  const filteredItems = $derived(
    itemSearch.length >= 2
      ? items.filter(i =>
          i.name.toLowerCase().includes(itemSearch.toLowerCase()) ||
          i.code?.toLowerCase().includes(itemSearch.toLowerCase())
        ).slice(0, 8)
      : []
  )

  function selectItem(item) {
    const exists = $form.lines.findIndex(l => l.item_id === item.id)
    if (exists >= 0) {
      $form.lines[exists].invoice_quantity++
      recalcLine(exists)
    } else {
      $form.lines = [...$form.lines, {
        item_id:               item.id,
        item_name:             item.name,
        item_code:             item.code ?? '',
        invoice_quantity:      1,
        average_cost:          parseFloat(item.average_cost ?? item.purchase_price ?? 0),
        tax:                   null,
        line_extension_amount: parseFloat(item.average_cost ?? item.purchase_price ?? 0),
      }]
    }
    itemSearch   = ''
    showDropdown = false
  }

  function recalcLine(i) {
    const line = $form.lines[i]
    line.line_extension_amount = +(line.invoice_quantity * line.average_cost).toFixed(4)
    $form.lines = [...$form.lines]
  }

  function removeLine(i) {
    $form.lines = $form.lines.filter((_, idx) => idx !== i)
  }

  // ── Totales ─────────────────────────────────────────────────────────────────
  const total = $derived($form.lines.reduce((s, l) => s + (l.line_extension_amount ?? 0), 0))

  function fmt(n) {
    return Number(n ?? 0).toLocaleString('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 })
  }

  function submit() {
    $form.post('/purchases')
  }
</script>

<AppLayout title="Nueva Orden de Compra">
  <div class="max-w-5xl mx-auto">
    <!-- Encabezado -->
    <div class="flex items-center gap-3 mb-6">
      <a href="/purchases" class="text-slate-400 hover:text-slate-600">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Nueva Orden de Compra</h1>
        <p class="text-sm text-slate-500">Registra una compra a proveedor</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Col izquierda: datos generales -->
      <div class="lg:col-span-1 space-y-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <h2 class="font-semibold text-slate-700 mb-4">Datos generales</h2>

          <!-- Proveedor -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Proveedor</label>
            <select bind:value={$form.third_party_id}
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">— Sin proveedor —</option>
              {#each suppliers as s}
                <option value={s.id}>{s.business_name}</option>
              {/each}
            </select>
          </div>

          <!-- Referencia proveedor -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Referencia proveedor</label>
            <input bind:value={$form.reference} type="text" placeholder="Ej: FAC-001"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            {#if $form.errors.reference}<p class="text-xs text-red-600 mt-1">{$form.errors.reference}</p>{/if}
          </div>

          <!-- Fecha -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-1">Fecha <span class="text-red-500">*</span></label>
            <input bind:value={$form.issue_date} type="date"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
            {#if $form.errors.issue_date}<p class="text-xs text-red-600 mt-1">{$form.errors.issue_date}</p>{/if}
          </div>

          <!-- Notas -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notas</label>
            <textarea bind:value={$form.notes} rows="3" placeholder="Observaciones..."
                      class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
          </div>
        </div>

        <!-- Totales -->
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <h2 class="font-semibold text-slate-700 mb-3">Resumen</h2>
          <div class="flex justify-between text-sm mb-2">
            <span class="text-slate-600">Artículos:</span>
            <span class="font-medium">{$form.lines.length}</span>
          </div>
          <div class="flex justify-between text-base font-bold text-slate-800 border-t border-slate-100 pt-2 mt-2">
            <span>Total:</span>
            <span class="text-blue-700">{fmt(total)}</span>
          </div>
        </div>

        <!-- Acciones -->
        <button onclick={submit}
                disabled={$form.processing || $form.lines.length === 0}
                class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-medium py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
          {#if $form.processing}
            <i class="mdi mdi-loading mdi-spin"></i> Guardando…
          {:else}
            <i class="mdi mdi-content-save-outline"></i> Crear Orden de Compra
          {/if}
        </button>
      </div>

      <!-- Col derecha: ítems -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="font-semibold text-slate-700 mb-4">Artículos a comprar</h2>

        <!-- Buscador de ítems -->
        <div class="relative mb-4">
          <input bind:value={itemSearch}
                 onfocus={() => showDropdown = true}
                 onblur={() => setTimeout(() => showDropdown = false, 200)}
                 type="text" placeholder="Buscar artículo por nombre o código…"
                 class="w-full border border-slate-300 rounded-lg px-3 py-2 pl-9 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"/>
          <i class="mdi mdi-magnify absolute left-3 top-2.5 text-slate-400"></i>

          {#if showDropdown && filteredItems.length > 0}
            <div class="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-56 overflow-y-auto">
              {#each filteredItems as item}
                <button onmousedown={() => selectItem(item)}
                        class="w-full text-left px-4 py-2.5 hover:bg-blue-50 text-sm border-b border-slate-100 last:border-0">
                  <span class="font-medium text-slate-800">{item.name}</span>
                  {#if item.code}<span class="text-slate-400 ml-2 text-xs">{item.code}</span>{/if}
                  <span class="float-right text-slate-600">{fmt(item.average_cost ?? item.purchase_price ?? 0)}</span>
                </button>
              {/each}
            </div>
          {/if}
        </div>

        {#if $form.errors.lines}
          <p class="text-sm text-red-600 mb-3">{$form.errors.lines}</p>
        {/if}

        <!-- Tabla de líneas -->
        {#if $form.lines.length > 0}
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-3 py-2 text-left font-semibold text-slate-600">Artículo</th>
                  <th class="px-3 py-2 text-right font-semibold text-slate-600 w-24">Cantidad</th>
                  <th class="px-3 py-2 text-right font-semibold text-slate-600 w-32">Costo unit.</th>
                  <th class="px-3 py-2 text-right font-semibold text-slate-600 w-32">Subtotal</th>
                  <th class="px-3 py-2 w-10"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                {#each $form.lines as line, i}
                  <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2">
                      <p class="font-medium text-slate-800">{line.item_name}</p>
                      {#if line.item_code}<p class="text-xs text-slate-400">{line.item_code}</p>{/if}
                    </td>
                    <td class="px-3 py-2">
                      <input type="number" min="0.001" step="0.001"
                             bind:value={line.invoice_quantity}
                             oninput={() => recalcLine(i)}
                             class="w-full border border-slate-300 rounded px-2 py-1 text-right text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"/>
                    </td>
                    <td class="px-3 py-2">
                      <input type="number" min="0" step="1"
                             bind:value={line.average_cost}
                             oninput={() => recalcLine(i)}
                             class="w-full border border-slate-300 rounded px-2 py-1 text-right text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"/>
                    </td>
                    <td class="px-3 py-2 text-right font-medium text-slate-800">
                      {fmt(line.line_extension_amount)}
                    </td>
                    <td class="px-3 py-2 text-center">
                      <button onclick={() => removeLine(i)}
                              class="text-red-400 hover:text-red-600 transition-colors">
                        <i class="mdi mdi-trash-can-outline"></i>
                      </button>
                    </td>
                  </tr>
                {/each}
              </tbody>
              <tfoot class="bg-slate-50 border-t border-slate-200">
                <tr>
                  <td colspan="3" class="px-3 py-2 text-right font-bold text-slate-700">Total</td>
                  <td class="px-3 py-2 text-right font-bold text-blue-700">{fmt(total)}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        {:else}
          <div class="text-center py-12 text-slate-400">
            <i class="mdi mdi-cart-plus text-5xl block mb-3 opacity-40"></i>
            <p>Busca y agrega artículos a la orden</p>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AppLayout>
