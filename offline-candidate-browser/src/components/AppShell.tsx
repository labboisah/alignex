import type { ReactNode } from 'react';

export function AppShell({ children }: { children: ReactNode }) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-lightBackground px-6 py-10">
            <div className="w-full">{children}</div>
        </main>
    );
}
