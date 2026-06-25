import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'AlignEx';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const pages = {
            ...import.meta.glob('./Pages/**/*.tsx'),
            ...import.meta.glob('./Pages/**/*.jsx'),
        };

        return resolvePageComponent(`./Pages/${name}.tsx`, pages)
            .catch(() => resolvePageComponent(`./Pages/${name}.jsx`, pages));
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#0F7A3A',
    },
});
