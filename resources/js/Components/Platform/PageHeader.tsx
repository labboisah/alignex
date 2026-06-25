import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function PageHeader({ title, description, eyebrow, actions, className }: { title: string; description?: string; eyebrow?: string; actions?: ReactNode; className?: string }) {
    return (
        <div className={cn('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div>
                {eyebrow && <p className="text-sm font-semibold uppercase text-primary">{eyebrow}</p>}
                <h1 className="mt-1 text-2xl font-bold text-slateDark">{title}</h1>
                {description && <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
