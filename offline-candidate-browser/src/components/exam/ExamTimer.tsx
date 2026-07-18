import { Clock } from 'lucide-react';
import { cn } from '../../utils/cn';

export function ExamTimer({ seconds }: { seconds: number | null }) {
    const remaining = Math.max(0, seconds ?? 0);
    const critical = remaining < 60;
    const warning = remaining >= 60 && remaining < 300;

    return (
        <div
            className={cn(
                'inline-flex min-w-40 items-center justify-center gap-2 rounded-md border px-4 py-2 text-2xl font-black tabular-nums shadow-sm',
                critical && 'border-danger/50 bg-danger/10 text-danger ring-2 ring-danger/10',
                warning && 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange',
                !critical && !warning && 'border-primary/30 bg-primary/5 text-primary',
            )}
        >
            <Clock className="h-6 w-6" />
            {formatTime(remaining)}
        </div>
    );
}

function formatTime(value: number): string {
    const hours = Math.floor(value / 3600);
    const minutes = Math.floor((value % 3600) / 60);
    const seconds = value % 60;
    return [hours, minutes, seconds].map((part) => String(part).padStart(2, '0')).join(':');
}
