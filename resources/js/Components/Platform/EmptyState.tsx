import { Inbox } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function EmptyState({ title = 'Nothing here yet', description, action, className }: { title?: string; description?: string; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('flex min-h-48 flex-col items-center justify-center rounded-md border border-dashed border-border bg-white p-8 text-center', className)}>
            <Inbox className="h-8 w-8 text-slate-400" />
            <h3 className="mt-4 text-sm font-semibold text-slateDark">{title}</h3>
            {description && <p className="mt-2 max-w-md text-sm leading-6 text-slate-500">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
