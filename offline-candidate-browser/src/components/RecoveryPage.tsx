import type { ReactNode } from 'react';
import { AppLogo } from './AppLogo';
import { AppShell } from './AppShell';
import { PageCard } from './PageCard';
import { Button } from './ui/button';
import { cn } from '../utils/cn';

type Tone = 'error' | 'warning' | 'info' | 'success';

export function RecoveryPage({
    icon,
    tone,
    title,
    explanation,
    action,
    retryLabel = 'Retry',
    backLabel = 'Back to Login',
    onRetry,
    onBackToLogin,
}: {
    icon: ReactNode;
    tone: Tone;
    title: string;
    explanation: string;
    action: string;
    retryLabel?: string;
    backLabel?: string;
    onRetry?: () => void;
    onBackToLogin?: () => void;
}) {
    return (
        <AppShell>
            <PageCard className="max-w-lg">
                <div className="flex justify-center">
                    <AppLogo />
                </div>

                <div className="mt-6 text-center">
                    <div
                        className={cn(
                            'mx-auto flex h-16 w-16 items-center justify-center rounded-md',
                            tone === 'error' && 'bg-danger/10 text-danger',
                            tone === 'warning' && 'bg-accentOrange/10 text-accentOrange',
                            tone === 'info' && 'bg-info/10 text-info',
                            tone === 'success' && 'bg-success/10 text-success',
                        )}
                    >
                        {icon}
                    </div>
                    <h1 className="mt-5 text-2xl font-bold text-slateDark">{title}</h1>
                    <p className="mt-3 text-base font-semibold leading-7 text-slate-600">{explanation}</p>
                </div>

                <div
                    className={cn(
                        'mt-6 rounded-md border p-4 text-sm font-semibold leading-6',
                        tone === 'error' && 'border-danger/30 bg-danger/5 text-danger',
                        tone === 'warning' && 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange',
                        tone === 'info' && 'border-info/30 bg-info/5 text-info',
                        tone === 'success' && 'border-success/30 bg-success/5 text-success',
                    )}
                >
                    {action}
                </div>

                {(onRetry || onBackToLogin) && (
                    <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                        {onBackToLogin && (
                            <Button type="button" variant="outline" onClick={onBackToLogin}>
                                {backLabel}
                            </Button>
                        )}
                        {onRetry && (
                            <Button type="button" onClick={onRetry}>
                                {retryLabel}
                            </Button>
                        )}
                    </div>
                )}
            </PageCard>
        </AppShell>
    );
}
