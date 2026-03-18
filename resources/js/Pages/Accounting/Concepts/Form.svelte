<script>
  import { useForm, page } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { concept, operations } = $props()

  // Pre-fill desde query string si viene de "Configurar"
  const params = new URLSearchParams(window.location.search)

  const form = useForm({
    name:           concept?.name           ?? params.get('desc') ?? '',
    type_concept:   concept?.type_concept   ?? params.get('slug') ?? '',
    accountable_id: concept?.accountable_id ?? params.get('default') ?? '',
    is_tax_concept: concept?.is_tax_concept ?? false,
  })

  function submit() {
    if (concept) {
      form.put(`/accounting/concepts/${concept.id}`)
    } else {
      form.post('/accounting/concepts')
    }
  }

  // Todos los slugs predefinidos para el datalist
  const allSlugs = operations.flatMap(op => op.slugs.map(s => ({ slug: s.slug, desc: s.desc, op: op.name })))
</script>

<AppLayout title={concept ? 'Editar Concepto' : 'Nuevo Concepto'}>
  <div class="p-6 max-w-lg space-y-5">

    <div class="flex items-center gap-3">
      <a href="/accounting/concepts" class="text-slate-400 hover:text-slate-600 transition">
        <i class="mdi mdi-arrow-left text-xl"></i>
      </a>
      <h1 class="text-2xl font-bold text-slate-800">
        {concept ? 'Editar Concepto' : 'Nuevo Concepto Contable'}
      </h1>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <form onsubmit={(e) => { e.preventDefault(); submit() }} class="space-y-4">

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Nombre descriptivo</label>
          <input type="text" bind:value={$form.name}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
            placeholder="Ej: Cuentas por cobrar nacionales" />
          {#if $form.errors.name}
            <p class="text-red-500 text-xs mt-1">{$form.errors.name}</p>
          {/if}
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Slug / Tipo de concepto</label>
          <input type="text" bind:value={$form.type_concept}
            list="slugs-list"
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
            placeholder="Ej: 1_CXC" />
          <datalist id="slugs-list">
            {#each allSlugs as s}
              <option value={s.slug}>{s.op} — {s.desc}</option>
            {/each}
          </datalist>
          <p class="text-xs text-slate-400 mt-1">Formato: {'{opId}_{SLUG}'}, ej: 1_CXC, 14_INVENTARIO, 20_SUELDOS</p>
          {#if $form.errors.type_concept}
            <p class="text-red-500 text-xs mt-1">{$form.errors.type_concept}</p>
          {/if}
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Código de cuenta PUC</label>
          <input type="text" bind:value={$form.accountable_id}
            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500"
            placeholder="Ej: 13050501" />
          <p class="text-xs text-slate-400 mt-1">Ingresa el código auxiliar del PUC (8 dígitos recomendado)</p>
          {#if $form.errors.accountable_id}
            <p class="text-red-500 text-xs mt-1">{$form.errors.accountable_id}</p>
          {/if}
        </div>

        <div class="flex items-center gap-2">
          <input type="checkbox" bind:checked={$form.is_tax_concept} id="is_tax"
            class="w-4 h-4 rounded border-slate-300 text-blue-600" />
          <label for="is_tax" class="text-sm text-slate-700">Es concepto de impuesto</label>
        </div>

        <div class="flex gap-3 pt-2">
          <button type="submit" disabled={$form.processing}
            class="flex-1 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition disabled:opacity-50 cursor-pointer">
            {$form.processing ? 'Guardando...' : (concept ? 'Actualizar' : 'Crear concepto')}
          </button>
          <a href="/accounting/concepts"
             class="flex-1 py-2 text-center border border-slate-200 text-slate-600 text-sm rounded-lg hover:bg-slate-50 transition">
            Cancelar
          </a>
        </div>

      </form>
    </div>

    <!-- Referencia de slugs predefinidos -->
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-4">
      <h3 class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-3">Referencia de slugs</h3>
      <div class="space-y-3">
        {#each operations as op}
          <div>
            <p class="text-xs font-medium text-slate-700 mb-1">Op {op.id} — {op.name}</p>
            <div class="space-y-0.5">
              {#each op.slugs as s}
                <div class="flex items-center justify-between text-xs">
                  <span class="font-mono text-blue-600">{s.slug}</span>
                  <span class="text-slate-500">{s.desc}</span>
                  <span class="font-mono text-slate-400">{s.default}</span>
                </div>
              {/each}
            </div>
          </div>
        {/each}
      </div>
    </div>

  </div>
</AppLayout>
