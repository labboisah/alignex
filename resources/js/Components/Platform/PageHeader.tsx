import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';
import { BackButton } from './BackButton';
import { cn } from '@/lib/cn';

export function PageHeader({
    title,
    description,
    eyebrow,
    actions,
    className,
    showBack,
    backHref = '/dashboard',
}: {
    title: string;
    description?: string;
    eyebrow?: string;
    actions?: ReactNode;
    className?: string;
    showBack?: boolean;
    backHref?: string;
}) {
    const { url } = usePage();
    const shouldShowBack = showBack ?? !['/dashboard', '/'].includes(url.split('?')[0]);

    return (
        <div className={cn('mb-6', className)}>
            {shouldShowBack && (
                <div className="mb-4">
                    <BackButton fallbackHref={backHref} />
                </div>
            )}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    {eyebrow && <p className="text-sm font-semibold uppercase text-primary">{eyebrow}</p>}
                    <h1 className="mt-1 text-2xl font-bold text-slateDark">{title}</h1>
                    {description && <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{description}</p>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
            </div>
        </div>
    );
}
