import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function ChartCard({ title, description, actions, children, className }: { title: string; description?: string; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <section className={cn('rounded-md border border-border bg-white p-5 shadow-sm', className)}>
            <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h2 className="font-semibold text-slateDark">{title}</h2>
                    {description && <p className="mt-1 text-sm leading-6 text-slate-500">{description}</p>}
                </div>
                {actions && <div className="shrink-0">{actions}</div>}
            </div>
            {children}
        </section>
    );
}
