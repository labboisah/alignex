import { TriangleAlert } from 'lucide-react';
import { Button } from './ui/button';

export function ErrorState({ title = 'Something went wrong', message, onRetry }: { title?: string; message: string; onRetry?: () => void }) {
    return (
        <div className="rounded-md border border-danger/30 bg-danger/5 p-5 text-left">
            <div className="flex items-start gap-3">
                <TriangleAlert className="mt-0.5 h-5 w-5 text-danger" />
                <div className="min-w-0 flex-1">
                    <h2 className="font-semibold text-slateDark">{title}</h2>
                    <p className="mt-1 text-sm leading-6 text-slate-600">{message}</p>
                    {onRetry && (
                        <Button className="mt-4" onClick={onRetry} size="sm" variant="outline">
                            Try Again
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
