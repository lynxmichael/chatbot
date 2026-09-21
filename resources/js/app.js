import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

/*
 * Nom affiché dans l'onglet du navigateur : « Tickets — AI Service
 * Client ». VITE_APP_NAME reprend APP_NAME, qui vaut « Laravel » après
 * installation : on ne l'affiche jamais tel quel.
 */
const configured = import.meta.env.VITE_APP_NAME;

const appName = configured && configured !== 'Laravel'
    ? configured
    : 'AI Service Client';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
