<script>
  import { page, router, inertia } from '@inertiajs/svelte'
  import { onMount } from 'svelte'
  import { fade } from 'svelte/transition'
  import BrandLogo from '@/Components/BrandLogo.svelte'
  import Toast from '@/Components/UI/Toast.svelte'
  import BrandMark from '@/Components/UI/BrandMark.svelte'

  let { children } = $props()

  let navMode        = $state('vertical')
  let sidebarOpen    = $state(true)
  let activeDropdown = $state(null)
  let userMenuOpen   = $state(false)

  onMount(() => {
    const saved = localStorage.getItem('navMode')
    if (saved === 'horizontal' || saved === 'vertical') navMode = saved
  })

  function switchNavMode() {
    navMode = navMode === 'vertical' ? 'horizontal' : 'vertical'
    localStorage.setItem('navMode', navMode)
    activeDropdown = null
  }

  const user    = $derived($page.props.auth?.user)
  const appName = $derived($page.props.appName ?? 'PyME POS SaaS')
  const impersonation = $derived($page.props.impersonation)
  const currentPath = $derived($page.url)

  const navGroups = [
    {
      label: null,
      items: [
        { href: '/dashboard', icon: 'mdi-home-outline', label: 'Inicio' },
        { href: '/setup',     icon: 'mdi-map-check-outline', label: 'Primeros pasos' },
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
        { href: '/inventory/categories', icon: 'mdi-tag-multiple-outline',   label: 'Categorías' },
        { href: '/inventory/transfers',  icon: 'mdi-transfer',               label: 'Traslados' },
        { href: '/purchases',            icon: 'mdi-cart-outline',           label: 'Compras' },
        { href: '/tax-mailbox',          icon: 'mdi-email-outline',           label: 'Buzón Tributario' },
      ],
    },
    {
      label: 'Finanzas',
      items: [
        { href: '/cash',                       icon: 'mdi-bank-outline',               label: 'Caja y Bancos' },
        { href: '/accounting/audit',           icon: 'mdi-clipboard-check-outline',     label: 'Auditoría Contable' },
        { href: '/accounting/auxiliary',       icon: 'mdi-file-tree-outline',           label: 'Auxiliar Terceros' },
        { href: '/accounting/differences',     icon: 'mdi-alert-decagram-outline',      label: 'Diferencias' },
        { href: '/accounting/adjustments',     icon: 'mdi-file-restore-outline',        label: 'Ajustes Contables' },
        { href: '/accounting/journal',         icon: 'mdi-book-open-outline',          label: 'Libro Diario' },
        { href: '/accounting/ledger',          icon: 'mdi-book-multiple-outline',      label: 'Libro Mayor' },
        { href: '/accounting/trial-balance',   icon: 'mdi-scale-balance',              label: 'Balance de Prueba' },
        { href: '/accounting/income-statement',icon: 'mdi-chart-line',                 label: 'Estado de Resultados' },
        { href: '/accounting/balance-sheet',   icon: 'mdi-calculator-variant-outline', label: 'Balance General' },
        { href: '/accounting/concepts',        icon: 'mdi-cog-outline',                label: 'Conceptos Contables' },
      ],
    },
    {
      label: 'Nómina',
      items: [
        { href: '/payroll/runs',      icon: 'mdi-calculator-variant',       label: 'Liquidaciones' },
        { href: '/payroll/employees', icon: 'mdi-account-hard-hat-outline', label: 'Empleados' },
        { href: '/payroll/novelties', icon: 'mdi-bell-ring-outline',        label: 'Novedades' },
        { href: '/payroll/benefits',  icon: 'mdi-cash-multiple',            label: 'Prestaciones' },
      ],
    },
    {
      label: 'Reportes',
      items: [
        { href: '/reports/sales',     icon: 'mdi-chart-bar',             label: 'Ventas' },
        { href: '/reports/receivables',icon: 'mdi-account-cash-outline',  label: 'Cartera Clientes' },
        { href: '/reports/payables',   icon: 'mdi-file-clock-outline',    label: 'Cuentas por Pagar' },
        { href: '/reports/cash',      icon: 'mdi-cash-register',         label: 'Caja' },
        { href: '/reports/inventory', icon: 'mdi-package-variant-closed',label: 'Inventario' },
      ],
    },
    {
      label: 'Configuración',
      items: [
        { href: '/config/company',        icon: 'mdi-domain',                   label: 'Mi Empresa' },
        { href: '/config/resolutions',    icon: 'mdi-file-certificate-outline', label: 'Resoluciones DIAN' },
        { href: '/config/establishments', icon: 'mdi-store-outline',            label: 'Establecimientos' },
        { href: '/config/warehouses',     icon: 'mdi-warehouse',                label: 'Bodegas' },
        { href: '/users',                 icon: 'mdi-account-multiple-outline', label: 'Usuarios' },
        { href: '/subscription',          icon: 'mdi-crown-outline',            label: 'Mi Suscripción' },
      ],
    },
    {
      label: 'Auditoría',
      items: [
        { href: '/audit/activity', icon: 'mdi-shield-check-outline',   label: 'Log de Actividad' },
        { href: '/audit/api-logs', icon: 'mdi-api',                    label: 'API DIAN' },
      ],
    },
  ]

  const allNavHrefs = $derived(navGroups.flatMap(g => g.items.map(i => i.href)))

  function isActive(href) {
    if (currentPath === href) return true
    if (!currentPath.startsWith(href + '/')) return false
    return !allNavHrefs.some(other => other !== href && currentPath.startsWith(other))
  }

  function groupHasActive(group) {
    return group.items.some(item => isActive(item.href))
  }

  function logout() {
    router.post('/logout')
  }

  // Acordeón: solo un grupo abierto a la vez, para mantener el foco en la
  // sección donde estás. Por defecto se abre el grupo de la ruta activa;
  // al navegar a otra página se descarta cualquier apertura manual y el
  // acordeón vuelve a seguir la selección actual.
  const activeGroupLabel = $derived(navGroups.find(g => g.label && groupHasActive(g))?.label ?? null)

  let openGroupOverride = $state(null)

  $effect(() => {
    currentPath
    openGroupOverride = null
  })

  function toggleGroup(label) {
    const currentlyOpen = openGroupOverride ?? activeGroupLabel
    openGroupOverride = currentlyOpen === label ? '__none__' : label
  }

  function isGroupOpen(group) {
    if (!group.label) return true
    const target = openGroupOverride ?? activeGroupLabel
    return target === group.label
  }

  function toggleDropdown(idx) {
    activeDropdown = activeDropdown === idx ? null : idx
  }

  function closeDropdowns() {
    activeDropdown = null
  }

  function toggleUserMenu() {
    userMenuOpen = !userMenuOpen
  }

  function closeUserMenu() {
    userMenuOpen = false
  }

  // Color de avatar determinístico basado en el nombre
  const avatarColors = [
    '#2563eb', '#7c3aed', '#059669', '#d97706',
    '#dc2626', '#0891b2', '#be185d', '#65a30d',
  ]
  const avatarColor = $derived(() => {
    const name = user?.name ?? 'U'
    const idx  = name.charCodeAt(0) % avatarColors.length
    return avatarColors[idx]
  })

  const userInitials = $derived(
    (user?.name ?? 'U')
      .split(' ')
      .slice(0, 2)
      .map(w => w[0]?.toUpperCase() ?? '')
      .join('')
  )
</script>

<!-- ══════════════════════════════════════════════════════════
     MODO VERTICAL (sidebar light)
═══════════════════════════════════════════════════════════ -->
{#if navMode === 'vertical'}

<div class="flex h-screen bg-slate-50 overflow-hidden">

  <!-- Sidebar blanco con borde y acento izquierdo -->
  <aside class="flex flex-col bg-white border-r border-slate-200 shadow-sm transition-all duration-300 shrink-0 {sidebarOpen ? 'w-60' : 'w-16'}">

    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 h-14 border-b border-slate-100 shrink-0">
      {#if sidebarOpen}
        <BrandLogo size="sm" />
      {:else}
        <BrandLogo variant="mark" size="md" />
      {/if}
    </div>

    <!-- Navegación -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
      {#each navGroups as group}
        {#if !group.label}
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-colors group
                {isActive(item.href)
                  ? 'bg-primary/10 text-primary font-semibold'
                  : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
              title={!sidebarOpen ? item.label : undefined}
            >
              <i class="mdi {item.icon} text-lg shrink-0
                {isActive(item.href) ? 'text-primary' : 'text-slate-400 group-hover:text-slate-600'}"></i>
              {#if sidebarOpen}
                <span class="truncate">{item.label}</span>
              {/if}
            </a>
          {/each}

        {:else if sidebarOpen}
          <div class="pt-1">
            <button
              onclick={() => toggleGroup(group.label)}
              class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg cursor-pointer transition-colors
                {groupHasActive(group) ? 'text-primary' : 'text-slate-400 hover:text-slate-600'}"
            >
              <span class="text-[10px] font-bold uppercase tracking-wider">{group.label}</span>
              <i class="mdi text-sm transition-transform duration-200
                {isGroupOpen(group) ? 'mdi-chevron-down' : 'mdi-chevron-right'}"></i>
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
                          ? 'bg-primary/10 text-primary font-semibold'
                          : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
                    >
                      <i class="mdi {item.icon} text-base shrink-0
                        {isActive(item.href) ? 'text-primary' : 'text-slate-400 group-hover:text-slate-600'}"></i>
                      <span class="truncate">{item.label}</span>
                    </a>
                  </li>
                {/each}
              </ul>
            {/if}
          </div>

        {:else}
          <!-- Sidebar colapsado → solo iconos con tooltip, agrupar con separador -->
          <div class="border-t border-slate-100 mx-1 my-1"></div>
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center justify-center w-10 h-10 mx-auto rounded-lg transition-colors group
                {isActive(item.href)
                  ? 'bg-primary/10 text-primary'
                  : 'text-slate-400 hover:bg-slate-50 hover:text-slate-700'}"
              title={item.label}
            >
              <i class="mdi {item.icon} text-lg"></i>
            </a>
          {/each}
        {/if}
      {/each}
    </nav>

    <!-- Usuario (pie) -->
    <div class="border-t border-slate-100 p-3 shrink-0 relative">
      {#if sidebarOpen}
        <button
          onclick={toggleUserMenu}
          class="w-full flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 transition cursor-pointer group"
        >
          <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0"
               style="background:{avatarColor()}">
            <span class="text-white text-xs font-bold">{userInitials}</span>
          </div>
          <div class="flex-1 overflow-hidden text-left">
            <p class="text-slate-700 text-xs font-semibold truncate">{user?.name ?? 'Usuario'}</p>
            <p class="text-slate-400 text-[10px] truncate">{user?.email ?? ''}</p>
          </div>
          <i class="mdi mdi-dots-vertical text-slate-400 group-hover:text-slate-600 text-base shrink-0"></i>
        </button>
      {:else}
        <button
          onclick={toggleUserMenu}
          class="w-full flex justify-center p-1 rounded-lg hover:bg-slate-50 transition cursor-pointer"
          title="{user?.name ?? 'Usuario'}"
        >
          <div class="w-8 h-8 rounded-full flex items-center justify-center"
               style="background:{avatarColor()}">
            <span class="text-white text-xs font-bold">{userInitials}</span>
          </div>
        </button>
      {/if}

      <!-- Menú desplegable del usuario -->
      {#if userMenuOpen}
        <div class="fixed inset-0 z-40" onclick={closeUserMenu}></div>
        <div class="absolute bottom-full left-3 right-3 mb-1 bg-white rounded-xl shadow-lg border border-slate-100 py-1.5 z-50">
          <div class="px-3 py-2 border-b border-slate-100 mb-1">
            <p class="text-slate-800 text-xs font-bold truncate">{user?.name ?? 'Usuario'}</p>
            <p class="text-slate-400 text-[11px] truncate">{user?.email ?? ''}</p>
          </div>
          <a use:inertia href="/profile" onclick={closeUserMenu}
             class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition">
            <i class="mdi mdi-account-edit-outline text-base text-slate-400"></i>
            Mi Perfil
          </a>
          <a use:inertia href="/config/company" onclick={closeUserMenu}
             class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition">
            <i class="mdi mdi-domain text-base text-slate-400"></i>
            Mi Empresa
          </a>
          <div class="border-t border-slate-100 mt-1 pt-1">
            <button onclick={logout}
               class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-500 hover:bg-red-50 transition cursor-pointer">
              <i class="mdi mdi-logout text-base"></i>
              Cerrar sesión
            </button>
          </div>
        </div>
      {/if}
    </div>
  </aside>

  <!-- Contenido principal -->
  <div class="flex flex-col flex-1 overflow-hidden">

    <!-- Topbar -->
    <header class="flex items-center justify-between bg-white border-b border-slate-200 px-4 h-14 shrink-0">
      <div class="flex items-center gap-3">
        <button
          onclick={() => sidebarOpen = !sidebarOpen}
          aria-label="Alternar menú lateral"
          class="text-slate-400 hover:text-primary transition cursor-pointer p-1 rounded-lg hover:bg-slate-50"
        >
          <i class="mdi mdi-menu text-xl"></i>
        </button>
        <!-- Breadcrumb simple -->
        <span class="text-slate-400 text-xs hidden sm:block">{appName}</span>
      </div>

      <div class="flex items-center gap-2">
        <!-- Cambiar modo nav -->
        <button
          onclick={switchNavMode}
          title="Cambiar a menú horizontal"
          class="text-slate-400 hover:text-primary transition cursor-pointer p-1.5 rounded-lg hover:bg-slate-50"
        >
          <i class="mdi mdi-monitor-dashboard text-lg"></i>
        </button>
      </div>
    </header>

    <!-- Contenido -->
    <main class="flex-1 overflow-y-auto p-5 bg-slate-50" in:fade={{ duration: 150 }}>
      {#if impersonation}
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-center gap-2">
          <i class="mdi mdi-account-switch-outline text-lg"></i>
          <span>Sesión de soporte iniciada por {impersonation.admin_name ?? impersonation.admin_email}.</span>
        </div>
      {/if}
      {@render children()}
    </main>

  </div>
</div>

{/if}


<!-- ══════════════════════════════════════════════════════════
     MODO HORIZONTAL (topnav light)
═══════════════════════════════════════════════════════════ -->
{#if navMode === 'horizontal'}

<div class="flex flex-col h-screen bg-slate-50 overflow-hidden">

  <!-- Barra superior blanca con borde inferior -->
  <header class="bg-white border-b border-slate-200 shrink-0 shadow-sm overflow-visible">

    <!-- Primera fila: logo + usuario -->
    <div class="flex items-center justify-between px-4 h-14 border-b border-slate-100">

      <!-- Logo -->
      <div class="flex items-center gap-2.5">
        <BrandLogo size="sm" />
      </div>

      <!-- Derecha -->
      <div class="flex items-center gap-3">
        <span class="text-slate-400 text-xs hidden md:block">{appName}</span>

        <button
          onclick={switchNavMode}
          title="Cambiar a menú lateral"
          class="text-slate-400 hover:text-primary transition cursor-pointer p-1.5 rounded-lg hover:bg-slate-50"
        >
          <i class="mdi mdi-view-split-vertical text-lg"></i>
        </button>

        <!-- Avatar con dropdown -->
        <div class="relative">
          <button
            onclick={toggleUserMenu}
            class="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-50 transition cursor-pointer"
          >
            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                 style="background:{avatarColor()}">
              <span class="text-white text-xs font-bold">{userInitials}</span>
            </div>
            <span class="text-slate-700 text-sm font-medium hidden sm:block">{user?.name ?? ''}</span>
            <i class="mdi mdi-chevron-down text-slate-400 text-sm hidden sm:block transition-transform {userMenuOpen ? 'rotate-180' : ''}"></i>
          </button>

          {#if userMenuOpen}
            <div class="fixed inset-0 z-40" onclick={closeUserMenu}></div>
            <div class="absolute top-full right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-slate-100 py-1.5 z-50">
              <div class="px-3 py-2 border-b border-slate-100 mb-1">
                <p class="text-slate-800 text-xs font-bold truncate">{user?.name ?? 'Usuario'}</p>
                <p class="text-slate-400 text-[11px] truncate">{user?.email ?? ''}</p>
              </div>
              <a use:inertia href="/profile" onclick={closeUserMenu}
                 class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition">
                <i class="mdi mdi-account-edit-outline text-base text-slate-400"></i>
                Mi Perfil
              </a>
              <a use:inertia href="/config/company" onclick={closeUserMenu}
                 class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 hover:text-primary transition">
                <i class="mdi mdi-domain text-base text-slate-400"></i>
                Mi Empresa
              </a>
              <div class="border-t border-slate-100 mt-1 pt-1">
                <button onclick={logout}
                   class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-500 hover:bg-red-50 transition cursor-pointer">
                  <i class="mdi mdi-logout text-base"></i>
                  Cerrar sesión
                </button>
              </div>
            </div>
          {/if}
        </div>
      </div>
    </div>

    <!-- Segunda fila: navegación horizontal -->
    <nav class="flex items-center px-2 h-10 gap-0.5 overflow-x-auto">
      {#each navGroups as group, idx}
        {#if !group.label}
          {#each group.items as item}
            <a
              use:inertia
              href={item.href}
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm whitespace-nowrap transition-colors
                {isActive(item.href)
                  ? 'bg-primary/10 text-primary font-semibold'
                  : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
              <i class="mdi {item.icon} text-base"></i>
              <span>{item.label}</span>
            </a>
          {/each}
        {:else}
          <div class="relative">
            <button
              onclick={() => toggleDropdown(idx)}
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm whitespace-nowrap transition-colors cursor-pointer
                {groupHasActive(group) || activeDropdown === idx
                  ? 'bg-primary/10 text-primary font-semibold'
                  : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
              <i class="mdi {group.items[0].icon} text-base"></i>
              <span>{group.label}</span>
              <i class="mdi mdi-chevron-down text-sm transition-transform {activeDropdown === idx ? 'rotate-180' : ''}"></i>
            </button>

            {#if activeDropdown === idx}
              <div class="fixed inset-0 z-40" onclick={closeDropdowns}></div>
              <div class="absolute top-full left-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-slate-100 py-1.5 z-50">
                {#each group.items as item}
                  <a
                    use:inertia
                    href={item.href}
                    onclick={closeDropdowns}
                    class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors
                      {isActive(item.href)
                        ? 'bg-primary/10 text-primary font-semibold'
                        : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'}"
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

  <!-- Contenido -->
  <main class="flex-1 overflow-y-auto p-5 bg-slate-50">
    {#if impersonation}
      <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 flex items-center gap-2">
        <i class="mdi mdi-account-switch-outline text-lg"></i>
        <span>Sesión de soporte iniciada por {impersonation.admin_name ?? impersonation.admin_email}.</span>
      </div>
    {/if}
    {@render children()}
  </main>

</div>

{/if}

<Toast />
