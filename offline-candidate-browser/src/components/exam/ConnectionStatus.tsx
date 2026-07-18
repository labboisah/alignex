import { CheckCircle2, CircleDashed, Loader2, WifiOff } from 'lucide-react';
import { cn } from '../../utils/cn';

export function ConnectionStatus({ status }: { status: 'unknown' | 'connected' | 'reconnecting' | 'disconnected' | 'syncing' }) {
    const busy = status === 'reconnecting' || status === 'syncing';
    const Icon = status === 'connected' ? CheckCircle2 : status === 'disconnected' ? WifiOff : busy ? Loader2 : CircleDashed;
    const label =
        status === 'connected'
            ? 'Connected'
            : status === 'disconnected'
              ? 'Disconnected'
              : status === 'reconnecting'
                ? 'Reconnecting'
                : status === 'syncing'
                  ? 'Syncing pending answers'
                  : 'Checking';

    return (
        <div
            className={cn(
                'inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-bold',
                status === 'connected' && 'border-success/30 bg-success/5 text-success',
                status === 'syncing' && 'border-info/30 bg-info/5 text-info',
                status === 'reconnecting' && 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange',
                status === 'disconnected' && 'border-danger/30 bg-danger/5 text-danger',
                status === 'unknown' && 'border-border bg-lightBackground text-slate-500',
            )}
        >
            <Icon className={cn('h-4 w-4', busy && 'animate-spin')} />
            {label}
        </div>
    );
}
