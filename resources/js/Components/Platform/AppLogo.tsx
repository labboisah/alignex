import { GraduationCap } from 'lucide-react';
import { cn } from '@/lib/cn';

type AppLogoProps = {
    compact?: boolean;
    className?: string;
};

export function AppLogo({ compact = false, className }: AppLogoProps) {
    return (
        <div className={cn('flex items-center gap-3 text-primaryDark', className)}>
            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary text-white">
                <GraduationCap className="h-6 w-6" />
            </div>
            {!compact && (
                <div className="leading-tight">
                    <div className="text-lg font-bold">AlignEx</div>
                    <div className="text-xs font-semibold uppercase text-slate-500">CBT Platform</div>
                </div>
            )}
        </div>
    );
}
