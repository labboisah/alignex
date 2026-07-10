import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from './types';

export default function ExamsIndex({ exams, can }: { exams: { data: Exam[] }; can: { create: boolean } }) {
    return (
        <PortalAppShell title="Exams">
            <Head title="Exams" />
            <PageHeader
                eyebrow="Assessment"
                title="Exams"
                description="Create, configure, schedule, and operate examinations."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button"><Link href="/exams/create"><Plus className="h-4 w-4" />New Exam</Link></Button>
                    </ProtectedAction>
                }
            />
            <DataTable<Exam>
                rows={exams.data}
                emptyTitle="No exams found"
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
                                    { label: 'Cancel Exam', icon: XCircle, destructive: true, disabled: exam.can?.cancel === false, onSelect: () => window.confirm('Cancel this exam?') && router.patch(`/exams/${exam.id}/cancel`, {}, { preserveScroll: true }) },
                                    { label: 'Delete', icon: Trash2, destructive: true, disabled: exam.can?.delete === false, onSelect: () => window.confirm('Delete this exam?') && router.delete(`/exams/${exam.id}`, { preserveScroll: true }) },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}
