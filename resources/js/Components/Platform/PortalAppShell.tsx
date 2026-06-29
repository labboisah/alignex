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
import { AlertBanner } from './AlertBanner';

const iconByLabel = {
    Dashboard: LayoutDashboard,
    Organizations: Building2,
    'Access Controls': ShieldCheck,
    Applications: Users,
    Centers: Building2,
    Schools: Building2,
    'My Schools': Building2,
    Users,
    Subjects: BookOpen,
    Topics: SlidersHorizontal,
    'Question Bank': FileQuestion,
    Questions: FileQuestion,
    Exams: ClipboardList,
    'Recruitment Exams': ClipboardList,
    'Assessment Exams': ClipboardList,
    'Certification Exams': ClipboardList,
    'Adaptive Exams': ClipboardList,
    'Assigned Exams': ClipboardList,
    Candidates: Users,
    Results: BarChart3,
    Reports: FileText,
    Settings,
    'Secondary Schools': Building2,
    'Professional Schools': Building2,
    'CBT Centers': Building2,
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
    flash?: {
        success?: string;
        error?: string;
    };
};

export function PortalAppShell({ title, children, navItems, topbarActions }: { title?: string; children: ReactNode; navItems?: PortalNavItem[]; topbarActions?: ReactNode }) {
    const pageProps = usePage().props as SharedProps;
    const sharedNav = pageProps.auth?.navigation ?? [];
    const flash = pageProps.flash;
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
                    <main className="px-4 py-6 lg:px-6">
                        {(flash?.success || flash?.error) && (
                            <div className="mb-5">
                                {flash.success && <AlertBanner tone="success" title={flash.success} />}
                                {flash.error && <AlertBanner tone="danger" title={flash.error} />}
                            </div>
                        )}
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
