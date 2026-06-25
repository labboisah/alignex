import { Link, usePage } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';
import { AppLogo } from './AppLogo';
import { cn } from '@/lib/cn';

export type PortalNavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
};

export function PortalSidebar({ items, className }: { items: PortalNavItem[]; className?: string }) {
    const { url } = usePage();

    return (
        <aside className={cn('hidden min-h-screen w-64 border-r border-border bg-white lg:block', className)}>
            <div className="flex h-16 items-center border-b border-border px-5">
                <AppLogo />
            </div>
            <nav className="space-y-1 p-3">
                {items.map((item) => {
                    const Icon = item.icon;
                    const active = url === item.href || (item.href !== '/' && url.startsWith(item.href));

                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100',
                                active && 'bg-green-50 text-primary',
                            )}
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
