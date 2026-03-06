<script>
  import { page, router } from '@inertiajs/svelte'

  let { children } = $props()

  let sidebarOpen = $state(true)

  const user = $derived($page.props.auth?.user)
  const appName = $derived($page.props.appName ?? 'NextPOS SaaS')

  const currentPath = $derived($page.url)

  // Grupos de navegación
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
        { href: '/invoices',      icon: 'mdi-file-document-outline',  label: 'Facturación' },
        { href: '/pos',           icon: 'mdi-point-of-sale',          label: 'Punto de Venta' },
        { href: '/third-parties', icon: 'mdi-account-group-outline',  label: 'Terceros' },
      ],
    },
    {
      label: 'Inventario',
      items: [
        { href: '/inventory',  icon: 'mdi-package-variant-closed', label: 'Artículos' },
        { href: '/purchases',  icon: 'mdi-cart-outline',           label: 'Compras' },
      ],
    },
    {
      label: 'Finanzas',
      items: [
        { href: '/cash',       icon: 'mdi-bank-outline',           label: 'Caja y Bancos' },
        { href: '/accounting', icon: 'mdi-calculator-variant-outline', label: 'Contabilidad' },
      ],
    },
    {
      label: 'Análisis',
      items: [
        { href: '/reports', icon: 'mdi-chart-bar', label: 'Reportes' },
      ],
    },
    {
      label: 'Sistema',
      items: [
        { href: '/config', icon: 'mdi-cog-outline', label: 'Configuración' },
      ],
    },
  ]

  function isActive(href) {
    return currentPath === href || currentPath.startsWith(href + '/')
  }

  function logout() {
    router.post('/logout')
  }

  let userMenuOpen = $state(false)
</script>

<div class="flex h-screen bg-slate-100 overflow-hidden">

  <!-- ══ Sidebar ═══════════════════════════════════════════════════════════ -->
  <aside
    class="flex flex-col bg-slate-900 transition-all duration-300 shrink-0 {sidebarOpen ? 'w-60' : 'w-16'}"
  >

    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 h-14 border-b border-white/5 shrink-0">
      <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shrink-0">
        <i class="mdi mdi-lightning-bolt text-white text-base"></i>
      </div>
      {#if sidebarOpen}
        <div class="overflow-hidden">
          <span class="text-white text-sm font-bold leading-none whitespace-nowrap">NextPOS</span>
          <span class="text-blue-400 text-sm font-light"> SaaS</span>
        </div>
      {/if}
    </div>

    <!-- Navegación -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-4">
      {#each navGroups as group}
        <div>
          {#if group.label && sidebarOpen}
            <p class="text-slate-500 text-[10px] font-semibold uppercase tracking-wider px-2 mb-1">
              {group.label}
            </p>
          {:else if group.label}
            <div class="border-t border-white/5 mx-2 mb-2"></div>
          {/if}

          <ul class="space-y-0.5">
            {#each group.items as item}
              <li>
                <a
                  href={item.href}
                  class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm transition-colors group
                    {isActive(item.href)
                      ? 'bg-blue-600 text-white'
                      : 'text-slate-400 hover:bg-white/5 hover:text-white'}"
                  title={!sidebarOpen ? item.label : undefined}
                >
                  <i class="mdi {item.icon} text-lg shrink-0 {isActive(item.href) ? 'text-white' : 'text-slate-400 group-hover:text-white'}"></i>
                  {#if sidebarOpen}
                    <span class="truncate">{item.label}</span>
                  {/if}
                </a>
              </li>
            {/each}
          </ul>
        </div>
      {/each}
    </nav>

    <!-- Usuario (pie del sidebar) -->
    <div class="border-t border-white/5 p-3 shrink-0">
      {#if sidebarOpen}
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center shrink-0">
            <span class="text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </span>
          </div>
          <div class="flex-1 overflow-hidden">
            <p class="text-white text-xs font-medium truncate">{user?.name ?? 'Usuario'}</p>
            <p class="text-slate-500 text-[10px] truncate">{user?.email ?? ''}</p>
          </div>
          <button
            onclick={logout}
            class="text-slate-500 hover:text-red-400 transition cursor-pointer"
            title="Cerrar sesión"
          >
            <i class="mdi mdi-logout text-base"></i>
          </button>
        </div>
      {:else}
        <button
          onclick={logout}
          class="w-full flex justify-center text-slate-500 hover:text-red-400 transition cursor-pointer"
          title="Cerrar sesión"
        >
          <i class="mdi mdi-logout text-base"></i>
        </button>
      {/if}
    </div>
  </aside>

  <!-- ══ Contenido principal ════════════════════════════════════════════════ -->
  <div class="flex flex-col flex-1 overflow-hidden">

    <!-- Topbar -->
    <header class="flex items-center justify-between bg-white border-b border-slate-200 px-4 h-14 shrink-0">

      <!-- Izquierda: toggle sidebar + breadcrumb -->
      <div class="flex items-center gap-3">
        <button
          onclick={() => sidebarOpen = !sidebarOpen}
          aria-label="Alternar menú lateral"
          class="text-slate-500 hover:text-slate-800 transition cursor-pointer"
        >
          <i class="mdi mdi-menu text-xl"></i>
        </button>
      </div>

      <!-- Derecha: nombre de app + usuario -->
      <div class="flex items-center gap-4">
        <span class="text-slate-400 text-xs hidden sm:block">{appName}</span>
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center">
            <span class="text-white text-xs font-semibold">
              {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
            </span>
          </div>
          <span class="text-slate-700 text-sm font-medium hidden sm:block">{user?.name ?? ''}</span>
        </div>
      </div>
    </header>

    <!-- Área de contenido -->
    <main class="flex-1 overflow-y-auto p-6">
      {@render children()}
    </main>

  </div>
</div>
