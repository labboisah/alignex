import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Pencil, Trash2 } from 'lucide-react';
import { PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Question } from './types';

export default function ShowQuestion({ question, can }: { question: { data: Question }; can: { update: boolean; delete: boolean } }) {
    const record = question.data;

    return (
        <PortalAppShell title="Question Preview">
            <Head title="Question Preview" />
            <section className="mx-auto max-w-4xl">
                <PageHeader
                    eyebrow="Question Preview"
                    title={record.question_bank_name ?? 'Question'}
                    description="Admin preview includes the correct answer and explanation."
                    actions={
                        <>
                            <ProtectedAction allowed={can.update}>
                                <Button asChild type="button" variant="secondary">
                                    <Link href={`/questions/${record.id}/edit`}>
                                        <Pencil className="h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>
                            </ProtectedAction>
                            <ProtectedAction allowed={can.delete}>
                                <Button type="button" variant="danger" onClick={() => window.confirm('Delete this question?') && router.delete(`/questions/${record.id}`)}>
                                    <Trash2 className="h-4 w-4" />
                                    Delete
                                </Button>
                            </ProtectedAction>
                        </>
                    }
                />

                <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <StatusBadge label={record.status_label} tone={record.status === 'approved' ? 'success' : record.status === 'rejected' ? 'danger' : record.status === 'review' ? 'warning' : 'neutral'} />
                        <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{record.difficulty}</span>
                        <span className="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{record.marks} marks</span>
                    </div>

                    <div className="whitespace-pre-wrap text-base font-semibold leading-7 text-slateDark">{record.stem}</div>
                    {record.image_url && <img src={record.image_url} alt="Question" className="mt-4 max-h-96 rounded-md border border-border object-contain" />}

                    <div className="mt-6 space-y-3">
                        {record.options?.map((option) => (
                            <div key={option.label} className={`flex gap-3 rounded-md border p-3 ${option.is_correct ? 'border-success bg-green-50' : 'border-border'}`}>
                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-sm font-bold text-slateDark">{option.label}</div>
                                <div className="min-w-0 flex-1 text-sm leading-6 text-slate-700">{option.option_text}</div>
                                {option.is_correct && <CheckCircle2 className="h-5 w-5 text-success" />}
                            </div>
                        ))}
                    </div>

                    {record.explanation && (
                        <div className="mt-6 rounded-md border border-border bg-slate-50 p-4">
                            <div className="text-sm font-semibold text-slateDark">Explanation</div>
                            <div className="mt-2 whitespace-pre-wrap text-sm leading-6 text-slate-700">{record.explanation}</div>
                        </div>
                    )}
                </div>
            </section>
        </PortalAppShell>
    );
}
