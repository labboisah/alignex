import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function FormSection({ title, description, children, footer, className }: { title: string; description?: string; children: ReactNode; footer?: ReactNode; className?: string }) {
    return (
        <section className={cn('rounded-md border border-border bg-white shadow-sm', className)}>
            <div className="border-b border-border p-5">
                <h2 className="font-semibold text-slateDark">{title}</h2>
                {description && <p className="mt-1 text-sm leading-6 text-slate-500">{description}</p>}
            </div>
            <div className="grid gap-4 p-5">{children}</div>
            {footer && <div className="border-t border-border bg-slate-50 px-5 py-4">{footer}</div>}
        </section>
    );
}
