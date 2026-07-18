import { Head, Link } from '@inertiajs/react';
import { BookOpen, Boxes, FileQuestion, GraduationCap, ShieldCheck, Users } from 'lucide-react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type School = Record<string, any>;

const links = [
    ['Programmes', 'programmes'],
    ['Courses', 'courses'],
    ['Modules', 'modules'],
    ['Training Batches', 'training-batches'],
    ['Facilitators', 'facilitators'],
    ['Question Banks', 'question-banks'],
    ['Certificates', 'certificates'],
    ['Candidates', 'candidates'],
] as const;

export default function ProfessionalSchoolShow({ professionalSchool, dashboard }: { professionalSchool: School; dashboard: Record<string, number> }) {
    return (
        <PortalAppShell title={professionalSchool.name}>
            <Head title={professionalSchool.name} />
            <PageHeader
                eyebrow="Professional School"
                title={professionalSchool.name}
                description={professionalSchool.organization_name || 'Standalone'}
                actions={<div className="flex flex-wrap gap-2"><Button asChild variant="secondary"><Link href={`/professional-schools/${professionalSchool.id}/edit`}>Edit</Link></Button><Button asChild><Link href="/exams/create">Create Exam</Link></Button></div>}
            />

            <div className="mb-6 grid gap-3 md:grid-cols-4">
                <Metric icon={GraduationCap} label="Programmes" value={dashboard.total_programmes} />
                <Metric icon={BookOpen} label="Courses" value={dashboard.total_courses} />
                <Metric icon={Boxes} label="Modules" value={dashboard.total_modules} />
                <Metric icon={Users} label="Candidates" value={dashboard.total_candidates} />
                <Metric icon={ShieldCheck} label="Professional Exams" value={dashboard.professional_exams} />
                <Metric icon={ShieldCheck} label="Adaptive Exams" value={dashboard.adaptive_exams} />
                <Metric icon={ShieldCheck} label="Certification Exams" value={dashboard.certification_exams} />
                <Metric icon={FileQuestion} label="Certificates" value={dashboard.certificates_generated} />
            </div>

            <div className="mb-6 flex flex-wrap gap-2">
                {links.map(([label, slug]) => <Button key={slug} asChild variant="secondary"><Link href={`/professional-schools/${professionalSchool.id}/${slug}`}>{label}</Link></Button>)}
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <DataTable rows={professionalSchool.programmes ?? []} emptyTitle="No programmes" columns={[{ key: 'name', header: 'Programme' }, { key: 'duration', header: 'Duration' }, { key: 'courses_count', header: 'Courses' }]} />
                <DataTable rows={professionalSchool.recent_exams ?? []} emptyTitle="No recent exams" columns={[{ key: 'title', header: 'Exam' }, { key: 'code', header: 'Code' }, { key: 'category', header: 'Category' }, { key: 'mode', header: 'Mode' }, { key: 'status', header: 'Status', render: (exam: School) => <StatusBadge label={exam.status} tone="neutral" /> }]} />
                <DataTable rows={professionalSchool.candidates ?? []} emptyTitle="No candidates" columns={[{ key: 'registration_number', header: 'Registration' }, { key: 'full_name', header: 'Name' }, { key: 'programme_name', header: 'Programme' }, { key: 'status', header: 'Status' }]} />
                <DataTable rows={professionalSchool.certificates ?? []} emptyTitle="No certificates" columns={[{ key: 'serial_number', header: 'Serial' }, { key: 'candidate_name', header: 'Candidate' }, { key: 'status', header: 'Status' }]} />
            </div>
        </PortalAppShell>
    );
}

function Metric({ icon: Icon, label, value }: { icon: typeof GraduationCap; label: string; value: number }) {
    return <div className="rounded-md border border-border bg-white p-4"><Icon className="mb-3 h-5 w-5 text-primary" /><div className="text-2xl font-bold text-slateDark">{value ?? 0}</div><div className="text-sm text-slate-600">{label}</div></div>;
}
