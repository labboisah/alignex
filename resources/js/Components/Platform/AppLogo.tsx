import { cn } from '@/lib/cn';

type AppLogoProps = {
    compact?: boolean;
    className?: string;
};

export function AppLogo({ compact = false, className }: AppLogoProps) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            <img
                src={compact ? '/images/logo.png' : '/images/brand-logo.png'}
                alt="AlignEx"
                className={compact ? 'h-10 w-10 object-contain' : 'h-12 w-auto max-w-[190px] object-contain'}
            />
        </div>
    );
}
