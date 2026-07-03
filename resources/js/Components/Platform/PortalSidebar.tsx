import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LucideIcon } from 'lucide-react';
import { AppLogo } from './AppLogo';
import { cn } from '@/lib/cn';
import { getContextTerminology } from '@/lib/terminology';

export type PortalNavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    children?: PortalNavItem[];
};

export function PortalSidebar({ items, className }: { items: PortalNavItem[]; className?: string }) {
    const { url, props } = usePage();
    const context = props.current_context as { type?: string } | null | undefined;
    const terms = getContextTerminology(context?.type);

    return (
        <aside className={cn('hidden min-h-screen w-64 border-r border-border bg-white lg:block', className)}>
            <div className="flex h-16 items-center border-b border-border px-5">
                <AppLogo />
            </div>
            <nav className="space-y-1 p-3">
                {items.map((item) => {
                    const Icon = item.icon;
                    const childActive = item.children?.some((child) => isActive(url, child.href)) ?? false;
                    const active = isActive(url, item.href) || childActive;

                    if (item.children?.length) {
                        return (
                            <details key={item.label} className="group" open={active}>
                                <summary
                                    className={cn(
                                        'flex h-10 cursor-pointer list-none items-center gap-3 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100',
                                        active && 'bg-green-50 text-primary',
                                    )}
                                    title={`${item.label} | ${terms.examLabel}`}
                                >
                                    <Icon className="h-4 w-4" />
                                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                    <ChevronDown className="h-4 w-4 transition group-open:rotate-180" />
                                </summary>
                                <div className="mt-1 space-y-1 pl-4">
                                    {item.children.map((child) => {
                                        const ChildIcon = child.icon;
                                        const childIsActive = isActive(url, child.href);

                                        return (
                                            <Link
                                                key={`${item.label}:${child.label}:${child.href}`}
                                                href={child.href}
                                                className={cn(
                                                    'flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium text-slate-600 hover:bg-slate-100',
                                                    childIsActive && 'bg-green-50 text-primary',
                                                )}
                                                title={`${child.label} | ${terms.examLabel}`}
                                            >
                                                <ChildIcon className="h-4 w-4" />
                                                <span className="min-w-0 truncate">{child.label}</span>
                                            </Link>
                                        );
                                    })}
                                </div>
                            </details>
                        );
                    }

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100',
                                active && 'bg-green-50 text-primary',
                            )}
                            title={`${item.label} | ${terms.examLabel}`}
                        >
                            <Icon className="h-4 w-4" />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>
        </aside>
    );
}

function isActive(url: string, href: string) {
    return href !== '#' && (url === href || (href !== '/' && url.startsWith(href)));
}
