import type { ReactNode } from 'react';
import { cn } from '../utils/cn';

export function PageCard({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <section className={cn('mx-auto rounded-md border border-border bg-white p-8 shadow-sm', className)}>
            {children}
        </section>
    );
}
