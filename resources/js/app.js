import './bootstrap';
import { createInertiaApp } from '@inertiajs/svelte';
import { mount } from 'svelte';

createInertiaApp({
    // Resuelve el componente Svelte correspondiente a la página
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.svelte', { eager: true });
        return pages[`./Pages/${name}.svelte`];
    },

    // Bootstrap de la app Svelte con Inertia
    setup({ el, App, props }) {
        mount(App, { target: el, props });
    },

    // Barra de progreso en navegación
    progress: {
        color: '#4F46E5',
        showSpinner: true,
    },
});
