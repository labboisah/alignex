import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { ActionDropdown, AlertBanner, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
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
    const currentContext = usePage().props.current_context as { type?: string } | undefined;
    const isSecondary = currentContext?.type === 'secondary_school';
    const isInstitution = currentContext?.type === 'institution' || questions.data.some((question) => question.institution_id);
    const isProfessional = currentContext?.type === 'professional_school' || questions.data.some((question) => question.professional_school_id);
    const isCbt = currentContext?.type === 'cbt_center';

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

            <BulkTools templateHref="/questions/template" uploadHref="/questions/import" questionBanks={questionBanks} subjects={subjects} topics={topics} isSecondary={isSecondary} isInstitution={isInstitution} isProfessional={isProfessional} isCbt={isCbt} />

            <DataTable<Question>
                rows={questions.data}
                emptyTitle="No questions found"
                columns={[
                    { key: 'stem', header: 'Question', render: (question) => <span className="line-clamp-2 font-semibold text-slateDark">{question.stem}</span> },
                    { key: 'question_bank_name', header: 'Bank', render: (question) => question.question_bank_name ?? 'N/A' },
                    { key: 'structure', header: isInstitution ? 'Course' : isProfessional ? 'Course / Module' : 'Subject', render: (question) => isInstitution ? question.question_bank_course_name ?? 'N/A' : isProfessional ? [question.question_bank_course_name, question.question_bank_module_name].filter(Boolean).join(' / ') || question.subject_name || 'N/A' : question.subject_name ?? 'N/A' },
                    { key: 'difficulty', header: 'Difficulty', render: (question) => question.difficulty },
                    { key: 'marks', header: 'Marks', render: (question) => String(question.marks) },
                    { key: 'status', header: 'Status', render: (question) => <StatusBadge label={question.status_label} tone={question.status === 'approved' ? 'success' : question.status === 'rejected' ? 'danger' : question.status === 'review' ? 'warning' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (question) => (
                            <ActionDropdown
                                items={[
                                    { label: 'Preview', icon: Eye, disabled: question.can?.view === false, onSelect: () => router.visit(`/questions/${question.id}`) },
                                    { label: 'Edit', icon: Pencil, disabled: question.can?.update === false, onSelect: () => router.visit(`/questions/${question.id}/edit`) },
                                    {
                                        label: 'Delete',
                                        icon: Trash2,
                                        destructive: true,
                                        disabled: question.can?.delete === false,
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

function BulkTools({ templateHref, uploadHref, questionBanks, subjects, topics, isSecondary, isInstitution, isProfessional, isCbt }: { templateHref: string; uploadHref: string; questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] }; isSecondary: boolean; isInstitution: boolean; isProfessional: boolean; isCbt: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; course_id: string; subject_id: string; question_bank_id: string; topic_id: string }>({
        file: null,
        course_id: '',
        subject_id: '',
        question_bank_id: '',
        topic_id: '',
    });
    const [uploadStatus, setUploadStatus] = useState<{ tone: 'success' | 'danger'; title: string } | null>(null);

    const courseOptions = Array.from(
        new Map(
            questionBanks.data
                .filter((bank) => bank.course_id)
                .map((bank) => [String(bank.course_id), { id: String(bank.course_id), name: bank.course_name ?? 'Course' }]),
        ).values(),
    ).sort((first, second) => first.name.localeCompare(second.name));
    const availableBanks = isInstitution
        ? data.course_id
            ? questionBanks.data.filter((bank) => String(bank.course_id ?? '') === data.course_id)
            : []
        : data.subject_id
            ? questionBanks.data.filter((bank) => bank.subject_id === data.subject_id)
            : [];
    const availableTopics = topics.data.filter((topic) => !data.subject_id || topic.subject_id === data.subject_id);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setUploadStatus(null);

        post(uploadHref, {
            forceFormData: true,
            preserveScroll: true,
            onBefore: () => {
                if (isSecondary || isInstitution) {
                    setData({ ...data, subject_id: isInstitution ? '' : data.subject_id, topic_id: '' });
                }
            },
            onSuccess: () => {
                reset('file');
                setUploadStatus({ tone: 'success', title: 'Questions uploaded successfully.' });
            },
            onError: (formErrors) => {
                const firstError = Object.values(formErrors)[0];
                setUploadStatus({
                    tone: 'danger',
                    title: typeof firstError === 'string' ? firstError : 'Question upload failed. Check the form and try again.',
                });
            },
        });
    };

    return (
        <div className="mb-5 space-y-3">
            {uploadStatus && <AlertBanner tone={uploadStatus.tone} title={uploadStatus.title} />}
            <form onSubmit={submit} className={`grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm lg:items-end ${isCbt || isSecondary || isInstitution ? 'lg:grid-cols-[auto_1fr_1fr_1fr_auto]' : 'lg:grid-cols-[auto_1fr_1fr_1fr_1fr_auto]'}`}>
                <Button asChild type="button" variant="secondary">
                    <a href={templateHref}>
                        <Download className="h-4 w-4" />
                        Template
                    </a>
                </Button>
                {isInstitution ? (
                    <label className="text-sm font-semibold text-slateDark">
                        Course
                        <select
                            className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                            value={data.course_id}
                            onChange={(event) => setData({ ...data, course_id: event.target.value, question_bank_id: '', subject_id: '', topic_id: '' })}
                            required
                        >
                            <option value="">Choose course</option>
                            {courseOptions.map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
                        </select>
                    </label>
                ) : (
                    <label className="text-sm font-semibold text-slateDark">
                        {isProfessional ? 'Course / Module Mapping' : 'Subject'}
                        <select
                            className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm"
                            value={data.subject_id}
                            onChange={(event) => setData({ ...data, subject_id: event.target.value, question_bank_id: '', topic_id: '' })}
                            required
                        >
                            <option value="">{isProfessional ? 'Choose mapping' : 'Choose subject'}</option>
                            {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                        </select>
                        {errors.subject_id && <span className="mt-1 block text-sm text-danger">{errors.subject_id}</span>}
                    </label>
                )}
                <label className="text-sm font-semibold text-slateDark">
                    Question Bank
                    <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.question_bank_id} onChange={(event) => setData('question_bank_id', event.target.value)} required>
                        <option value="">{isInstitution ? (data.course_id ? 'Choose bank' : 'Choose course first') : (data.subject_id ? 'Choose bank' : 'Choose subject first')}</option>
                        {availableBanks.map((bank) => <option key={bank.id} value={bank.id}>{bank.name}</option>)}
                    </select>
                    {errors.question_bank_id && <span className="mt-1 block text-sm text-danger">{errors.question_bank_id}</span>}
                </label>
                {!isCbt && !isSecondary && !isInstitution && (
                    <label className="text-sm font-semibold text-slateDark">
                        {isProfessional ? 'Module Detail' : 'Topic'}
                        <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.topic_id} onChange={(event) => setData('topic_id', event.target.value)}>
                            <option value="">None</option>
                            {availableTopics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name}</option>)}
                        </select>
                        {errors.topic_id && <span className="mt-1 block text-sm text-danger">{errors.topic_id}</span>}
                    </label>
                )}
                <label className="text-sm font-semibold text-slateDark">
                    Upload CSV
                    <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept=".csv,text/csv" onChange={(event) => setData('file', event.target.files?.[0] ?? null)} />
                    {errors.file && <span className="mt-1 block text-sm text-danger">{errors.file}</span>}
                </label>
                <Button type="submit" disabled={processing || !data.file || (isInstitution ? !data.course_id : !data.subject_id) || !data.question_bank_id}>
                    <Upload className="h-4 w-4" />
                    Upload
                </Button>
            </form>
        </div>
    );
}
