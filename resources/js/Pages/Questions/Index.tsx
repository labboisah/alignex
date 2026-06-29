import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent } from 'react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Question, QuestionBankOption, SubjectOption, TopicOption } from './types';

type Props = {
    questions: { data: Question[] };
    can: { create: boolean };
    questionBanks: { data: QuestionBankOption[] };
    subjects: { data: SubjectOption[] };
    topics: { data: TopicOption[] };
};

export default function QuestionsIndex({ questions, can, questionBanks, subjects, topics }: Props) {
    return (
        <PortalAppShell title="Questions">
            <Head title="Questions" />
            <PageHeader
                eyebrow="Question Bank"
                title="Questions"
                description="Create, review, preview, and maintain question options and answer keys."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/questions/create">
                                <Plus className="h-4 w-4" />
                                New Question
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <BulkTools templateHref="/questions/template" uploadHref="/questions/import" questionBanks={questionBanks} subjects={subjects} topics={topics} />

            <DataTable<Question>
                rows={questions.data}
                emptyTitle="No questions found"
                columns={[
                    { key: 'stem', header: 'Question', render: (question) => <span className="line-clamp-2 font-semibold text-slateDark">{question.stem}</span> },
                    { key: 'question_bank_name', header: 'Bank', render: (question) => question.question_bank_name ?? 'N/A' },
                    { key: 'subject_name', header: 'Subject', render: (question) => question.subject_name ?? 'N/A' },
                    { key: 'difficulty', header: 'Difficulty', render: (question) => question.difficulty },
                    { key: 'marks', header: 'Marks', render: (question) => String(question.marks) },
                    { key: 'status', header: 'Status', render: (question) => <StatusBadge label={question.status_label} tone={question.status === 'approved' ? 'success' : question.status === 'rejected' ? 'danger' : question.status === 'review' ? 'warning' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (question) => (
                            <ActionDropdown
                                items={[
                                    { label: 'Preview', icon: Eye, onSelect: () => router.visit(`/questions/${question.id}`) },
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/questions/${question.id}/edit`) },
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        onSelect: () => window.confirm('Delete this question?') && router.delete(`/questions/${question.id}`, { preserveScroll: true }),
                                    },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}

function BulkTools({ templateHref, uploadHref, questionBanks, subjects, topics }: { templateHref: string; uploadHref: string; questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] } }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; subject_id: string; question_bank_id: string; topic_id: string }>({
        file: null,
        subject_id: '',
        question_bank_id: '',
        topic_id: '',
    });

    const availableBanks = questionBanks.data.filter((bank) => !data.subject_id || bank.subject_id === data.subject_id);
    const availableTopics = topics.data.filter((topic) => !data.subject_id || topic.subject_id === data.subject_id);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post(uploadHref, { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file') });
    };

    return (
        <form onSubmit={submit} className="mb-5 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm lg:grid-cols-[auto_1fr_1fr_1fr_1fr_auto] lg:items-end">
            <Button asChild type="button" variant="secondary">
                <a href={templateHref}>
                    <Download className="h-4 w-4" />
                    Template
                </a>
            </Button>
            <label className="text-sm font-semibold text-slateDark">
                Subject
                <select
                    className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                    value={data.subject_id}
                    onChange={(event) => setData({ ...data, subject_id: event.target.value, question_bank_id: '', topic_id: '' })}
                    required
                >
                    <option value="">Choose subject</option>
                    {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                </select>
                {errors.subject_id && <span className="mt-1 block text-sm text-danger">{errors.subject_id}</span>}
            </label>
            <label className="text-sm font-semibold text-slateDark">
                Question Bank
                <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.question_bank_id} onChange={(event) => setData('question_bank_id', event.target.value)} required>
                    <option value="">Choose bank</option>
                    {availableBanks.map((bank) => <option key={bank.id} value={bank.id}>{bank.name}</option>)}
                </select>
                {errors.question_bank_id && <span className="mt-1 block text-sm text-danger">{errors.question_bank_id}</span>}
            </label>
            <label className="text-sm font-semibold text-slateDark">
                Topic
                <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.topic_id} onChange={(event) => setData('topic_id', event.target.value)}>
                    <option value="">None</option>
                    {availableTopics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}</option>)}
                </select>
                {errors.topic_id && <span className="mt-1 block text-sm text-danger">{errors.topic_id}</span>}
            </label>
            <label className="text-sm font-semibold text-slateDark">
                Upload CSV
                <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept=".csv,text/csv" onChange={(event) => setData('file', event.target.files?.[0] ?? null)} />
                {errors.file && <span className="mt-1 block text-sm text-danger">{errors.file}</span>}
            </label>
            <Button type="submit" disabled={processing || !data.file || !data.subject_id || !data.question_bank_id}>
                <Upload className="h-4 w-4" />
                Upload
            </Button>
        </form>
    );
}
