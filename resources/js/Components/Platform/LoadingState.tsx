import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

export function LoadingState({ message = 'Loading...', className }: { message?: string; className?: string }) {
    return (
        <div className={cn('flex min-h-40 flex-col items-center justify-center rounded-md border border-dashed border-border bg-white p-6 text-sm text-slate-500', className)}>
            <Loader2 className="mb-3 h-6 w-6 animate-spin text-primary" />
            {message}
        </div>
    );
}
