import { AlertTriangle } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function ErrorState({ title = 'Something went wrong', description, action, className }: { title?: string; description?: string; action?: ReactNode; className?: string }) {
    return (
        <div className={cn('rounded-md border border-red-200 bg-red-50 p-5 text-danger', className)}>
            <div className="flex gap-3">
                <AlertTriangle className="h-5 w-5 shrink-0" />
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {description && <p className="mt-1 text-sm leading-6 text-red-700">{description}</p>}
                    {action && <div className="mt-4">{action}</div>}
                </div>
            </div>
        </div>
    );
}
