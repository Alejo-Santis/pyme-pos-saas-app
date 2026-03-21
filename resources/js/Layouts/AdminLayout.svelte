<script>
  import { page, router, inertia } from '@inertiajs/svelte'

  let { children } = $props()

  const admin   = $derived($page.props.auth?.admin)
  const flash   = $derived($page.props.flash ?? {})

  let showFlash = $state(true)
  $effect(() => { if (flash.success || flash.error) showFlash = true })

  function logout() {
    router.post('/admin/logout')
  }

  const navItems = [
    { href: '/admin/dashboard', icon: 'mdi-view-dashboard-outline', label: 'Dashboard' },
    { href: '/admin/tenants',   icon: 'mdi-domain',                  label: 'Empresas'  },
    { href: '/admin/plans',     icon: 'mdi-layers-outline',           label: 'Planes'    },
  ]

  const currentPath = $derived($page.url)

  function isActive(href) {
    return currentPath === href || currentPath.startsWith(href + '/')
  }
</script>

<div class="flex h-screen bg-body overflow-hidden">

  <!-- Sidebar claro -->
  <aside class="flex flex-col w-56 shrink-0 bg-white border-r border-slate-200 shadow-sm">

    <!-- Logo / título panel -->
    <div class="flex items-center gap-2 px-4 h-14 border-b border-slate-100 shrink-0">
      <div class="w-7 h-7 bg-primary rounded-lg flex items-center justify-center">
        <i class="mdi mdi-shield-crown text-white text-sm"></i>
      </div>
      <div>
        <span class="text-slate-800 text-sm font-bold">PymePOS</span>
        <span class="text-xs text-slate-400 block leading-none">Super Admin</span>
      </div>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-4 px-2 space-y-0.5">
      {#each navItems as item}
        <a
          use:inertia
          href={item.href}
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors
            {isActive(item.href)
              ? 'bg-primary/10 text-primary font-semibold'
              : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
        >
          <i class="mdi {item.icon} text-lg shrink-0
            {isActive(item.href) ? 'text-primary' : 'text-slate-400'}"></i>
          <span>{item.label}</span>
        </a>
      {/each}
    </nav>

    <!-- Pie: usuario + logout -->
    <div class="border-t border-slate-100 p-3 shrink-0">
      <div class="flex items-center gap-2">
        <div class="w-7 h-7 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
          <span class="text-primary text-xs font-bold">
            {admin?.name?.charAt(0)?.toUpperCase() ?? 'A'}
          </span>
        </div>
        <div class="flex-1 overflow-hidden">
          <p class="text-slate-700 text-xs font-medium truncate">{admin?.name ?? 'Admin'}</p>
          <p class="text-slate-400 text-[10px] truncate">{admin?.email ?? ''}</p>
        </div>
        <button onclick={logout} title="Cerrar sesión" class="text-slate-400 hover:text-red-500 transition cursor-pointer">
          <i class="mdi mdi-logout text-base"></i>
        </button>
      </div>
    </div>
  </aside>

  <!-- Área de contenido -->
  <div class="flex flex-col flex-1 overflow-hidden">

    <!-- Topbar -->
    <header class="flex items-center justify-between bg-white border-b border-slate-200 px-6 h-14 shrink-0 shadow-sm">
      <h1 class="text-slate-700 text-sm font-semibold">Panel de Administración</h1>
      <div class="flex items-center gap-3">
        <a href="/" class="text-slate-400 hover:text-primary transition" title="Ir al sitio público">
          <i class="mdi mdi-open-in-new text-base"></i>
        </a>
        <div class="w-7 h-7 bg-primary rounded-full flex items-center justify-center">
          <span class="text-white text-xs font-semibold">
            {admin?.name?.charAt(0)?.toUpperCase() ?? 'A'}
          </span>
        </div>
        <span class="text-slate-700 text-sm font-medium hidden sm:block">{admin?.name ?? ''}</span>
      </div>
    </header>

    <!-- Flash notifications -->
    {#if showFlash && (flash.success || flash.error)}
      <div class="px-6 pt-4">
        <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg text-sm
          {flash.success ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800'}">
          <div class="flex items-center gap-2">
            <i class="mdi {flash.success ? 'mdi-check-circle-outline' : 'mdi-alert-circle-outline'} text-lg"></i>
            <span>{flash.success ?? flash.error}</span>
          </div>
          <button onclick={() => showFlash = false} class="opacity-60 hover:opacity-100 cursor-pointer">
            <i class="mdi mdi-close text-base"></i>
          </button>
        </div>
      </div>
    {/if}

    <!-- Contenido de la página -->
    <main class="flex-1 overflow-y-auto p-6">
      {@render children()}
    </main>

  </div>
</div>
