<script>
    import AppLayout from '@/Layouts/AppLayout.svelte';

    let { auth } = $props();
</script>

<AppLayout>
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Dashboard</h1>
        <p class="text-gray-600">Bienvenido, {auth.user.name}.</p>
    </div>
</AppLayout>
