import '../css/app.css';
import '../css/anchor-link-icons.css';
import '../css/ui-fixes.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, Fragment, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppToasts from '@/Components/AppToasts.vue';
import ToastRelay from '@/Components/ToastRelay.vue';
import { applyThemeMode, readThemeMode } from '@/lib/theme';

const appName = 'Evala';

if (typeof window !== 'undefined') {
    applyThemeMode(readThemeMode());
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({
            render: () => h(Fragment, [h(App, props), h(AppToasts)]),
        });

        vueApp.component('ToastRelay', ToastRelay);

        return vueApp
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
