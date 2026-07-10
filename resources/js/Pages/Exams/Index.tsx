import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from './types';

export default function ExamsIndex({ exams, can }: { exams: { data: Exam[] }; can: { create: boolean } }) {
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isTeacher = auth.user?.role === 'teacher';
    const noun = isTeacher ? 'Assessment' : 'Exam';
    const nounPlural = isTeacher ? 'Assessments' : 'Exams';

    return (
        <PortalAppShell title={nounPlural}>
            <Head title={nounPlural} />
            <PageHeader
                eyebrow={isTeacher ? 'Assessment' : 'Assessment'}
                title={nounPlural}
                description={isTeacher ? 'Create, configure, monitor, and review assessments for your assigned subjects.' : 'Create, configure, schedule, and operate examinations.'}
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button"><Link href={isTeacher ? '/exams/create?category=assessment' : '/exams/create'}><Plus className="h-4 w-4" />New {noun}</Link></Button>
                    </ProtectedAction>
                }
            />
            <DataTable<Exam>
                rows={exams.data}
                emptyTitle={`No ${nounPlural.toLowerCase()} found`}
                columns={[
                    { key: 'title', header: 'Title', render: (exam) => <span className="font-semibold text-slateDark">{exam.title}</span> },
                    { key: 'exam_code', header: 'Code' },
                    { key: 'exam_type_label', header: 'Type', render: (exam) => exam.exam_type_label ?? exam.exam_type },
                    { key: 'delivery_mode', header: 'Delivery' },
                    { key: 'start_at', header: 'Start' },
                    { key: 'total_marks', header: 'Marks', render: (exam) => String(exam.total_marks) },
                    { key: 'status', header: 'Status', render: (exam) => <StatusBadge label={exam.status_label} tone={exam.status === 'active' ? 'success' : exam.status === 'cancelled' ? 'danger' : exam.status === 'scheduled' ? 'info' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (exam) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, disabled: exam.can?.view === false, onSelect: () => router.visit(`/exams/${exam.id}`) },
                                    { label: 'Edit', icon: Pencil, disabled: exam.can?.update === false, onSelect: () => router.visit(`/exams/${exam.id}/edit`) },
                                    { label: `Cancel ${noun}`, icon: XCircle, destructive: true, disabled: exam.can?.cancel === false, onSelect: () => window.confirm(`Cancel this ${noun.toLowerCase()}?`) && router.patch(`/exams/${exam.id}/cancel`, {}, { preserveScroll: true }) },
                                    { label: 'Delete', icon: Trash2, destructive: true, disabled: exam.can?.delete === false, onSelect: () => window.confirm(`Delete this ${noun.toLowerCase()}?`) && router.delete(`/exams/${exam.id}`, { preserveScroll: true }) },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}
