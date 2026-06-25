import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function DashboardCard({ title, value, description, icon: Icon, footer, className }: { title: string; value: string | number; description?: string; icon?: LucideIcon; footer?: ReactNode; className?: string }) {
    return (
        <div className={cn('rounded-md border border-border bg-white p-5 shadow-sm', className)}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-medium text-slate-500">{title}</p>
                    <div className="mt-3 text-3xl font-bold text-slateDark">{value}</div>
                </div>
                {Icon && (
                    <div className="rounded-md bg-green-50 p-2 text-primary">
                        <Icon className="h-5 w-5" />
                    </div>
                )}
            </div>
            {description && <p className="mt-3 text-sm leading-6 text-slate-500">{description}</p>}
            {footer && <div className="mt-4 border-t border-border pt-4">{footer}</div>}
        </div>
    );
}
