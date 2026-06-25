import { cn } from '@/lib/cn';

type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

const tones: Record<StatusTone, string> = {
    success: 'bg-green-50 text-success ring-green-200',
    warning: 'bg-amber-50 text-warning ring-amber-200',
    danger: 'bg-red-50 text-danger ring-red-200',
    info: 'bg-blue-50 text-info ring-blue-200',
    neutral: 'bg-slate-100 text-slate-700 ring-slate-200',
};

export function StatusBadge({ label, tone = 'neutral', className }: { label: string; tone?: StatusTone; className?: string }) {
    return (
        <span className={cn('inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold ring-1 ring-inset', tones[tone], className)}>
            {label}
        </span>
    );
}
