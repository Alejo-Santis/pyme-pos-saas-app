# Guía de Estilo Visual — Nuevo Proyecto SaaS ERP

> Este documento describe cómo replicar el estilo visual del proyecto **Xedoc**
> en el nuevo proyecto, usando **Tailwind CSS 4** en lugar de Bootstrap 5.
>
> Base de referencia: `xedoc-laravel-svelte`
> Última actualización: 2026-03-02

---

## 1. Resumen del Sistema Visual Actual (Xedoc)

El proyecto actual usa una plantilla administrativa tipo **Hyper/Adminize** basada en Bootstrap 5.
Los elementos visuales clave son:

| Elemento | Valor actual |
|---|---|
| Color primario | `#727cf5` (indigo/morado) |
| Color éxito | `#0acf97` (teal) |
| Color peligro | `#fa5c7c` (rojo/rosa) |
| Color warning | `#ffc35a` (amarillo) |
| Color info | `#39afd1` (cyan) |
| Fondo body | `#fafbfe` (blanco con tinte azul muy sutil) |
| Fondo sidebar (dark) | `#313a46` (azul-gris oscuro) |
| Fuente | `Nunito` (Google Fonts — 300, 400, 600, 700) |
| Íconos | Material Design Icons (MDI) — fuente CSS local |
| Layout | Sidebar izquierdo 260px + TopBar fijo 70px |
| Botones | `border-radius: 20px`, hover con lift effect (translateY -3px) |
| Sombra cards | `0px 0px 35px 0px rgba(154,161,171,0.15)` |
| Tamaño de fuente base | `0.9rem` |
| Tamaño fuente menú | `0.9375rem` |
| Dark mode | Selector `[data-bs-theme="dark"]` en `<html>` |

### Archivos de estilo del proyecto actual (referencia)

```
resources/css/
├── app-saas.min.css   ← tema Bootstrap customizado (colores, variables, componentes)
├── bootstrap.min.css  ← Bootstrap 5 base
├── icons.min.css      ← Material Design Icons (fuente local)
└── styles.css         ← overrides y componentes custom propios
```

---

## 2. Estrategia de Migración a Tailwind CSS

### Qué cambia y qué se mantiene

| Elemento | ¿Cambia? | Detalle |
|---|---|---|
| Colores y paleta | No (lógicamente) | Se configuran en `tailwind.config.js` con los mismos valores |
| Fuente Nunito | No | Se importa igual desde Google Fonts |
| Íconos MDI | **No cambia nada** | Es un archivo CSS con fuente local — solo se importa igual |
| SweetAlert2 | No | Es JS puro, no depende de Bootstrap |
| Layout sidebar/topbar | Se reconstruye | Misma estructura visual, clases Tailwind en vez de Bootstrap |
| Componentes (cards, botones, inputs) | Se reconstruyen | Componentes Svelte con clases Tailwind |
| Animaciones (modales, hover) | Se definen en CSS | Mismas animaciones, en `app.css` |
| Dark mode | Se mantiene el mismo selector | `[data-bs-theme="dark"]` funciona igual con Tailwind |

### Principio clave

> **El resultado visual final es idéntico.** Solo cambia el mecanismo interno:
> en vez de clases Bootstrap (`.btn-primary`, `.card`, `.form-control`),
> se usan clases Tailwind (`bg-primary text-white rounded-btn`, etc.).

---

## 3. Configuración de Tailwind (`tailwind.config.js`)

Este archivo extiende Tailwind con los valores exactos del sistema visual actual.

```js
// tailwind.config.js
import defaultTheme from 'tailwindcss/defaultTheme'

export default {
  content: [
    './resources/js/**/*.svelte',
    './resources/views/**/*.blade.php',
  ],

  // Mismo selector de dark mode que el proyecto actual
  darkMode: ['selector', '[data-bs-theme="dark"]'],

  theme: {
    extend: {

      // ─── PALETA DE COLORES (idéntica al proyecto actual) ───────────────
      colors: {
        primary: {
          DEFAULT: '#727cf5',
          soft:    '#eef0fe',   // fondo suave para variante "soft"
          dark:    '#6169d0',   // hover más oscuro
        },
        success: {
          DEFAULT: '#0acf97',
          soft:    '#e4faf4',
        },
        danger: {
          DEFAULT: '#fa5c7c',
          soft:    '#feecf0',
        },
        warning: {
          DEFAULT: '#ffc35a',
          soft:    '#fff8ec',
        },
        info: {
          DEFAULT: '#39afd1',
          soft:    '#eaf6fb',
        },
        secondary: {
          DEFAULT: '#6c757d',
        },

        // Escala de grises del sistema actual
        gray: {
          100: '#f6f7fb',
          200: '#eef2f7',
          300: '#dee2e6',
          400: '#ced4da',
          500: '#adb5bd',
          600: '#98a6ad',
          700: '#6c757d',
          800: '#495057',
          900: '#313a46',   // ← sidebar dark background
        },

        // Fondos especiales
        body:        '#fafbfe',   // fondo del área de contenido
        sidebar:     '#313a46',   // sidebar en modo dark
        topbar:      '#ffffff',   // topbar fondo
        'sidebar-brand': {
          from: '#6379c3',        // gradiente del sidebar brand
          to:   '#546ee5',
        },
      },

      // ─── TIPOGRAFÍA ────────────────────────────────────────────────────
      fontFamily: {
        sans: ['Nunito', ...defaultTheme.fontFamily.sans],
      },
      fontSize: {
        'base':  ['0.9rem',     { lineHeight: '1.5' }],
        'menu':  ['0.9375rem',  { lineHeight: '1.5' }],
        'label': ['0.8125rem',  { lineHeight: '1.4' }],
        'small': ['0.75rem',    { lineHeight: '1.4' }],
      },

      // ─── DIMENSIONES DEL LAYOUT ────────────────────────────────────────
      width: {
        sidebar:       '260px',   // sidebar ancho normal
        'sidebar-md':  '160px',   // sidebar compacto
        'sidebar-sm':  '70px',    // sidebar icon-only
      },
      height: {
        topbar: '70px',
        footer: '60px',
      },
      // Para margin/padding que usen las mismas dimensiones
      spacing: {
        sidebar:    '260px',
        'sidebar-sm': '70px',
        topbar:     '70px',
        footer:     '60px',
      },

      // ─── SOMBRAS ───────────────────────────────────────────────────────
      boxShadow: {
        'card':   '0px 0px 35px 0px rgba(154, 161, 171, 0.15)',
        'btn':    '0 4px 15px rgba(114, 124, 245, 0.3)',
        'btn-lg': '0 8px 20px rgba(114, 124, 245, 0.4)',
      },

      // ─── BORDER RADIUS ─────────────────────────────────────────────────
      borderRadius: {
        'btn':  '20px',    // botones redondeados del proyecto actual
        'card': '0.375rem',
      },

      // ─── TRANSICIONES ──────────────────────────────────────────────────
      transitionProperty: {
        'lift': 'transform, box-shadow',
      },
    },
  },

  plugins: [],
}
```

---

## 4. CSS Base (`resources/css/app.css`)

```css
/* resources/css/app.css */

/* ── Tailwind base ─────────────────────────────────────────────────── */
@import "tailwindcss";

/* ── Fuente Nunito (idéntica al proyecto actual) ─────────────────── */
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap');

/* ── Material Design Icons (igual que hoy — solo cambiar la ruta) ── */
/* Copiar el archivo icons.min.css del proyecto actual */
@import "./icons.min.css";

/* ── Variables de layout ─────────────────────────────────────────── */
:root {
  --topbar-height:    70px;
  --sidebar-width:    260px;
  --sidebar-width-sm: 70px;
  --footer-height:    60px;
}

/* ── Reset y estilos base ────────────────────────────────────────── */
body {
  font-family: 'Nunito', sans-serif;
  font-size: 0.9rem;
  line-height: 1.5;
  background-color: #fafbfe;
  color: #6c757d;
  -webkit-font-smoothing: antialiased;
}

/* ── Scrollbar personalizada (igual al proyecto actual) ─────────── */
::-webkit-scrollbar       { width: 6px; height: 6px; }
::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 3px; }
::-webkit-scrollbar-track { background: transparent; }

/* ── Efecto lift en botones (hover sube el botón) ────────────────── */
.btn-lift {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.btn-lift:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 15px rgba(114, 124, 245, 0.3);
}

/* ── Animación de modales (slide-up igual al proyecto actual) ────── */
@keyframes slideUp {
  from { transform: translateY(20px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
.modal-animate {
  animation: slideUp 0.25s ease forwards;
}

/* ── Indicador de campo requerido (punto rojo) ───────────────────── */
/* Uso: <label class="required">Nombre</label>  */
.required::after {
  content: '';
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: #fa5c7c;
  margin-left: 4px;
  vertical-align: middle;
}

/* ── Tablas compactas (equivalente a .table-next del proyecto) ───── */
.table-compact td,
.table-compact th {
  padding: 2px 5px;
  font-size: 14px;
}
.table-compact thead th {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #f6f7fb;
  border-bottom: 1px solid #eef2f7;
}

/* ── Totales fijos al pie (equivalente a .monetary-totals-fixed) ─── */
.totals-fixed {
  position: sticky;
  bottom: 0;
  background: #fff;
  border-top: 2px solid #eef2f7;
  padding: 8px 12px;
}

/* ── Dark mode (mismo selector que el proyecto actual) ───────────── */
[data-bs-theme="dark"] body {
  background-color: #323a46;
  color: #aab8c5;
}
[data-bs-theme="dark"] .bg-topbar   { background-color: #3c4655; }
[data-bs-theme="dark"] .bg-card     { background-color: #3c4655; }
[data-bs-theme="dark"] .border-card { border-color: #464f5b; }

/* ── Print styles ────────────────────────────────────────────────── */
@media print {
  body      { font-size: 12px; }
  .no-print { display: none !important; }
  .sidebar,
  .topbar   { display: none !important; }
  .content  { margin: 0 !important; padding: 0 !important; }
}
```

---

## 5. Componentes Svelte Equivalentes

### 5.1 Layout principal (`Pages/Shared/Layout.svelte`)

```svelte
<script>
  import { onMount } from 'svelte'
  import SideNav   from './SideNav.svelte'
  import TopNavbar from './TopNavbar.svelte'
  import Footer    from './Footer.svelte'

  let { children } = $props()

  // Misma lógica del proyecto actual: persistir preferencia en localStorage
  let theme = $state(localStorage.getItem('theme') ?? 'light')

  onMount(() => {
    document.documentElement.setAttribute('data-bs-theme', theme)
  })
</script>

<!-- Sidebar izquierdo — 260px, fijo -->
<SideNav />

<!-- Topbar fijo arriba -->
<TopNavbar bind:theme />

<!-- Área de contenido principal -->
<main class="ml-sidebar mt-topbar min-h-screen bg-body p-6 transition-all duration-300">
  {@render children()}
</main>

<!-- Footer opcional -->
<Footer />
```

### 5.2 Sidebar (`Pages/Shared/SideNav.svelte`)

```svelte
<script>
  import { page } from '@inertiajs/svelte'
  let menus = $derived($page.props.menus ?? [])
</script>

<aside class="fixed left-0 top-0 h-screen w-sidebar bg-sidebar
               flex flex-col z-40 overflow-hidden transition-all duration-300
               shadow-[2px_0_10px_rgba(0,0,0,0.1)]">

  <!-- Logo / Brand -->
  <div class="flex h-topbar items-center justify-center border-b border-white/10 px-6">
    <span class="text-xl font-bold text-white tracking-wide">ERP SaaS</span>
  </div>

  <!-- Navegación con scroll -->
  <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-0.5">
    {#each menus as menu}
      <!-- Item de menú -->
      <a
        href={menu.url}
        class="flex items-center gap-3 rounded-md px-3 py-2.5 text-menu font-medium
               text-white/70 transition-colors duration-150
               hover:bg-white/10 hover:text-white
               aria-[current=page]:bg-white/15 aria-[current=page]:text-white"
        aria-current={$page.url === menu.url ? 'page' : undefined}
      >
        <i class="mdi mdi-{menu.icon} text-lg w-5 text-center flex-shrink-0"></i>
        <span class="truncate">{menu.name}</span>
      </a>
    {/each}
  </nav>

  <!-- Footer del sidebar -->
  <div class="border-t border-white/10 p-3">
    <p class="text-center text-small text-white/40">v1.0.0</p>
  </div>
</aside>
```

### 5.3 TopBar (`Pages/Shared/TopNavbar.svelte`)

```svelte
<script>
  import { page, router } from '@inertiajs/svelte'
  let { theme = $bindable('light') } = $props()

  function toggleTheme() {
    theme = theme === 'light' ? 'dark' : 'light'
    document.documentElement.setAttribute('data-bs-theme', theme)
    localStorage.setItem('theme', theme)
  }

  function logout() {
    router.post('/logout')
  }
</script>

<header class="fixed top-0 left-sidebar right-0 h-topbar bg-topbar z-30
                flex items-center justify-between px-6
                border-b border-gray-200 shadow-sm">

  <!-- Izquierda: breadcrumb o título -->
  <div>
    <h4 class="text-base font-semibold text-gray-800 m-0">
      {$page.props.title ?? 'Dashboard'}
    </h4>
  </div>

  <!-- Derecha: acciones -->
  <div class="flex items-center gap-3">

    <!-- Toggle dark mode -->
    <button onclick={toggleTheme}
            class="flex h-8 w-8 items-center justify-center rounded-full
                   text-gray-500 hover:bg-gray-100 transition-colors">
      <i class="mdi {theme === 'dark' ? 'mdi-weather-sunny' : 'mdi-weather-night'} text-lg"></i>
    </button>

    <!-- Notificaciones (placeholder) -->
    <button class="flex h-8 w-8 items-center justify-center rounded-full
                   text-gray-500 hover:bg-gray-100 transition-colors relative">
      <i class="mdi mdi-bell-outline text-lg"></i>
      <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-danger"></span>
    </button>

    <!-- Avatar + menú de usuario -->
    <div class="relative">
      <button class="flex items-center gap-2 rounded-full px-2 py-1
                     hover:bg-gray-100 transition-colors">
        <div class="h-8 w-8 rounded-full bg-primary flex items-center justify-center">
          <span class="text-sm font-semibold text-white">
            {$page.props.auth?.user?.name?.charAt(0) ?? 'U'}
          </span>
        </div>
        <span class="text-sm font-medium text-gray-700 hidden md:block">
          {$page.props.auth?.user?.name ?? 'Usuario'}
        </span>
        <i class="mdi mdi-chevron-down text-gray-500"></i>
      </button>
      <!-- Dropdown: logout, perfil, etc. — implementar con Svelte state -->
    </div>
  </div>
</header>
```

### 5.4 Botón (`Components/UI/Button.svelte`)

```svelte
<script>
  /**
   * Equivalente al Button.svelte del proyecto actual.
   * Mantiene el mismo aspecto: rounded-btn (20px), hover lift, variantes.
   */
  let {
    variant  = 'primary',   // primary | danger | success | warning | secondary | soft | outline
    size     = 'sm',        // xs | sm | md | lg
    icon     = null,        // nombre del ícono MDI, ej: "plus" → mdi mdi-plus
    iconEnd  = null,        // ícono al final del texto
    disabled = false,
    type     = 'button',
    children,
    ...props
  } = $props()

  const base = 'inline-flex items-center justify-center gap-1.5 font-medium rounded-btn btn-lift transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-60 disabled:cursor-not-allowed disabled:transform-none'

  const variants = {
    primary:   'bg-primary      text-white   hover:bg-primary-dark  focus:ring-primary/40',
    danger:    'bg-danger       text-white   hover:bg-danger/90     focus:ring-danger/40',
    success:   'bg-success      text-white   hover:bg-success/90    focus:ring-success/40',
    warning:   'bg-warning      text-gray-900 hover:bg-warning/90   focus:ring-warning/40',
    secondary: 'bg-gray-200     text-gray-800 hover:bg-gray-300     focus:ring-gray-300',
    soft:      'bg-primary/10   text-primary hover:bg-primary/20    focus:ring-primary/20',
    'soft-danger': 'bg-danger/10 text-danger hover:bg-danger/20     focus:ring-danger/20',
    outline:   'border border-primary text-primary hover:bg-primary hover:text-white focus:ring-primary/30',
    ghost:     'text-gray-600   hover:bg-gray-100                   focus:ring-gray-200',
  }

  const sizes = {
    xs: 'px-2    py-0.5  text-xs',
    sm: 'px-3    py-1.5  text-sm',
    md: 'px-4    py-2    text-base',
    lg: 'px-5    py-2.5  text-base',
  }
</script>

<button
  {type}
  {disabled}
  class="{base} {variants[variant] ?? variants.primary} {sizes[size] ?? sizes.sm}"
  {...props}
>
  {#if icon}
    <i class="mdi mdi-{icon} text-base leading-none"></i>
  {/if}

  {@render children?.()}

  {#if iconEnd}
    <i class="mdi mdi-{iconEnd} text-base leading-none"></i>
  {/if}
</button>
```

**Uso:**
```svelte
<Button icon="plus">Nueva Factura</Button>
<Button variant="danger" icon="delete">Eliminar</Button>
<Button variant="soft" size="xs">Ver detalle</Button>
<Button variant="outline" icon="download" iconEnd="chevron-down">Exportar</Button>
```

### 5.5 Input de texto (`Components/UI/Input.svelte`)

```svelte
<script>
  /**
   * Equivalente al TextInput.svelte del proyecto actual.
   * Soporte: label, required, disabled, error message.
   */
  let {
    label    = null,
    required = false,
    disabled = false,
    error    = null,
    id       = null,
    value    = $bindable(''),
    ...props
  } = $props()

  const inputId = id ?? `input-${Math.random().toString(36).slice(2)}`
</script>

<div class="flex flex-col gap-1">
  {#if label}
    <label
      for={inputId}
      class="text-label font-medium text-gray-700"
      class:required
    >
      {label}
    </label>
  {/if}

  <input
    {id}={inputId}
    bind:value
    {disabled}
    class="w-full rounded border px-3 py-1.5 text-sm text-gray-800
           placeholder:text-gray-400
           focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
           disabled:bg-gray-100 disabled:cursor-not-allowed disabled:text-gray-500
           {error ? 'border-danger focus:ring-danger/30' : 'border-gray-300'}"
    {...props}
  />

  {#if error}
    <p class="text-small text-danger">{error}</p>
  {/if}
</div>
```

### 5.6 Select (`Components/UI/Select.svelte`)

```svelte
<script>
  let {
    label    = null,
    required = false,
    disabled = false,
    error    = null,
    options  = [],       // [{ value, label }]
    value    = $bindable(''),
    ...props
  } = $props()
</script>

<div class="flex flex-col gap-1">
  {#if label}
    <label class="text-label font-medium text-gray-700" class:required>
      {label}
    </label>
  {/if}

  <select
    bind:value
    {disabled}
    class="w-full rounded border px-3 py-1.5 text-sm text-gray-800
           focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
           disabled:bg-gray-100 disabled:cursor-not-allowed
           {error ? 'border-danger' : 'border-gray-300'}"
    {...props}
  >
    <option value="">-- Seleccionar --</option>
    {#each options as opt}
      <option value={opt.value}>{opt.label}</option>
    {/each}
  </select>

  {#if error}
    <p class="text-small text-danger">{error}</p>
  {/if}
</div>
```

### 5.7 Card (`Components/UI/Card.svelte`)

```svelte
<script>
  /**
   * Equivalente al .card de Bootstrap del proyecto actual.
   */
  let {
    title    = null,
    subtitle = null,
    children,
    actions,          // slot para botones en el header
    footer,           // slot para footer de la card
    padding  = true,
  } = $props()
</script>

<div class="bg-white rounded-card shadow-card border border-gray-200 overflow-hidden">

  {#if title}
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <div>
        <h5 class="text-base font-semibold text-gray-800 m-0">{title}</h5>
        {#if subtitle}
          <p class="text-small text-gray-500 m-0">{subtitle}</p>
        {/if}
      </div>
      {#if actions}
        <div class="flex items-center gap-2">
          {@render actions()}
        </div>
      {/if}
    </div>
  {/if}

  <div class={padding ? 'p-4' : ''}>
    {@render children()}
  </div>

  {#if footer}
    <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
      {@render footer()}
    </div>
  {/if}
</div>
```

**Uso:**
```svelte
<Card title="Facturas del mes">
  {#snippet actions()}
    <Button icon="plus" size="xs">Nueva</Button>
  {/snippet}

  <!-- contenido de la card -->

  {#snippet footer()}
    <p class="text-small text-gray-500">Total: 24 facturas</p>
  {/snippet}
</Card>
```

### 5.8 Badge / Estado (`Components/UI/Badge.svelte`)

```svelte
<script>
  let {
    variant = 'primary',   // primary | success | danger | warning | info | secondary
    children,
  } = $props()

  const variants = {
    primary:   'bg-primary/10   text-primary',
    success:   'bg-success/10   text-success',
    danger:    'bg-danger/10    text-danger',
    warning:   'bg-warning/10   text-warning-700',
    info:      'bg-info/10      text-info',
    secondary: 'bg-gray-100     text-gray-600',
  }
</script>

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
             {variants[variant] ?? variants.secondary}">
  {@render children()}
</span>
```

**Uso para estados de factura:**
```svelte
<Badge variant="success">Aprobada</Badge>
<Badge variant="danger">Rechazada</Badge>
<Badge variant="warning">Pendiente</Badge>
<Badge variant="secondary">Borrador</Badge>
```

### 5.9 Modal (`Components/UI/Modal.svelte`)

```svelte
<script>
  /**
   * Equivalente al .modal de Bootstrap del proyecto actual.
   * Mismo efecto: overlay oscuro + slide-up animation.
   */
  let {
    open     = $bindable(false),
    title    = '',
    size     = 'md',       // sm | md | lg | xl | full
    children,
    footer,
  } = $props()

  const sizes = {
    sm:   'max-w-sm',
    md:   'max-w-lg',
    lg:   'max-w-2xl',
    xl:   'max-w-4xl',
    full: 'max-w-7xl',
  }

  function close() { open = false }
</script>

{#if open}
  <!-- Overlay -->
  <div
    class="fixed inset-0 z-50 flex items-center justify-center p-4
           bg-black/50 backdrop-blur-sm"
    onclick={close}
    role="dialog"
    aria-modal="true"
  >
    <!-- Panel del modal -->
    <div
      class="modal-animate relative w-full bg-white rounded-card shadow-xl
             overflow-hidden {sizes[size] ?? sizes.md}"
      onclick|stopPropagation
    >

      <!-- Header — fondo primario igual al proyecto actual -->
      <div class="flex items-center justify-between bg-primary px-5 py-3">
        <h5 class="text-base font-semibold text-white m-0">{title}</h5>
        <button
          onclick={close}
          class="flex h-7 w-7 items-center justify-center rounded
                 text-white/70 hover:text-white hover:bg-white/15 transition-colors"
        >
          <i class="mdi mdi-close text-lg"></i>
        </button>
      </div>

      <!-- Body -->
      <div class="p-5 overflow-y-auto max-h-[75vh]">
        {@render children()}
      </div>

      <!-- Footer -->
      {#if footer}
        <div class="flex items-center justify-end gap-2 px-5 py-3
                    border-t border-gray-200 bg-gray-50">
          {@render footer()}
        </div>
      {/if}

    </div>
  </div>
{/if}
```

**Uso:**
```svelte
<script>
  let showModal = $state(false)
</script>

<Button icon="pencil" onclick={() => showModal = true}>Editar</Button>

<Modal bind:open={showModal} title="Editar Cliente" size="lg">
  <!-- formulario aquí -->

  {#snippet footer()}
    <Button variant="ghost" onclick={() => showModal = false}>Cancelar</Button>
    <Button variant="primary" icon="content-save">Guardar</Button>
  {/snippet}
</Modal>
```

### 5.10 Tabla compacta (`Components/UI/DataTable.svelte`)

```svelte
<script>
  /**
   * Equivalente a .table-next del proyecto actual.
   * Tabla con headers sticky, filas compactas y responsive.
   */
  let {
    columns  = [],    // [{ key, label, class? }]
    rows     = [],    // array de objetos
    loading  = false,
    children,         // slot para celdas personalizadas
  } = $props()
</script>

<div class="overflow-x-auto rounded-card border border-gray-200">
  <table class="w-full text-sm">
    <thead>
      <tr class="bg-gray-100 text-left">
        {#each columns as col}
          <th class="table-compact border-b border-gray-200 font-semibold text-gray-700
                     sticky top-0 bg-gray-100 z-10 {col.class ?? ''}">
            {col.label}
          </th>
        {/each}
      </tr>
    </thead>
    <tbody>
      {#if loading}
        <tr>
          <td colspan={columns.length} class="py-8 text-center text-gray-400">
            <i class="mdi mdi-loading mdi-spin text-2xl"></i>
            <p class="mt-1 text-small">Cargando...</p>
          </td>
        </tr>
      {:else if rows.length === 0}
        <tr>
          <td colspan={columns.length} class="py-8 text-center text-gray-400">
            <i class="mdi mdi-inbox-outline text-3xl block mb-2"></i>
            Sin registros
          </td>
        </tr>
      {:else}
        {#each rows as row, i}
          <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
            {#if children}
              {@render children({ row, i })}
            {:else}
              {#each columns as col}
                <td class="table-compact text-gray-700">{row[col.key] ?? '—'}</td>
              {/each}
            {/if}
          </tr>
        {/each}
      {/if}
    </tbody>
  </table>
</div>
```

---

## 6. Íconos MDI — Referencia de los más usados

Los mismos íconos del proyecto actual. Solo copiar el archivo `icons.min.css`.

```svelte
<!-- Sintaxis idéntica al proyecto actual -->
<i class="mdi mdi-{nombre-icono}"></i>
<i class="mdi mdi-{nombre-icono} text-lg text-primary"></i>
```

| Contexto | Ícono | Clase |
|---|---|---|
| Dashboard | `mdi-view-dashboard-outline` | |
| Facturas | `mdi-file-document-outline` | |
| Clientes | `mdi-account-group-outline` | |
| Inventario | `mdi-package-variant-closed` | |
| Contabilidad | `mdi-calculator-variant-outline` | |
| Caja / POS | `mdi-cash-register` | |
| Compras | `mdi-cart-outline` | |
| Reportes | `mdi-chart-bar` | |
| Configuración | `mdi-cog-outline` | |
| Agregar | `mdi-plus` | |
| Editar | `mdi-pencil-outline` | |
| Eliminar | `mdi-delete-outline` | |
| Guardar | `mdi-content-save-outline` | |
| Buscar | `mdi-magnify` | |
| Descargar | `mdi-download-outline` | |
| Imprimir | `mdi-printer-outline` | |
| Enviar DIAN | `mdi-send-outline` | |
| Aprobado | `mdi-check-circle-outline` | `text-success` |
| Rechazado | `mdi-close-circle-outline` | `text-danger` |
| Pendiente | `mdi-clock-outline` | `text-warning` |
| Cerrar | `mdi-close` | |
| Spinner / carga | `mdi-loading mdi-spin` | |
| Alerta | `mdi-alert-circle-outline` | `text-warning` |
| Info | `mdi-information-outline` | `text-info` |

---

## 7. Paleta de Colores — Referencia Visual Rápida

```
COLORES PRINCIPALES
┌─────────────────────────────────────────────────────────────┐
│  PRIMARY   ████ #727cf5  Indigo/morado — botones, links     │
│  SUCCESS   ████ #0acf97  Teal — estados OK, validaciones    │
│  DANGER    ████ #fa5c7c  Rojo/rosa — errores, eliminar      │
│  WARNING   ████ #ffc35a  Amarillo — alertas, pendiente      │
│  INFO      ████ #39afd1  Cyan — informativo                 │
│  SECONDARY ████ #6c757d  Gris — texto secundario            │
└─────────────────────────────────────────────────────────────┘

FONDOS
┌─────────────────────────────────────────────────────────────┐
│  Body      ████ #fafbfe  Fondo del área de contenido        │
│  Card      ████ #ffffff  Fondo de tarjetas                  │
│  Sidebar   ████ #313a46  Sidebar en modo dark               │
│  Table row ████ #f6f7fb  Fondo alterno o header de tabla    │
└─────────────────────────────────────────────────────────────┘

GRISES DE TEXTO
┌─────────────────────────────────────────────────────────────┐
│  Título    ████ #313a46  Títulos y texto importante         │
│  Cuerpo    ████ #6c757d  Texto general                      │
│  Suave     ████ #98a6ad  Texto secundario, placeholders     │
│  Borde     ████ #dee2e6  Bordes de inputs y tarjetas        │
└─────────────────────────────────────────────────────────────┘
```

---

## 8. Estructura de Archivos del Sistema de Diseño

```
resources/
├── css/
│   ├── app.css              ← Tailwind + variables + utilitarios custom
│   └── icons.min.css        ← MDI icons (copiar del proyecto actual)
│
└── js/
    └── Components/
        └── UI/              ← Librería de componentes base
            ├── Button.svelte
            ├── Input.svelte
            ├── Select.svelte
            ├── Card.svelte
            ├── Modal.svelte
            ├── Badge.svelte
            ├── DataTable.svelte
            ├── Pagination.svelte
            ├── Alert.svelte
            └── index.js     ← re-exporta todos para imports limpios
```

```js
// Components/UI/index.js — import limpio en las páginas
export { default as Button    } from './Button.svelte'
export { default as Input     } from './Input.svelte'
export { default as Select    } from './Select.svelte'
export { default as Card      } from './Card.svelte'
export { default as Modal     } from './Modal.svelte'
export { default as Badge     } from './Badge.svelte'
export { default as DataTable } from './DataTable.svelte'
```

```svelte
<!-- Uso en cualquier página -->
<script>
  import { Button, Card, Modal, Badge } from '@/Components/UI'
</script>
```

---

## 9. Integración con vite.config.js

```js
// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { svelte } from '@sveltejs/vite-plugin-svelte'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
  plugins: [
    tailwindcss(),   // ← Plugin oficial de Tailwind 4 para Vite

    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),

    svelte(),
  ],

  resolve: {
    alias: {
      // Alias @/ para imports limpios desde resources/js/
      '@': path.resolve(__dirname, 'resources/js'),
    },
  },
})
```

---

## 10. Checklist de Implementación del Estilo

```
Fase inicial (antes de construir páginas):

[ ] Copiar icons.min.css del proyecto actual a resources/css/
[ ] Crear tailwind.config.js con la paleta completa (Sección 3)
[ ] Crear resources/css/app.css con variables y utilitarios (Sección 4)
[ ] Configurar vite.config.js con el plugin de Tailwind 4 (Sección 9)
[ ] Verificar que la fuente Nunito carga correctamente
[ ] Verificar que los íconos MDI funcionan (ej: <i class="mdi mdi-home"></i>)
[ ] Crear componentes base en resources/js/Components/UI/ (Sección 5):
    [ ] Button.svelte
    [ ] Input.svelte
    [ ] Select.svelte
    [ ] Card.svelte
    [ ] Modal.svelte
    [ ] Badge.svelte
    [ ] DataTable.svelte
[ ] Crear Layout.svelte con sidebar + topbar (Secciones 5.1, 5.2, 5.3)
[ ] Probar dark mode: document.documentElement.setAttribute('data-bs-theme', 'dark')
[ ] Probar en mobile: sidebar colapsa correctamente
[ ] Probar impresión: .no-print oculta sidebar y topbar
```

---

*Documento basado en el análisis visual del proyecto xedoc-laravel-svelte.*
*Última actualización: 2026-03-02.*
