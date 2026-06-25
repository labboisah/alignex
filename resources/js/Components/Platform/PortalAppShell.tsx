import { LayoutDashboard, Settings, Users } from 'lucide-react';
import { ReactNode } from 'react';
import { PortalNavItem, PortalSidebar } from './PortalSidebar';
import { PortalTopbar } from './PortalTopbar';

const defaultItems: PortalNavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Users', href: '/users', icon: Users },
    { label: 'Settings', href: '/settings', icon: Settings },
];

export function PortalAppShell({ title, children, navItems = defaultItems, topbarActions }: { title?: string; children: ReactNode; navItems?: PortalNavItem[]; topbarActions?: ReactNode }) {
    return (
        <div className="min-h-screen bg-surface text-slateDark">
            <div className="lg:flex">
                <PortalSidebar items={navItems} />
                <div className="min-w-0 flex-1">
                    <PortalTopbar title={title} actions={topbarActions} />
                    <main className="px-4 py-6 lg:px-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
