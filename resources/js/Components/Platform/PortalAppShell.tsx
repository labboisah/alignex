import { usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    BookOpen,
    Building2,
    ClipboardList,
    Download,
    FileQuestion,
    FileText,
    GraduationCap,
    LayoutDashboard,
    Monitor,
    MonitorDown,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    X,
} from 'lucide-react';
import { ReactNode, useState } from 'react';
import { PortalNavItem, PortalSidebar } from './PortalSidebar';
import { PortalTopbar } from './PortalTopbar';
import { AlertBanner } from './AlertBanner';
import { SetupGuide, SetupGuideIndicator } from './SetupGuideIndicator';
import { Button } from '@/Components/ui/button';

const iconByLabel = {
    Dashboard: LayoutDashboard,
    Organizations: Building2,
    'Access Controls': ShieldCheck,
    Applications: Users,
    'Pricing Plans': FileText,
    'App Releases': MonitorDown,
    Centers: Building2,
    Schools: Building2,
    'My Schools': Building2,
    Platform: Building2,
    Institutions: Building2,
    Admin: Building2,
    Academics: GraduationCap,
    Reporting: BarChart3,
    Administration: Building2,
    'Academic Setup': GraduationCap,
    'Candidate Management': Users,
    Exam: ClipboardList,
    'Exam Management': ClipboardList,
    'Question Management': FileQuestion,
    'Center Operations': Building2,
    'Academic Sessions': ClipboardList,
    Terms: ClipboardList,
    Classes: GraduationCap,
    'Student Groups': Users,
    Students: Users,
    Teachers: Users,
    Programmes: GraduationCap,
    Courses: BookOpen,
    Modules: SlidersHorizontal,
    'Training Batches': ClipboardList,
    'Candidates / Trainees': Users,
    Users,
    Subjects: BookOpen,
    Topics: SlidersHorizontal,
    'Question Bank': FileQuestion,
    Questions: FileQuestion,
    Exams: ClipboardList,
    'Recruitment Exams': ClipboardList,
    Assessments: ClipboardList,
    'Assessment Exams': ClipboardList,
    'Certification Exams': ClipboardList,
    'Adaptive Exams': ClipboardList,
    'Terminal Exams': ClipboardList,
    'Traditional Exams': ClipboardList,
    'Traditional CBT Exams': ClipboardList,
    'Adaptive CBT Exams': ClipboardList,
    'Continuous Assessment': ClipboardList,
    'Assigned Exams': ClipboardList,
    Candidates: Users,
    'Candidate Groups': Users,
    Results: BarChart3,
    Certificates: FileText,
    'Report Cards': FileText,
    Reports: FileText,
    'Offline Server': Download,
    'Client App': MonitorDown,
    'Activation Codes': ShieldCheck,
    'Manage Activation': ShieldCheck,
    Settings,
    'Center Settings': Settings,
    'Secondary Schools': Building2,
    'Professional Schools': Building2,
    'CBT Centers': Building2,
    'Supervisor Monitor': Monitor,
    'Candidate Activity': Activity,
};

type SharedNavItem = {
    label: keyof typeof iconByLabel;
    href?: string;
    permission?: string;
    feature?: string;
    children?: SharedNavItem[];
};

type SharedProps = {
    auth?: {
        navigation?: SharedNavItem[];
        plan?: { id: number | null; slug: string | null; name: string | null } | null;
        plan_features?: Record<string, boolean>;
        setup_guide?: SetupGuide | null;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};

export function PortalAppShell({
    title,
    children,
    navItems,
    topbarActions,
}: {
    title?: string;
    children: ReactNode;
    navItems?: PortalNavItem[];
    topbarActions?: ReactNode;
}) {
    const pageProps = usePage().props as SharedProps;
    const sharedNav = pageProps.auth?.navigation ?? [];
    const setupGuide = pageProps.auth?.setup_guide ?? null;
    const flash = pageProps.flash;
    const resolvedItems = navItems ?? sharedNav.map(resolveNavItem);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    return (
        <div className="min-h-screen bg-surface text-slateDark">
            <div className="lg:flex">
                <PortalSidebar items={resolvedItems} />
                <div className="min-w-0 flex-1">
                    <PortalTopbar title={title} actions={topbarActions} onMobileMenuClick={() => setMobileNavOpen(true)} />
                    <main className="px-4 py-6 lg:px-6">
                        {(flash?.success || flash?.error) && (
                            <div className="mb-5">
                                {flash.success && <AlertBanner tone="success" title={flash.success} />}
                                {flash.error && <AlertBanner tone="danger" title={flash.error} />}
                            </div>
                        )}
                        {children}
                    </main>
                    <SetupGuideIndicator guide={setupGuide} />
                </div>
            </div>
            {mobileNavOpen && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
                    <button
                        type="button"
                        className="absolute inset-0 bg-slate-950/40"
                        aria-label="Close navigation menu"
                        onClick={() => setMobileNavOpen(false)}
                    />
                    <div className="relative h-full w-72 max-w-[85vw]">
                        <div className="absolute right-3 top-3 z-10">
                            <Button
                                variant="ghost"
                                type="button"
                                className="h-10 w-10 bg-white px-0"
                                onClick={() => setMobileNavOpen(false)}
                                aria-label="Close navigation menu"
                            >
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                        <PortalSidebar items={resolvedItems} mode="mobile" />
                    </div>
                </div>
            )}
        </div>
    );
}

function resolveNavItem(item: SharedNavItem): PortalNavItem {
    return {
        ...item,
        href: item.href ?? '#',
        icon: iconByLabel[item.label] ?? LayoutDashboard,
        children: item.children?.map(resolveNavItem),
    };
}
