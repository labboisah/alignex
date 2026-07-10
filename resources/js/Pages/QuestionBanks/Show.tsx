import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileQuestion, Pencil, Trash2 } from 'lucide-react';
import { DashboardCard, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { QuestionBank } from './types';

type Props = {
    questionBank: { data: QuestionBank };
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function ShowQuestionBank({ questionBank, can }: Props) {
    const record = questionBank.data;
    const scope = record.organization_name ?? record.professional_school_name ?? record.secondary_school_name ?? record.cbt_center_name ?? record.school_name ?? record.center_name ?? 'Platform';
    const structure = [record.course_name, record.module_name].filter(Boolean).join(' / ') || record.subject_name || 'N/A';

    return (
        <PortalAppShell title={record.name}>
            <Head title={record.name} />
            <PageHeader
                eyebrow="Question Bank"
                title={record.name}
                description={record.description ?? 'Question bank details and ownership scope.'}
                actions={
                    <>
                        <Button asChild type="button" variant="secondary">
                            <Link href="/question-bank">
                                <ArrowLeft className="h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <ProtectedAction allowed={can.update}>
                            <Button asChild type="button" variant="secondary">
                                <Link href={`/question-bank/${record.id}/edit`}>
                                    <Pencil className="h-4 w-4" />
                                    Edit
                                </Link>
                            </Button>
                        </ProtectedAction>
                        <ProtectedAction allowed={can.delete}>
                            <Button type="button" variant="danger" onClick={() => window.confirm('Delete this question bank?') && router.delete(`/question-bank/${record.id}`, { preserveScroll: true })}>
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        </ProtectedAction>
                    </>
                }
            />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <DashboardCard title="Questions" value={record.questions_count ?? 0} description="Questions currently attached." icon={FileQuestion} />
                <InfoCard label="Code" value={record.code} />
                <InfoCard label="Structure" value={structure} />
                <InfoCard label="Scope" value={scope} />
            </div>

            <section className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-slateDark">Status</h2>
                        <p className="mt-1 text-sm text-slate-500">{record.subject_code ? `Subject code: ${record.subject_code}` : 'Subject code unavailable'}</p>
                    </div>
                    <StatusBadge label={record.status_label} tone={record.status === 'active' ? 'success' : record.status === 'archived' ? 'neutral' : 'warning'} />
                </div>
            </section>
        </PortalAppShell>
    );
}

function InfoCard({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="text-sm font-semibold text-slate-500">{label}</div>
            <div className="mt-2 text-lg font-bold text-slateDark">{value}</div>
        </div>
    );
}
