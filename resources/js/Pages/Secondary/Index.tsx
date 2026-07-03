import { Head } from '@inertiajs/react';
import { BookOpen, ClipboardList, GraduationCap, Users } from 'lucide-react';
import { DataTable, PageHeader, PortalAppShell } from '@/Components/Platform';

type Option = { id: string; name: string; code?: string; status?: string; terms_count?: number; groups_count?: number; terms?: Option[]; groups?: Option[] };

export default function SecondaryIndex({ dashboard, sessions, classes, exams, subjects }: { dashboard: Record<string, number>; sessions: Option[]; classes: Option[]; exams: Option[]; candidates: Option[]; subjects: Option[] }) {
    return (
        <PortalAppShell title="Secondary School">
            <Head title="Secondary School" />
            <PageHeader
                eyebrow="Secondary School"
                title="School Structure"
                description="Manage the academic structure on the dedicated pages for sessions, terms, classes, arms, student groups, students, subjects, and exams."
            />
            <div className="mb-6 grid gap-3 md:grid-cols-4">
                <Metric label="Exams" value={dashboard.secondary_exams} icon={ClipboardList} />
                <Metric label="Students" value={dashboard.students} icon={Users} />
                <Metric label="Classes" value={dashboard.classes} icon={GraduationCap} />
                <Metric label="Subjects" value={subjects.length} icon={BookOpen} />
            </div>
            <div className="grid gap-6 xl:grid-cols-2">
                <DataTable rows={sessions} emptyTitle="No academic sessions" columns={[{ key: 'name', header: 'Session' }, { key: 'terms_count', header: 'Terms' }, { key: 'status', header: 'Status' }]} />
                <DataTable rows={classes} emptyTitle="No classes" columns={[{ key: 'name', header: 'Class' }, { key: 'groups_count', header: 'Student Groups' }, { key: 'status', header: 'Status' }]} />
                <DataTable rows={exams} emptyTitle="No exams" columns={[{ key: 'title', header: 'Exam' }, { key: 'exam_code', header: 'Code' }]} />
                <DataTable rows={subjects} emptyTitle="No subjects" columns={[{ key: 'name', header: 'Subject' }]} />
            </div>
        </PortalAppShell>
    );
}

function Metric({ label, value, icon: Icon }: { label: string; value: number; icon: typeof ClipboardList }) {
    return <div className="rounded-md border border-border bg-white p-4 shadow-sm"><Icon className="mb-3 h-5 w-5 text-primary" /><div className="text-2xl font-bold text-slateDark">{value ?? 0}</div><div className="text-sm text-slate-600">{label}</div></div>;
}
