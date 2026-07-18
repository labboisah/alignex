import { CheckCircle2, CircleDashed, Loader2, TriangleAlert } from 'lucide-react';
import { cn } from '../../utils/cn';

export function SaveStatus({ status }: { status: 'idle' | 'saving' | 'saved' | 'failed' | 'syncing' }) {
    const busy = status === 'saving' || status === 'syncing';
    const Icon = busy ? Loader2 : status === 'saved' ? CheckCircle2 : status === 'failed' ? TriangleAlert : CircleDashed;
    const label = status === 'saving' ? 'Saving' : status === 'syncing' ? 'Syncing' : status === 'saved' ? 'All answers synced' : status === 'failed' ? 'Save Failed' : 'Not Saved';

    return (
        <div
            className={cn(
                'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-bold',
                status === 'saving' && 'border-info/30 bg-info/5 text-info',
                status === 'syncing' && 'border-info/30 bg-info/5 text-info',
                status === 'saved' && 'border-success/30 bg-success/5 text-success',
                status === 'failed' && 'border-danger/30 bg-danger/5 text-danger',
                status === 'idle' && 'border-border bg-lightBackground text-slate-500',
            )}
        >
            <Icon className={cn('h-4 w-4', busy && 'animate-spin')} />
            {label}
        </div>
    );
}
