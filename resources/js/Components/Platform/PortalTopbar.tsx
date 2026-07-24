import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, Menu, Search, UserCircle } from 'lucide-react';
import { ReactNode, useState } from 'react';
import { RoleBadge } from './RoleBadge';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/cn';

export function PortalTopbar({
    title,
    userName = 'AlignEx User',
    role = 'Admin',
    actions,
    className,
    onMobileMenuClick,
}: {
    title?: string;
    userName?: string;
    role?: string;
    actions?: ReactNode;
    className?: string;
    onMobileMenuClick?: () => void;
}) {
    const auth = usePage().props.auth as {
        user?: { name?: string; role?: string; role_label?: string };
        role?: { name?: string; label?: string };
        current_context?: { type: string; id: number; name: string } | null;
        available_contexts?: { type: string; id: number; name: string }[];
    } | undefined;
    const user = auth?.user;
    const currentContext = auth?.current_context;
    const availableContexts = auth?.available_contexts ?? [];
    const displayName = user?.name ?? userName;
    const displayRole = user?.role_label ?? user?.role ?? role;
    const isSuperAdmin = user?.role === 'super_admin' || auth?.role?.name === 'super_admin';
    const contextValue = currentContext ? `${currentContext.type}:${currentContext.id}` : isSuperAdmin ? 'platform' : '';
    const [mobileAccountOpen, setMobileAccountOpen] = useState(false);

    const switchContext = (value: string) => {
        if (value === 'platform') {
            if (contextValue) {
                router.delete('/current-context', { preserveScroll: true });
            }

            return;
        }

        const [context_type, context_id] = value.split(':');

        if (!context_type || !context_id || value === contextValue) {
            return;
        }

        router.patch('/current-context', { context_type, context_id }, { preserveScroll: true });
    };

    return (
        <header className={cn('sticky top-0 z-20 border-b border-border bg-white/95 backdrop-blur', className)}>
            <div className="flex h-16 items-center justify-between gap-4 px-4 lg:px-6">
                <div className="flex min-w-0 items-center gap-3">
                    <Button
                        variant="ghost"
                        type="button"
                        className="h-10 w-10 px-0 lg:hidden"
                        onClick={onMobileMenuClick}
                        aria-label="Open navigation menu"
                    >
                        <Menu className="h-5 w-5" />
                    </Button>
                    <div className="hidden min-w-0 lg:block">
                        <div className="truncate text-sm font-semibold text-slate-500">Portal</div>
                        {title && <div className="truncate font-semibold text-slateDark">{title}</div>}
                    </div>
                </div>
                <div className="hidden max-w-sm flex-1 md:block">
                    <label className="relative block">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input className="h-10 w-full rounded-md border-border pl-9 text-sm focus:border-primary focus:ring-primary" placeholder="Search" />
                    </label>
                </div>
                <div className="flex items-center gap-2">
                    {(availableContexts.length > 1 || isSuperAdmin) && (
                        <select
                            className="hidden h-10 max-w-72 rounded-md border-border text-sm font-semibold text-slate-700 focus:border-primary focus:ring-primary md:block"
                            value={contextValue}
                            onChange={(event) => switchContext(event.target.value)}
                            aria-label="Current context"
                        >
                            {isSuperAdmin && <option value="platform">Platform-wide</option>}
                            {availableContexts.map((context) => (
                                <option key={`${context.type}:${context.id}`} value={`${context.type}:${context.id}`}>
                                    {context.type.replaceAll('_', ' ')}: {context.name}
                                </option>
                            ))}
                        </select>
                    )}
                    {actions && <div className="hidden items-center gap-2 lg:flex">{actions}</div>}
                    <Button variant="ghost" type="button" className="h-10 w-10 px-0">
                        <Bell className="h-4 w-4" />
                    </Button>
                    {user && (
                        <Button
                            variant="secondary"
                            type="button"
                            className="h-10 gap-1 px-2 sm:hidden"
                            onClick={() => setMobileAccountOpen((open) => !open)}
                            aria-expanded={mobileAccountOpen}
                            aria-label="Open account menu"
                        >
                            <UserCircle className="h-5 w-5" />
                            <ChevronDown className="h-4 w-4" />
                        </Button>
                    )}
                    {user && (
                        <Button asChild variant="secondary" className="hidden sm:inline-flex">
                            <Link href="/logout" method="post" as="button">Logout</Link>
                        </Button>
                    )}
                    <div className="hidden text-right sm:block">
                        <div className="text-sm font-semibold text-slateDark">{displayName}</div>
                        <RoleBadge role={displayRole} />
                    </div>
                </div>
            </div>
            {mobileAccountOpen && (
                <div className="border-t border-border bg-white px-4 py-3 shadow-sm sm:hidden">
                    <div className="mb-3">
                        <div className="text-sm font-semibold text-slateDark">{displayName}</div>
                        <RoleBadge role={displayRole} />
                    </div>
                    {(availableContexts.length > 1 || isSuperAdmin) && (
                        <select
                            className="mb-3 h-10 w-full rounded-md border-border text-sm font-semibold text-slate-700 focus:border-primary focus:ring-primary"
                            value={contextValue}
                            onChange={(event) => {
                                switchContext(event.target.value);
                                setMobileAccountOpen(false);
                            }}
                            aria-label="Current context"
                        >
                            {isSuperAdmin && <option value="platform">Platform-wide</option>}
                            {availableContexts.map((context) => (
                                <option key={`${context.type}:${context.id}`} value={`${context.type}:${context.id}`}>
                                    {context.type.replaceAll('_', ' ')}: {context.name}
                                </option>
                            ))}
                        </select>
                    )}
                    <div className="grid grid-cols-2 gap-2">
                        <Button asChild variant="outline" className="w-full">
                            <Link href="/profile">Profile</Link>
                        </Button>
                        <Button asChild variant="secondary" className="w-full">
                            <Link href="/logout" method="post" as="button">Logout</Link>
                        </Button>
                    </div>
                </div>
            )}
        </header>
    );
}
