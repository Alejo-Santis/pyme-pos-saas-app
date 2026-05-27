<script>
  import { inertia } from '@inertiajs/svelte'
  import AppLayout from '@/Layouts/AppLayout.svelte'

  let { setup = {} } = $props()

  const steps = $derived(setup.steps ?? [])
  const groups = $derived([...new Set(steps.map(step => step.group))])

  function groupSteps(group) {
    return steps.filter(step => step.group === group)
  }
</script>

<AppLayout>
  <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Primeros pasos</h1>
      <p class="mt-1 text-sm text-slate-500">
        Configura lo necesario para empezar a vender, importar datos y operar el ERP con orden.
      </p>
    </div>
    <div class="min-w-56 rounded-lg border border-slate-200 bg-white px-4 py-3 shadow-sm">
      <div class="flex items-center justify-between text-xs font-semibold text-slate-500">
        <span>Progreso</span>
        <span>{setup.completed ?? 0}/{setup.total ?? 0}</span>
      </div>
      <div class="mt-2 h-2 rounded-full bg-slate-100">
        <div
          class="h-2 rounded-full bg-emerald-500 transition-all"
          style="width: {setup.percent ?? 0}%"
        ></div>
      </div>
      <div class="mt-2 text-right text-sm font-bold text-slate-700">{setup.percent ?? 0}%</div>
    </div>
  </div>

  {#if setup.next_step}
    <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-5 py-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600">
            <i class="mdi {setup.next_step.icon} text-xl text-white"></i>
          </div>
          <div>
            <p class="text-xs font-bold uppercase text-blue-600">Siguiente paso</p>
            <h2 class="text-base font-bold text-slate-800">{setup.next_step.title}</h2>
            <p class="mt-0.5 text-sm text-slate-600">{setup.next_step.description}</p>
          </div>
        </div>
        <a
          use:inertia
          href={setup.next_step.href}
          class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-700"
        >
          <span>{setup.next_step.action}</span>
          <i class="mdi mdi-arrow-right text-base"></i>
        </a>
      </div>
    </div>
  {/if}

  <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
    {#each groups as group}
      <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
          <h2 class="text-sm font-bold text-slate-800">{group}</h2>
        </div>
        <div class="divide-y divide-slate-100">
          {#each groupSteps(group) as step}
            <div class="flex gap-4 px-5 py-4">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {step.completed ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'}">
                <i class="mdi {step.completed ? 'mdi-check' : step.icon} text-xl"></i>
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="text-sm font-bold text-slate-800">{step.title}</h3>
                  {#if step.optional}
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">Opcional</span>
                  {/if}
                  {#if step.completed}
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Listo</span>
                  {/if}
                </div>
                <p class="mt-1 text-sm text-slate-500">{step.description}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <a
                    use:inertia
                    href={step.href}
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                  >
                    <i class="mdi mdi-open-in-new text-sm"></i>
                    <span>{step.action}</span>
                  </a>
                  {#if step.secondary_href}
                    <a
                      href={step.secondary_href}
                      class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-700"
                    >
                      <i class="mdi mdi-download text-sm"></i>
                      <span>Plantilla Excel</span>
                    </a>
                  {/if}
                </div>
              </div>
            </div>
          {/each}
        </div>
      </section>
    {/each}
  </div>
</AppLayout>
