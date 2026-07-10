import { Head, Link } from '@inertiajs/react';
import { BookOpen, ClipboardList, GraduationCap, Users } from 'lucide-react';
import { DashboardCard, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type School = Record<string, unknown> & {
    id: number;
    name: string;
    code: string;
    organization_name?: string | null;
    contact_person: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    status: string;
    status_label: string;
    students?: Array<{ id: number; admission_number: string; full_name: string; class_name?: string | null; status: string }>;
    academic_sessions?: Array<{ id: string; name: string; code: string; is_active: boolean; terms_count: number }>;
    classes?: Array<{ id: string; name: string; code: string; level?: string | null }>;
    subjects?: Array<{ id: string; name: string; code: string }>;
    recent_exams?: Array<{ id: string; title: string; code: string; status: string; category?: string | null; mode?: string | null }>;
};

export default function ShowSecondarySchool({ secondarySchool, dashboard }: { secondarySchool: School; dashboard: Record<string, string | number | null> }) {
    return (
        <PortalAppShell title={secondarySchool.name}>
            <Head title={secondarySchool.name} />
            <section className="mx-auto max-w-7xl">
                <PageHeader eyebrow="Secondary School" title={secondarySchool.name} description={`${secondarySchool.code} - ${secondarySchool.organization_name || 'Standalone school'}`} />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <DashboardCard title="Total Students" value={dashboard.total_students ?? 0} description="Registered students." icon={Users} />
                    <DashboardCard title="Total Classes" value={dashboard.total_classes ?? 0} description="Academic classes." icon={GraduationCap} />
                    <DashboardCard title="Total Subjects" value={dashboard.total_subjects ?? 0} description="Subject catalogue." icon={BookOpen} />
                    <DashboardCard title="Exams" value={dashboard.terminal_exams ?? 0} description="Secondary school exams." icon={ClipboardList} />
                </div>

                <div className="mt-6 grid gap-6 lg:grid-cols-[360px_1fr]">
                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold text-slateDark">Profile</h2>
                        <div className="mt-4 space-y-3 text-sm">
                            <Info label="Contact" value={secondarySchool.contact_person} />
                            <Info label="Email" value={secondarySchool.email} />
                            <Info label="Phone" value={secondarySchool.phone || 'N/A'} />
                            <Info label="Address" value={secondarySchool.address || 'N/A'} />
                            <StatusBadge label={secondarySchool.status_label} tone={secondarySchool.status === 'active' ? 'success' : 'neutral'} />
                        </div>
                        <div className="mt-5 flex flex-wrap gap-2">
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/academic-sessions`}>Academic Sessions</Link></Button>
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/terms`}>Terms</Link></Button>
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/classes`}>Classes</Link></Button>
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/student-groups`}>Student Groups</Link></Button>
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/students`}>Students</Link></Button>
                            <Button asChild variant="secondary"><Link href={`/secondary-schools/${secondarySchool.id}/teachers`}>Teachers</Link></Button>
                            <Button asChild variant="secondary"><Link href="/exams/create">Create Exam</Link></Button>
                            <Button asChild variant="secondary"><Link href="/results">Results</Link></Button>
                        </div>
                    </section>

                    <div className="grid gap-6 xl:grid-cols-2">
                        <Panel title="Academic Sessions" empty="No sessions yet" items={secondarySchool.academic_sessions?.map((row) => `${row.name} (${row.code})${row.is_active ? ' - Active' : ''}`) ?? []} />
                        <Panel title="Classes" empty="No classes yet" items={secondarySchool.classes?.map((row) => `${row.name} - ${row.level || row.code}`) ?? []} />
                        <Panel title="Students" empty="No students yet" items={secondarySchool.students?.map((row) => `${row.full_name} - ${row.admission_number} - ${row.class_name || 'No class'}`) ?? []} />
                        <Panel title="Subjects" empty="No subjects yet" items={secondarySchool.subjects?.map((row) => `${row.name} (${row.code})`) ?? []} />
                        <Panel title="Recent Exams" empty="No exams yet" items={secondarySchool.recent_exams?.map((row) => `${row.title} - ${row.code} - ${row.status}`) ?? []} />
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return <div><div className="font-semibold text-slate-500">{label}</div><div className="text-slateDark">{value}</div></div>;
}

function Panel({ title, items, empty }: { title: string; items: string[]; empty: string }) {
    return <section className="rounded-md border border-border bg-white p-5 shadow-sm"><h2 className="font-semibold text-slateDark">{title}</h2><div className="mt-4 space-y-2">{items.length === 0 ? <div className="rounded-md border border-dashed border-border p-4 text-sm text-slate-500">{empty}</div> : items.map((item) => <div key={item} className="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">{item}</div>)}</div></section>;
}
