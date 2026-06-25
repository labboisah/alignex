import { AlertCircle, CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/cn';

type AlertTone = 'info' | 'success' | 'warning' | 'danger';

const styles: Record<AlertTone, string> = {
    info: 'border-blue-200 bg-blue-50 text-info',
    success: 'border-green-200 bg-green-50 text-success',
    warning: 'border-amber-200 bg-amber-50 text-warning',
    danger: 'border-red-200 bg-red-50 text-danger',
};

const icons = {
    info: Info,
    success: CheckCircle2,
    warning: TriangleAlert,
    danger: AlertCircle,
};

export function AlertBanner({ title, message, tone = 'info', className }: { title: string; message?: string; tone?: AlertTone; className?: string }) {
    const Icon = icons[tone];

    return (
        <div className={cn('rounded-md border p-4', styles[tone], className)}>
            <div className="flex gap-3">
                <Icon className="h-5 w-5 shrink-0" />
                <div>
                    <div className="text-sm font-semibold">{title}</div>
                    {message && <div className="mt-1 text-sm leading-6 opacity-90">{message}</div>}
                </div>
            </div>
        </div>
    );
}
