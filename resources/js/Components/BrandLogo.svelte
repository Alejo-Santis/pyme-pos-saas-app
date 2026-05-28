<script>
  let {
    variant = 'full',
    tone = 'dark',
    size = 'md',
    subtitle = '',
    class: className = '',
  } = $props()

  const sizes = {
    sm: { mark: 'w-7 h-7', text: 'text-sm', sub: 'text-[10px]' },
    md: { mark: 'w-8 h-8', text: 'text-xl', sub: 'text-xs' },
    lg: { mark: 'w-14 h-14', text: 'text-2xl', sub: 'text-sm' },
  }

  const palette = {
    dark: { main: 'text-slate-800', accent: 'text-slate-400', sub: 'text-slate-400' },
    light: { main: 'text-white', accent: 'text-blue-200', sub: 'text-blue-200' },
  }

  const currentSize = $derived(sizes[size] ?? sizes.md)
  const currentTone = $derived(palette[tone] ?? palette.dark)
</script>

<div class="inline-flex items-center gap-2 {className}">
  <svg
    class="{currentSize.mark} shrink-0 drop-shadow-sm"
    viewBox="0 0 64 64"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
  >
    <rect width="64" height="64" rx="16" fill="url(#pymepos-mark-gradient)" />
    <path d="M18 17.5C18 15.6 19.6 14 21.5 14H40.5C44.6 14 48 17.4 48 21.5C48 25.7 44.6 29 40.5 29H29V43.5C29 45.4 27.4 47 25.5 47H21.5C19.6 47 18 45.4 18 43.5V17.5Z" fill="white" />
    <path d="M29 20.5H39.7C41.4 20.5 42.8 21.9 42.8 23.6C42.8 25.3 41.4 26.7 39.7 26.7H29V20.5Z" fill="#1D4ED8" />
    <path d="M33 35H47C48.7 35 50 36.3 50 38V43.5C50 45.4 48.4 47 46.5 47H34.5C32.6 47 31 45.4 31 43.5V37C31 35.9 31.9 35 33 35Z" fill="#0F766E" />
    <path d="M35 39H46" stroke="white" stroke-width="2.4" stroke-linecap="round" />
    <circle cx="36.5" cy="43" r="1.6" fill="white" />
    <circle cx="43.5" cy="43" r="1.6" fill="white" />
    <defs>
      <linearGradient id="pymepos-mark-gradient" x1="8" y1="6" x2="58" y2="62" gradientUnits="userSpaceOnUse">
        <stop stop-color="#1D4ED8" />
        <stop offset="0.55" stop-color="#2563EB" />
        <stop offset="1" stop-color="#0F766E" />
      </linearGradient>
    </defs>
  </svg>

  {#if variant !== 'mark'}
    <div class="leading-none">
      <div class="whitespace-nowrap">
        <span class="{currentTone.main} {currentSize.text} font-extrabold tracking-tight">PymePOS</span>
        {#if variant === 'full'}
          <span class="{currentTone.accent} {currentSize.text} font-light"> SaaS</span>
        {/if}
      </div>
      {#if subtitle}
        <span class="block mt-1 {currentTone.sub} {currentSize.sub} font-medium tracking-wide">{subtitle}</span>
      {/if}
    </div>
  {/if}
</div>
