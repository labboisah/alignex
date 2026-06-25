import { Head } from '@inertiajs/react';
import { BarChart3, BookOpen, Building2, Download, Eye, FileText, LayoutDashboard, Pencil, Plus, Settings, ShieldCheck, Trash2, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Button } from '@/Components/ui/button';
import {
    ActionDropdown,
    AlertBanner,
    AppLogo,
    ChartCard,
    ConfirmDialog,
    DashboardCard,
    DataTable,
    EmptyState,
    ErrorState,
    FormSection,
    LoadingState,
    PageHeader,
    PortalAppShell,
    ProtectedAction,
    RoleBadge,
    StatusBadge,
    type ActionDropdownItem,
    type DataTableColumn,
    type PortalNavItem,
} from '@/Components/Platform';

type PreviewExam = {
    id: number;
    title: string;
    category: string;
    candidates: number;
    status: string;
};

const navItems: PortalNavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Organizations', href: '/organizations', icon: Building2 },
    { label: 'Question Banks', href: '/question-banks', icon: BookOpen },
    { label: 'Reports', href: '/reports', icon: BarChart3 },
    { label: 'Settings', href: '/settings', icon: Settings },
];

const rows: PreviewExam[] = [
    { id: 1, title: 'SS2 Mathematics Mock', category: 'Secondary school', candidates: 182, status: 'Live' },
    { id: 2, title: 'HR Analyst Recruitment', category: 'Recruitment', candidates: 64, status: 'Scheduled' },
    { id: 3, title: 'Safety Certification Level 1', category: 'Certification', candidates: 41, status: 'Draft' },
];

const columns: DataTableColumn<PreviewExam>[] = [
    { key: 'title', header: 'Exam' },
    { key: 'category', header: 'Category' },
    { key: 'candidates', header: 'Candidates' },
    {
        key: 'status',
        header: 'Status',
        render: (row) => (
            <StatusBadge
                label={row.status}
                tone={row.status === 'Live' ? 'success' : row.status === 'Scheduled' ? 'info' : 'neutral'}
            />
        ),
    },
];

const chartData = [
    { name: 'Mon', candidates: 42 },
    { name: 'Tue', candidates: 76 },
    { name: 'Wed', candidates: 58 },
    { name: 'Thu', candidates: 94 },
    { name: 'Fri', candidates: 71 },
];

export default function UiPreviewIndex() {
    const actions: ActionDropdownItem[] = [
        { label: 'View details', icon: Eye, onSelect: () => null },
        { label: 'Edit record', icon: Pencil, onSelect: () => null },
        { label: 'Export', icon: Download, onSelect: () => null },
        { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => null },
    ];

    return (
        <PortalAppShell
            title="UI Preview"
            navItems={navItems}
            topbarActions={<Button type="button" variant="secondary"><Plus className="h-4 w-4" /> New</Button>}
        >
            <Head title="UI Preview" />

            <PageHeader
                eyebrow="Design system"
                title="Platform UI Components"
                description="Reusable Tailwind and shadcn-style components for AlignEx public, admin, monitoring, reporting, and form workflows."
                actions={
                    <>
                        <ActionDropdown items={actions} />
                        <ConfirmDialog
                            title="Publish UI preview?"
                            description="This demonstrates the confirmation dialog component. No data will be changed."
                            confirmLabel="Publish"
                            trigger={<Button type="button">Open Dialog</Button>}
                            onConfirm={() => null}
                        />
                    </>
                }
            />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <DashboardCard title="Active Exams" value="12" description="Live or scheduled today" icon={ShieldCheck} />
                <DashboardCard title="Candidates" value="1,284" description="Assigned across all exams" icon={Users} />
                <DashboardCard title="Question Banks" value="38" description="Prepared for exam creation" icon={BookOpen} />
                <DashboardCard title="Reports" value="9" description="Ready for export" icon={FileText} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
                <ChartCard
                    title="Candidate Activity"
                    description="Recharts wrapped in a reusable chart container."
                    actions={<StatusBadge label="Preview" tone="info" />}
                >
                    <div className="h-72">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={chartData}>
                                <CartesianGrid stroke="#E2E8F0" vertical={false} />
                                <XAxis dataKey="name" />
                                <YAxis allowDecimals={false} />
                                <Tooltip />
                                <Bar dataKey="candidates" fill="#0F7A3A" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </ChartCard>

                <div className="space-y-4">
                    <AlertBanner title="Supervisor monitoring ready" message="Use alert banners for operational notices and anti-cheating policy reminders." tone="info" />
                    <AlertBanner title="Submission window closing" message="Warning banners can highlight urgent exam states." tone="warning" />
                    <div className="rounded-md border border-border bg-white p-5">
                        <h2 className="font-semibold">Identity Elements</h2>
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <AppLogo compact />
                            <RoleBadge role="Exam Manager" />
                            <StatusBadge label="Approved" tone="success" />
                            <StatusBadge label="Flagged" tone="danger" />
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6">
                <DataTable columns={columns} rows={rows} />
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-3">
                <LoadingState message="Loading candidate sessions..." />
                <EmptyState title="No candidates assigned" description="Assignments will appear after candidates are imported or created." action={<Button type="button" variant="secondary">Add Candidate</Button>} />
                <ErrorState title="Import failed" description="The uploaded file contains invalid rows. Download the validation report and try again." action={<Button type="button" variant="danger">Download Report</Button>} />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
                <FormSection
                    title="Exam Settings"
                    description="Use form sections to group related fields in admin workflows."
                    footer={<div className="flex justify-end gap-2"><Button type="button" variant="secondary">Cancel</Button><Button type="button">Save Settings</Button></div>}
                >
                    <label className="grid gap-2">
                        <span className="text-sm font-semibold text-slate-700">Exam title</span>
                        <input className="h-10 rounded-md border-border text-sm focus:border-primary focus:ring-primary" defaultValue="SS2 Mathematics Mock" />
                    </label>
                    <label className="grid gap-2">
                        <span className="text-sm font-semibold text-slate-700">Duration</span>
                        <input className="h-10 rounded-md border-border text-sm focus:border-primary focus:ring-primary" defaultValue="60 minutes" />
                    </label>
                </FormSection>

                <div className="rounded-md border border-border bg-white p-5">
                    <h2 className="font-semibold">Protected Actions</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-600">Use this wrapper to hide or disable actions when policies deny access.</p>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <ProtectedAction allowed>
                            <Button type="button"><ShieldCheck className="h-4 w-4" /> Release Results</Button>
                        </ProtectedAction>
                        <ProtectedAction allowed={false} fallbackLabel="Requires Policy" />
                    </div>
                </div>
            </div>
        </PortalAppShell>
    );
}
