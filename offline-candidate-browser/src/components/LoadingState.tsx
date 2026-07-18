import { Loader2 } from 'lucide-react';

export function LoadingState({ message = 'Loading...' }: { message?: string }) {
    return (
        <div className="flex items-center justify-center gap-3 rounded-md border border-border bg-white p-5 text-sm font-semibold text-slate-600">
            <Loader2 className="h-5 w-5 animate-spin text-info" />
            {message}
        </div>
    );
}
