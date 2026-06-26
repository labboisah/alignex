import { usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    BookOpen,
    Building2,
    ClipboardList,
    FileQuestion,
    FileText,
    LayoutDashboard,
    Monitor,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { ReactNode } from 'react';
import { PortalNavItem, PortalSidebar } from './PortalSidebar';
import { PortalTopbar } from './PortalTopbar';

const iconByLabel = {
    Dashboard: LayoutDashboard,
    Organizations: Building2,
    'Access Controls': ShieldCheck,
    Centers: Building2,
    Schools: Building2,
    Users,
    Subjects: BookOpen,
    Topics: SlidersHorizontal,
    'Question Bank': FileQuestion,
    Exams: ClipboardList,
    'Assigned Exams': ClipboardList,
    Candidates: Users,
    Results: BarChart3,
    Reports: FileText,
    Settings,
    'Supervisor Monitor': Monitor,
    'Candidate Activity': Activity,
};

type SharedNavItem = {
    label: keyof typeof iconByLabel;
    href: string;
    permission?: string;
};

type SharedProps = {
    auth?: {
        navigation?: SharedNavItem[];
    };
};

export function PortalAppShell({ title, children, navItems, topbarActions }: { title?: string; children: ReactNode; navItems?: PortalNavItem[]; topbarActions?: ReactNode }) {
    const sharedNav = (usePage().props as SharedProps).auth?.navigation ?? [];
    const resolvedItems = navItems ?? sharedNav.map((item) => ({
        ...item,
        icon: iconByLabel[item.label] ?? LayoutDashboard,
    }));

    return (
        <div className="min-h-screen bg-surface text-slateDark">
            <div className="lg:flex">
                <PortalSidebar items={resolvedItems} />
                <div className="min-w-0 flex-1">
                    <PortalTopbar title={title} actions={topbarActions} />
                    <main className="px-4 py-6 lg:px-6">{children}</main>
                </div>
            </div>
        </div>
    );
}
