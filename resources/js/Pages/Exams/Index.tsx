import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2, XCircle } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Exam } from './types';

export default function ExamsIndex({ exams, can, categories = [], filters = {} }: { exams: { data: Exam[] }; can: { create: boolean }; categories?: string[]; filters?: { category?: string; mode?: string } }) {
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isAssessmentRole = auth.user?.role === 'teacher' || auth.user?.role === 'facilitator';
    const noun = isAssessmentRole ? 'Assessment' : 'Exam';
    const nounPlural = isAssessmentRole ? 'Assessments' : 'Exams/Assessment';

    return (
        <PortalAppShell title={nounPlural}>
            <Head title={nounPlural} />
            <PageHeader
                eyebrow="Assessment"
                title={nounPlural}
                description={isAssessmentRole ? 'Create, configure, monitor, and review assessments for your assigned courses.' : 'Manage all exam and assessment categories in one place.'}
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button"><Link href={isAssessmentRole ? '/exams/create?category=assessment' : '/exams/create'}><Plus className="h-4 w-4" />New {noun}</Link></Button>
                    </ProtectedAction>
                }
            />
            <div className="mb-4 flex flex-wrap gap-4">
                {!isAssessmentRole && <label className="text-sm font-semibold text-slate-700">
                    Category
                    <select className="ml-2 rounded-md border-border text-sm" value={filters.category ?? ''} onChange={(event) => router.get('/exams', { ...filters, category: event.target.value }, { preserveState: true, replace: true })}>
                        <option value="">All categories</option>
                        {categories.map(category => <option key={category} value={category}>{category.replaceAll('_', ' ')}</option>)}
                    </select>
                </label>}
                <label className="text-sm font-semibold text-slate-700">
                    Mode
                    <select className="ml-2 rounded-md border-border text-sm" value={filters.mode ?? ''} onChange={(event) => router.get('/exams', { ...filters, mode: event.target.value }, { preserveState: true, replace: true })}>
                        <option value="">All modes</option>
                        <option value="traditional">Traditional</option>
                        <option value="adaptive">Adaptive</option>
                    </select>
                </label>
            </div>
            <DataTable<Exam>
                rows={exams.data}
                emptyTitle={`No ${nounPlural.toLowerCase()} found`}
                columns={[
                    { key: 'title', header: 'Title', render: (exam) => <span className="font-semibold text-slateDark">{exam.title}</span> },
                    { key: 'exam_code', header: 'Code' },
                    { key: 'exam_type_label', header: 'Type', render: (exam) => exam.exam_type_label ?? exam.exam_type },
                    { key: 'exam_category', header: 'Category', render: (exam) => exam.exam_category?.replaceAll('_', ' ') ?? 'General' },
                    { key: 'mode', header: 'Mode' },
                    { key: 'delivery_mode', header: 'Delivery' },
                    { key: 'subjects_count', header: 'Subjects', render: (exam) => String(exam.subjects_count ?? 0) },
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
