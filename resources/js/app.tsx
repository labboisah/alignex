import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { Component, ErrorInfo, ReactNode } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'AlignEx';

class AppErrorBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
    state = { error: null };

    static getDerivedStateFromError(error: Error) {
        return { error };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error('AlignEx frontend crashed', error, info);
    }

    render() {
        if (this.state.error) {
            return (
                <main className="min-h-screen bg-slate-50 p-6 text-slate-900">
                    <div className="mx-auto max-w-2xl rounded-md border border-red-200 bg-white p-5 shadow-sm">
                        <p className="text-sm font-semibold uppercase text-red-600">Frontend error</p>
                        <h1 className="mt-2 text-xl font-bold">The dashboard could not render.</h1>
                        <pre className="mt-4 overflow-auto rounded-md bg-slate-950 p-4 text-sm text-white">
                            {this.state.error.message}
                        </pre>
                    </div>
                </main>
            );
        }

        return this.props.children;
    }
}

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
        createRoot(el).render(
            <AppErrorBoundary>
                <App {...props} />
            </AppErrorBoundary>,
        );
    },
    progress: {
        color: '#0F7A3A',
    },
});
