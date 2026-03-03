<script>
    import { page } from '@inertiajs/svelte';

    let { children } = $props();
</script>

<div class="min-h-screen bg-gray-50">
    <!-- Navbar -->
    <nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
        <span class="text-lg font-semibold text-gray-800">
            {$page.props.appName ?? 'ColSaaS ERP'}
        </span>
        <span class="text-sm text-gray-500">
            {$page.props.auth?.user?.name ?? ''}
        </span>
    </nav>

    <!-- Contenido principal -->
    <main class="p-6">
        {@render children()}
    </main>
</div>
