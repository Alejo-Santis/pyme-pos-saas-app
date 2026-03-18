<script>
  import { router } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { concepts, operations } = $props()

  // Mapear conceptos existentes por type_concept para fácil acceso
  let conceptMap = $derived(
    Object.fromEntries(concepts.map(c => [c.type_concept, c]))
  )

  // Conceptos que no pertenecen a ningún slug predefinido
  let allDefinedSlugs = $derived(operations.flatMap(o => o.slugs.map(s => s.slug)))
  let customConcepts  = $derived(concepts.filter(c => !allDefinedSlugs.includes(c.type_concept)))

  function destroy(id) {
    if (!confirm('¿Eliminar este concepto?')) return
    router.delete(`/accounting/concepts/${id}`, { preserveScroll: true })
  }
</script>

<AppLayout title="Conceptos Contables">
  <div class="p-6 space-y-5">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Conceptos Contables</h1>
        <p class="text-sm text-slate-500 mt-0.5">Configure las cuentas del PUC para cada operación</p>
      </div>
      <a href="/accounting/concepts/create"
         class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
        <i class="mdi mdi-plus"></i> Nuevo concepto
      </a>
    </div>

    <!-- Por operación -->
    {#each operations as op}
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200 flex items-center gap-2">
          <span class="text-xs font-bold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Op {op.id}</span>
          <h2 class="text-sm font-semibold text-slate-700">{op.name}</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="text-left text-slate-500 text-xs">
            <tr>
              <th class="px-5 py-2 font-medium">Concepto</th>
              <th class="px-5 py-2 font-medium">Slug</th>
              <th class="px-5 py-2 font-medium">Cuenta PUC</th>
              <th class="px-5 py-2 font-medium">Cuenta por defecto</th>
              <th class="px-5 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each op.slugs as s}
              {@const existing = conceptMap[s.slug]}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-5 py-2.5 text-slate-700">{s.desc}</td>
                <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{s.slug}</td>
                <td class="px-5 py-2.5">
                  {#if existing}
                    <span class="font-mono font-semibold text-blue-700">{existing.accountable_id}</span>
                    <span class="text-xs text-slate-500 ml-1">— {existing.name}</span>
                  {:else}
                    <span class="text-slate-400 text-xs italic">No configurado (usa defecto)</span>
                  {/if}
                </td>
                <td class="px-5 py-2.5 font-mono text-xs text-slate-400">{s.default}</td>
                <td class="px-5 py-2.5 text-right">
                  <div class="flex items-center justify-end gap-1">
                    {#if existing}
                      <a href="/accounting/concepts/{existing.id}/edit"
                         class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition"
                         title="Editar">
                        <i class="mdi mdi-pencil-outline text-base"></i>
                      </a>
                      <button onclick={() => destroy(existing.id)}
                        class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer"
                        title="Eliminar">
                        <i class="mdi mdi-trash-can-outline text-base"></i>
                      </button>
                    {:else}
                      <a href={`/accounting/concepts/create?slug=${s.slug}&desc=${encodeURIComponent(s.desc)}&default=${s.default}`}
                         class="px-2 py-1 text-xs border border-slate-300 text-slate-600 rounded hover:bg-slate-50 transition">
                        Configurar
                      </a>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/each}

    <!-- Conceptos personalizados (fuera de los slugs predefinidos) -->
    {#if customConcepts.length > 0}
      <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 bg-slate-50 border-b border-slate-200">
          <h2 class="text-sm font-semibold text-slate-700">Conceptos personalizados</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="text-left text-slate-500 text-xs">
            <tr>
              <th class="px-5 py-2 font-medium">Nombre</th>
              <th class="px-5 py-2 font-medium">Slug</th>
              <th class="px-5 py-2 font-medium">Cuenta PUC</th>
              <th class="px-5 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {#each customConcepts as c}
              <tr class="hover:bg-slate-50 transition">
                <td class="px-5 py-2.5 text-slate-700">{c.name}</td>
                <td class="px-5 py-2.5 font-mono text-xs text-slate-500">{c.type_concept}</td>
                <td class="px-5 py-2.5 font-mono font-semibold text-blue-700">{c.accountable_id}</td>
                <td class="px-5 py-2.5 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a href="/accounting/concepts/{c.id}/edit"
                       class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition">
                      <i class="mdi mdi-pencil-outline text-base"></i>
                    </a>
                    <button onclick={() => destroy(c.id)}
                      class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded transition cursor-pointer">
                      <i class="mdi mdi-trash-can-outline text-base"></i>
                    </button>
                  </div>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}

  </div>
</AppLayout>
