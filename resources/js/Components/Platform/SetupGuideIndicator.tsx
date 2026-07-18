import { Link, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronUp, Circle, ListChecks, LockKeyhole, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { cn } from '@/lib/cn';

type SetupStep = {
    id: string;
    label: string;
    description: string;
    href?: string | null;
    can: boolean;
    complete: boolean;
    count?: number | null;
};

export type SetupGuide = {
    title: string;
    steps: SetupStep[];
    completed: number;
    total: number;
    progress: number;
    current_step?: SetupStep | null;
    next_step?: SetupStep | null;
    complete: boolean;
    context?: { type: string; id: number; name: string } | null;
};

export function SetupGuideIndicator({ guide }: { guide?: SetupGuide | null }) {
    const { url } = usePage();
    const [open, setOpen] = useState(false);

    const pageStep = useMemo(() => guide?.steps.find((step) => step.href && isActive(url, step.href)) ?? null, [guide, url]);
    const currentStep = pageStep ?? guide?.current_step ?? guide?.next_step ?? null;

    if (!guide || guide.total === 0) {
        return null;
    }

    return (
        <div className={cn('fixed bottom-4 left-4 z-40 w-[calc(100vw-2rem)]', open ? 'max-w-sm' : 'max-w-48 sm:max-w-48')}>
            {open ? (
                <div className="overflow-hidden rounded-md border border-border bg-white shadow-lg">
                    <div className="border-b border-border p-4">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 text-sm font-semibold text-slateDark">
                                    <ListChecks className="h-4 w-4 text-primary" />
                                    <span>{guide.title}</span>
                                </div>
                                <div className="mt-1 truncate text-xs text-slate-500">
                                    {guide.context?.name ? `${guide.context.name} setup` : 'Platform setup'}
                                </div>
                            </div>
                            <button type="button" className="rounded-md p-1 text-slate-500 hover:bg-slate-100" onClick={() => setOpen(false)} aria-label="Close setup guide">
                                <X className="h-4 w-4" />
                            </button>
                        </div>
                        <div className="mt-3">
                            <div className="flex items-center justify-between text-xs font-semibold text-slate-600">
                                <span>{guide.completed} of {guide.total} complete</span>
                                <span>{guide.progress}%</span>
                            </div>
                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full rounded-full bg-primary" style={{ width: `${guide.progress}%` }} />
                            </div>
                        </div>
                    </div>

                    <div className="max-h-[60vh] space-y-1 overflow-y-auto p-2">
                        {guide.steps.map((step, index) => {
                            const active = currentStep?.id === step.id;
                            const content = (
                                <div
                                    className={cn(
                                        'flex gap-3 rounded-md p-3 text-left transition',
                                        active ? 'bg-green-50' : 'hover:bg-slate-50',
                                        !step.can && 'opacity-70',
                                    )}
                                >
                                    <div className="mt-0.5">
                                        {step.complete ? (
                                            <CheckCircle2 className="h-4 w-4 text-success" />
                                        ) : step.can ? (
                                            <Circle className={cn('h-4 w-4', active ? 'text-primary' : 'text-slate-400')} />
                                        ) : (
                                            <LockKeyhole className="h-4 w-4 text-slate-400" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 text-sm font-semibold text-slateDark">
                                            <span>{index + 1}. {step.label}</span>
                                            {active && <span className="rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold uppercase text-white">Now</span>}
                                        </div>
                                        <div className="mt-1 text-xs leading-5 text-slate-600">{step.description}</div>
                                    </div>
                                </div>
                            );

                            return step.href ? (
                                <Link key={step.id} href={step.href} className="block">
                                    {content}
                                </Link>
                            ) : (
                                <div key={step.id}>{content}</div>
                            );
                        })}
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    className="flex w-full items-center gap-2 rounded-md border border-border bg-white p-2 text-left shadow-lg transition hover:border-primary"
                    onClick={() => setOpen(true)}
                >
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-green-50 text-primary">
                        <ListChecks className="h-4 w-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-sm font-semibold text-slateDark">Setup {guide.progress}%</div>
                            <ChevronUp className="h-4 w-4 text-slate-400" />
                        </div>
                        <div className="mt-1 truncate text-xs text-slate-600">
                            {guide.complete ? 'All setup steps are complete.' : `Next: ${currentStep?.label ?? 'Review setup'}`}
                        </div>
                    </div>
                </button>
            )}
        </div>
    );
}

function isActive(url: string, href: string) {
    const path = href.split('?')[0];

    return url === href || (path !== '/' && url.startsWith(path));
}
