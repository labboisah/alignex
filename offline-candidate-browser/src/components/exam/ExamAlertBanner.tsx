import { Info, TriangleAlert } from 'lucide-react';
import { cn } from '../../utils/cn';
import { Button } from '../ui/button';

export function ExamAlertBanner({
    tone = 'info',
    message,
    actionLabel,
    actionDisabled = false,
    onAction,
}: {
    tone?: 'info' | 'warning' | 'danger';
    message: string;
    actionLabel?: string;
    actionDisabled?: boolean;
    onAction?: () => void;
}) {
    const Icon = tone === 'info' ? Info : TriangleAlert;

    return (
        <div
            className={cn(
                'rounded-md border p-4 text-base font-semibold',
                tone === 'info' && 'border-info/30 bg-info/5 text-info',
                tone === 'warning' && 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange',
                tone === 'danger' && 'border-danger/30 bg-danger/5 text-danger',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Icon className="h-5 w-5" />
                    {message}
                </div>
                {actionLabel && onAction && (
                    <Button disabled={actionDisabled} onClick={onAction} size="sm" type="button" variant="outline">
                        {actionLabel}
                    </Button>
                )}
            </div>
        </div>
    );
}
