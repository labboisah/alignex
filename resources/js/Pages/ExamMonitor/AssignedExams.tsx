import { Head, Link } from '@inertiajs/react';
import { Eye, FileText, Settings } from 'lucide-react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type AssignedExam = {
    id: string;
    title: string;
    exam_code: string;
    course_label?: string | null;
    exam_category?: string | null;
    delivery_mode?: string | null;
    duration_minutes: number;
    starts_at?: string | null;
    ends_at?: string | null;
    status: string;
    status_label: string;
    supervision_role: string;
    can_manage: boolean;
};

export default function AssignedExams({ exams }: { exams: AssignedExam[] }) {
    return (
        <PortalAppShell title="Supervision Assessments">
            <Head title="Supervision Assessments" />
            <PageHeader
                eyebrow="Supervision"
                title="Assessments"
                description="Active and scheduled assessments assigned to you for supervision."
            />

            <DataTable<AssignedExam>
                rows={exams}
                emptyTitle="No active supervision assessments"
                columns={[
                    { key: 'title', header: 'Title', render: (exam) => <span className="font-semibold text-slateDark">{exam.title}</span> },
                    { key: 'course_label', header: 'Course', render: (exam) => exam.course_label ?? 'N/A' },
                    { key: 'exam_code', header: 'Code' },
                    { key: 'exam_category', header: 'Category', render: (exam) => exam.exam_category ?? 'Assessment' },
                    { key: 'delivery_mode', header: 'Delivery', render: (exam) => exam.delivery_mode ?? 'N/A' },
                    { key: 'starts_at', header: 'Start', render: (exam) => formatDateTime(exam.starts_at) },
                    { key: 'duration_minutes', header: 'Duration', render: (exam) => `${exam.duration_minutes} mins` },
                    { key: 'supervision_role', header: 'Role', render: (exam) => exam.supervision_role.replaceAll('_', ' ') },
                    { key: 'status', header: 'Status', render: (exam) => <StatusBadge label={exam.status_label} tone={exam.status === 'active' ? 'success' : exam.status === 'scheduled' ? 'info' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (exam) => (
                            <div className="flex flex-wrap gap-2">
                                {exam.can_manage && (
                                    <Button asChild type="button" size="sm" variant="secondary">
                                        <Link href={`/exams/${exam.id}`}><Settings className="h-4 w-4" />Manage</Link>
                                    </Button>
                                )}
                                <Button asChild type="button" size="sm" variant="secondary">
                                    <Link href={`/exams/${exam.id}/monitor`}><Eye className="h-4 w-4" />Monitor</Link>
                                </Button>
                                <Button asChild type="button" size="sm" variant="secondary">
                                    <Link href={`/exams/${exam.id}/monitor/incident-report`}><FileText className="h-4 w-4" />Report</Link>
                                </Button>
                            </div>
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}

function formatDateTime(value?: string | null): string {
    return value ? new Date(value).toLocaleString() : 'N/A';
}
