import { CheckCircle2, CircleDashed, Loader2, WifiOff } from 'lucide-react';
import { cn } from '../utils/cn';

type Status = 'connected' | 'reconnecting' | 'disconnected' | 'idle';

export function ConnectionStatus({ status, label }: { status: Status; label: string }) {
    const Icon = status === 'connected' ? CheckCircle2 : status === 'reconnecting' ? Loader2 : status === 'disconnected' ? WifiOff : CircleDashed;

    return (
        <div
            className={cn(
                'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold',
                status === 'connected' && 'border-success/30 bg-success/5 text-success',
                status === 'reconnecting' && 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange',
                status === 'disconnected' && 'border-danger/30 bg-danger/5 text-danger',
                status === 'idle' && 'border-border bg-lightBackground text-slate-500',
            )}
        >
            <Icon className={cn('h-4 w-4', status === 'reconnecting' && 'animate-spin')} />
            {label}
        </div>
    );
}
