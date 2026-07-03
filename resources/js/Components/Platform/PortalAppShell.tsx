import { usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    BookOpen,
    Building2,
    ClipboardList,
    FileQuestion,
    FileText,
    GraduationCap,
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
import { SetupGuide, SetupGuideIndicator } from './SetupGuideIndicator';

const iconByLabel = {
    Dashboard: LayoutDashboard,
    Organizations: Building2,
    'Access Controls': ShieldCheck,
    Applications: Users,
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
    children?: SharedNavItem[];
};

type SharedProps = {
    auth?: {
        navigation?: SharedNavItem[];
        setup_guide?: SetupGuide | null;
    };
    flash?: {
        success?: string;
        error?: string;
    };
};

export function PortalAppShell({ title, children, navItems, topbarActions }: { title?: string; children: ReactNode; navItems?: PortalNavItem[]; topbarActions?: ReactNode }) {
    const pageProps = usePage().props as SharedProps;
    const sharedNav = pageProps.auth?.navigation ?? [];
    const setupGuide = pageProps.auth?.setup_guide ?? null;
    const flash = pageProps.flash;
    const resolvedItems = navItems ?? sharedNav.map(resolveNavItem);

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
                    <SetupGuideIndicator guide={setupGuide} />
                </div>
            </div>
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
