<script>
  import { page, router, inertia } from '@inertiajs/svelte'
  import { onMount } from 'svelte'
  import { fade } from 'svelte/transition'
  import Toast from '@/Components/UI/Toast.svelte'

  let { children } = $props()

  // ── Modo de navegación ───────────────────────────────────────────────────────
  // 'vertical' → sidebar izquierdo (por defecto)
  // 'horizontal' → topnav completo
  let navMode = $state('vertical')
  let sidebarOpen = $state(true)
  let activeDropdown = $state(null)  // índice del grupo abierto en modo horizontal

  onMount(() => {
    const saved = localStorage.getItem('navMode')
    if (saved === 'horizontal' || saved === 'vertical') navMode = saved
  })

  function switchNavMode() {
    navMode = navMode === 'vertical' ? 'horizontal' : 'vertical'
    localStorage.setItem('navMode', navMode)
    activeDropdown = null
  }

  // ── Datos de página ──────────────────────────────────────────────────────────
  const user    = $derived($page.props.auth?.user)
  const appName = $derived($page.props.appName ?? 'NextPOS SaaS')
  const currentPath = $derived($page.url)

  // ── Grupos de navegación ─────────────────────────────────────────────────────
  const navGroups = [
    {
      label: null,
      items: [
        { href: '/dashboard', icon: 'mdi-home-city-outline', label: 'Inicio' },
      ],
    },
    {
      label: 'Ventas',
      items: [
        { href: '/invoices',      icon: 'mdi-file-document-outline', label: 'Facturación' },
        { href: '/pos',           icon: 'mdi-point-of-sale',         label: 'Punto de Venta' },
        { href: '/third-parties', icon: 'mdi-account-group-outline', label: 'Terceros' },
      ],
    },
    {
      label: 'Inventario',
      items: [
        { href: '/inventory',            icon: 'mdi-package-variant-closed', label: 'Artículos' },
        { href: '/inventory/categories', icon: 'mdi-tag-multiple-outline',    label: 'Categorías' },
        { href: '/inventory/transfers',  icon: 'mdi-transfer',                label: 'Traslados' },
        { href: '/purchases',            icon: 'mdi-cart-outline',            label: 'Compras' },
      ],
    },
    {
      label: 'Finanzas',
      items: [
        { href: '/cash',                      icon: 'mdi-bank-outline',              label: 'Caja y Bancos' },
        { href: '/accounting/journal',        icon: 'mdi-book-open-outline',         label: 'Libro Diario' },
        { href: '/accounting/ledger',         icon: 'mdi-book-multiple-outline',     label: 'Libro Mayor' },
        { href: '/accounting/trial-balance',  icon: 'mdi-scale-balance',             label: 'Balance de Prueba' },
        { href: '/accounting/income-statement',icon: 'mdi-chart-line',               label: 'Estado de Resultados' },
        { href: '/accounting/balance-sheet',  icon: 'mdi-calculator-variant-outline',label: 'Balance General' },
        { href: '/accounting/concepts',       icon: 'mdi-cog-outline',               label: 'Conceptos Contables' },
      ],
    },
    {
      label: 'Nómina',
      items: [
        { href: '/payroll/runs',      icon: 'mdi-calculator-variant',       label: 'Liquidaciones' },
        { href: '/payroll/employees', icon: 'mdi-account-hard-hat-outline', label: 'Empleados' },
        { href: '/payroll/novelties', icon: 'mdi-bell-ring-outline',        label: 'Novedades' },
        { href: '/payroll/benefits',  icon: 'mdi-cash-multiple',            label: 'Prestaciones Sociales' },
      ],
    },
    {
      label: 'Reportes',
      items: [
        { href: '/reports/sales',     icon: 'mdi-chart-bar',               label: 'Ventas' },
        { href: '/reports/cash',      icon: 'mdi-cash-register',            label: 'Caja' },
        { href: '/reports/inventory', icon: 'mdi-package-variant-closed',   label: 'Inventario' },
      ],
    },
    {
      label: 'Configuración',
      items: [
        { href: '/config/company',        icon: 'mdi-domain',                    label: 'Mi Empresa' },
        { href: '/config/resolutions',    icon: 'mdi-file-certificate-outline',  label: 'Resoluciones DIAN' },
        { href: '/config/establishments', icon: 'mdi-store-outline',             label: 'Establecimientos' },
        { href: '/config/warehouses',     icon: 'mdi-warehouse',                 label: 'Bodegas' },
      ],
    },
  ]

  // Todos los hrefs del nav (para detectar coincidencias más específicas)
  const allNavHrefs = $derived(navGroups.flatMap(g => g.items.map(i => i.href)))

  function isActive(href) {
    if (currentPath === href) return true
    if (!currentPath.startsWith(href + '/')) return false
    // No marcar activo si un item más específico también coincide con la ruta actual
    return !allNavHrefs.some(other => other !== href && currentPath.startsWith(other))
  }

  function groupHasActive(group) {
    return group.items.some(item => isActive(item.href))
  }

  function logout() {
    router.post('/logout')
  }

  // ── Grupos colapsables en sidebar vertical ───────────────────────────────────
  // Mapa: label → true/false (expandido/colapsado)
  let collapsedGroups = $state({})

  onMount(() => {
    // Restaurar estado desde localStorage
    try {
      const saved = JSON.parse(localStorage.getItem('sidebarGroups') ?? '{}')
      collapsedGroups = saved
    } catch {}
  })

  function toggleGroup(label) {
    collapsedGroups = { ...collapsedGroups, [label]: !collapsedGroups[label] }
    localStorage.setItem('sidebarGroups', JSON.stringify(collapsedGroups))
  }

  // Un grupo está abierto si: tiene un item activo, O no fue explícitamente cerrado
  function isGroupOpen(group) {
    if (!group.label) return true
    if (groupHasActive(group)) return true          // siempre abierto si tiene item activo
    return collapsedGroups[group.label] !== true    // cerrado solo si se marcó como cerrado
  }

  // Dropdown en modo horizontal
  function toggleDropdown(idx) {
    activeDropdown = activeDropdown === idx ? null : idx
  }

  function closeDropdowns() {
    activeDropdown = null
  }
</script>

<!-- ══════════════════════════════════════════════════════════════════════════════
     MODO VERTICAL (sidebar)
═══════════════════════════════════════════════════════════════════════════════ -->
{#if navMode === 'vertical'}

<div class="flex h-screen bg-body overflow-hidden">

  <!-- Sidebar azul -->
  <aside class="flex flex-col bg-primary-dark transition-all duration-300 shrink-0 {sidebarOpen ? 'w-60' : 'w-16'}">

    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 h-14 border-b border-white/10 shrink-0">
      <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center shrink-0">
        <i class="mdi mdi-lightning-bolt text-white text-base"></i>
      </div>
      {#if sidebarOpen}
        <div class="overflow-hidden">
          <span class="text-white text-sm font-bold leading-none whitespace-nowrap">NextPOS</span>
          <span class="text-blue-200 text-sm font-light"> SaaS</span>
        </div>
      {/if}
    </div>

    <!-- Navegación -->
    <nav class="flex-1 overflow-y-auto py-2 px-2 space-y-0.5">
      {#each navGroups as group}
        {#if !group.label}
          <!-- Items sin grupo (Inicio) — siempre visibles -->
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-colors group
                {isActive(item.href)
                  ? 'bg-white/20 text-white font-medium'
                  : 'text-blue-100 hover:bg-white/10 hover:text-white'}"
              title={!sidebarOpen ? item.label : undefined}
            >
              <i class="mdi {item.icon} text-lg shrink-0 {isActive(item.href) ? 'text-white' : 'text-blue-200 group-hover:text-white'}"></i>
              {#if sidebarOpen}
                <span class="truncate">{item.label}</span>
              {/if}
            </a>
          {/each}

        {:else if sidebarOpen}
          <!-- Grupo colapsable (sidebar abierto) -->
          <div class="pt-1">
            <button
              onclick={() => toggleGroup(group.label)}
              class="w-full flex items-center justify-between px-2 py-1.5 rounded-lg cursor-pointer transition-colors
                {groupHasActive(group) ? 'text-white' : 'text-blue-300 hover:text-blue-100'}"
            >
              <span class="text-[10px] font-bold uppercase tracking-wider">{group.label}</span>
              <i class="mdi text-sm transition-transform duration-200
                {isGroupOpen(group) ? 'mdi-chevron-down' : 'mdi-chevron-right'}
                {groupHasActive(group) ? 'text-blue-200' : 'text-blue-400'}">
              </i>
            </button>

            {#if isGroupOpen(group)}
              <ul class="mt-0.5 space-y-0.5">
                {#each group.items as item}
                  <li>
                    <a
                      use:inertia
                      href={item.href}
                      class="flex items-center gap-2.5 pl-3 pr-2 py-1.5 rounded-lg text-sm transition-colors group
                        {isActive(item.href)
                          ? 'bg-white/20 text-white font-medium'
                          : 'text-blue-100 hover:bg-white/10 hover:text-white'}"
                    >
                      <i class="mdi {item.icon} text-base shrink-0 {isActive(item.href) ? 'text-white' : 'text-blue-300 group-hover:text-white'}"></i>
                      <span class="truncate">{item.label}</span>
                    </a>
                  </li>
                {/each}
              </ul>
            {/if}
          </div>

        {:else}
          <!-- Sidebar colapsado → solo iconos con tooltip, agrupar con separador -->
          <div class="border-t border-white/10 mx-1 my-1"></div>
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center justify-center w-10 h-10 mx-auto rounded-lg text-sm transition-colors group
                {isActive(item.href)
                  ? 'bg-white/20 text-white'
                  : 'text-blue-200 hover:bg-white/10 hover:text-white'}"
              title={item.label}
            >
              <i class="mdi {item.icon} text-lg"></i>
            </a>
          {/each}
        {/if}
      {/each}
    </nav>

    <!-- Usuario (pie del sidebar) -->
    <div class="border-t border-white/10 p-3 shrink-0">
      {#if sidebarOpen}
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center shrink-0">
            <span class="text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </span>
          </div>
          <div class="flex-1 overflow-hidden">
            <p class="text-white text-xs font-medium truncate">{user?.name ?? 'Usuario'}</p>
            <p class="text-blue-300 text-[10px] truncate">{user?.email ?? ''}</p>
          </div>
          <button onclick={logout} class="text-blue-300 hover:text-red-300 transition cursor-pointer" title="Cerrar sesión">
            <i class="mdi mdi-logout text-base"></i>
          </button>
        </div>
      {:else}
        <button onclick={logout} class="w-full flex justify-center text-blue-300 hover:text-red-300 transition cursor-pointer" title="Cerrar sesión">
          <i class="mdi mdi-logout text-base"></i>
        </button>
      {/if}
    </div>
  </aside>

  <!-- Contenido principal -->
  <div class="flex flex-col flex-1 overflow-hidden">

    <!-- Topbar -->
    <header class="flex items-center justify-between bg-white border-b border-slate-200 px-4 h-14 shrink-0 shadow-sm">

      <div class="flex items-center gap-3">
        <!-- Colapsar sidebar -->
        <button
          onclick={() => sidebarOpen = !sidebarOpen}
          aria-label="Alternar menú lateral"
          class="text-slate-500 hover:text-primary transition cursor-pointer"
        >
          <i class="mdi mdi-menu text-xl"></i>
        </button>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-slate-400 text-xs hidden sm:block font-medium">{appName}</span>

        <!-- Botón cambiar a modo horizontal -->
        <button
          onclick={switchNavMode}
          title="Cambiar a menú horizontal"
          class="text-slate-400 hover:text-primary transition cursor-pointer"
        >
          <i class="mdi mdi-monitor-dashboard text-xl"></i>
        </button>

        <!-- Avatar usuario -->
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 bg-primary rounded-full flex items-center justify-center">
            <span class="text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </span>
          </div>
          <span class="text-slate-700 text-sm font-medium hidden sm:block">{user?.name ?? ''}</span>
        </div>
      </div>
    </header>

    <!-- Área de contenido -->
    <main class="flex-1 overflow-y-auto p-6" in:fade={{ duration: 150 }}>
      {@render children()}
    </main>

  </div>
</div>

{/if}


<!-- ══════════════════════════════════════════════════════════════════════════════
     MODO HORIZONTAL (topnav)
═══════════════════════════════════════════════════════════════════════════════ -->
{#if navMode === 'horizontal'}


<div class="flex flex-col h-screen bg-body overflow-hidden">

  <!-- Barra de navegación superior azul -->
  <header class="bg-primary-dark shrink-0 shadow-md overflow-visible">

    <!-- Primera fila: logo + controles de usuario -->
    <div class="flex items-center justify-between px-4 h-14 border-b border-white/10">

      <!-- Logo -->
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center shrink-0">
          <i class="mdi mdi-lightning-bolt text-white text-base"></i>
        </div>
        <div>
          <span class="text-white text-sm font-bold">NextPOS</span>
          <span class="text-blue-200 text-sm font-light"> SaaS</span>
        </div>
      </div>

      <!-- Derecha: nombre app + toggle + usuario + logout -->
      <div class="flex items-center gap-4">
        <span class="text-blue-200 text-xs hidden md:block">{appName}</span>

        <!-- Botón cambiar a modo vertical -->
        <button
          onclick={switchNavMode}
          title="Cambiar a menú lateral"
          class="text-blue-200 hover:text-white transition cursor-pointer"
        >
          <i class="mdi mdi-view-split-vertical text-xl"></i>
        </button>

        <!-- Avatar + nombre -->
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 bg-white/20 rounded-full flex items-center justify-center">
            <span class="text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </span>
          </div>
          <span class="text-white text-sm font-medium hidden sm:block">{user?.name ?? ''}</span>
        </div>

        <!-- Logout -->
        <button onclick={logout} title="Cerrar sesión" class="text-blue-200 hover:text-red-300 transition cursor-pointer">
          <i class="mdi mdi-logout text-lg"></i>
        </button>
      </div>
    </div>

    <!-- Segunda fila: grupos de navegación -->
    <nav class="flex items-center px-2 h-10 gap-1">

      {#each navGroups as group, idx}
        {#if !group.label}
          <!-- Ítems sin grupo van directo (ej: Inicio) -->
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center gap-1.5 px-3 py-1.5 rounded text-sm whitespace-nowrap transition-colors
                {isActive(item.href)
                  ? 'bg-white/20 text-white font-medium'
                  : 'text-blue-100 hover:bg-white/10 hover:text-white'}"
            >
              <i class="mdi {item.icon} text-base"></i>
              <span>{item.label}</span>
            </a>
          {/each}
        {:else}
          <!-- Grupos con label → dropdown -->
          <div class="relative">
            <button
              onclick={() => toggleDropdown(idx)}
              class="flex items-center gap-1.5 px-3 py-1.5 rounded text-sm whitespace-nowrap transition-colors cursor-pointer
                {groupHasActive(group) || activeDropdown === idx
                  ? 'bg-white/20 text-white font-medium'
                  : 'text-blue-100 hover:bg-white/10 hover:text-white'}"
            >
              <i class="mdi {group.items[0].icon} text-base"></i>
              <span>{group.label}</span>
              <i class="mdi mdi-chevron-down text-sm transition-transform {activeDropdown === idx ? 'rotate-180' : ''}"></i>
            </button>

            {#if activeDropdown === idx}
              <!-- Overlay invisible: clic fuera cierra el dropdown -->
              <div class="fixed inset-0 z-40" onclick={closeDropdowns}></div>
              <!-- Panel del dropdown (z-50 > z-40 para quedar encima del overlay) -->
              <div class="absolute top-full left-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-slate-100 py-1 z-50">
                {#each group.items as item}
                  <a
                    use:inertia
                    href={item.href}
                    onclick={closeDropdowns}
                    class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors
                      {isActive(item.href)
                        ? 'bg-primary-soft text-primary-dark font-medium'
                        : 'text-slate-700 hover:bg-slate-50 hover:text-primary'}"
                  >
                    <i class="mdi {item.icon} text-base {isActive(item.href) ? 'text-primary' : 'text-slate-400'}"></i>
                    {item.label}
                  </a>
                {/each}
              </div>
            {/if}
          </div>
        {/if}
      {/each}

    </nav>
  </header>

  <!-- Área de contenido -->
  <main class="flex-1 overflow-y-auto p-6">
    {@render children()}
  </main>

</div>

{/if}

<!-- Toast global: se renderiza una sola vez, aplica a ambos modos de navegación -->
<Toast />
