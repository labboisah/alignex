import QuestionBankPicker, { type ImportCourse, type ImportModule } from '@/Components/Platform/QuestionBankPicker';
import { useRecordFilters } from '@/Components/Platform/RecordFilters';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Download, Eye, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { ActionDropdown, AlertBanner, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Question, QuestionBankOption, SubjectOption, TopicOption } from './types';

type Props = {
    importCourses?: ImportCourse[];
    importModules?: ImportModule[];
    questions: { data: Question[] };
    can: { create: boolean };
    questionBanks: { data: QuestionBankOption[] };
    subjects: { data: SubjectOption[] };
    topics: { data: TopicOption[] };
};

export default function QuestionsIndex({ questions, can, questionBanks, subjects, topics, importCourses = [], importModules = [] }: Props) {
    const pageUrl = usePage().url;
    const [bankId, setBankId] = useState(() => new URLSearchParams(pageUrl.split('?')[1] ?? '').get('bank') ?? '');
    const bulk = useForm<{ question_ids: string[]; status: string }>({ question_ids: [], status: 'approved' });
    const filters = useRecordFilters(questions.data.filter(question => !bankId || question.question_bank_id === bankId));
    const visibleQuestions = filters.filteredRows;
    const eligible = visibleQuestions.filter(question => question.can?.update === true);
    const selectedIds = bulk.data.question_ids.filter(id => eligible.some(question => question.id === id));
    const allSelected = eligible.length > 0 && selectedIds.length === eligible.length;
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

            <BulkTools importCourses={importCourses} importModules={importModules} templateHref="/questions/template" uploadHref="/questions/import" questionBanks={questionBanks} subjects={subjects} topics={topics} isSecondary={isSecondary} isInstitution={isInstitution} isProfessional={isProfessional} isCbt={isCbt} />

            {filters.controls}
            <form aria-label="Update question statuses" className="mb-4 space-y-3 rounded-md border border-border bg-white p-4" onSubmit={event => {
                event.preventDefault();
                bulk.transform(data => ({ ...data, question_ids: selectedIds }));
                bulk.patch('/questions/bulk-status', {
                    preserveScroll: true, onSuccess: () => bulk.reset('question_ids'),
                });
            }}>
                <div className="flex flex-wrap items-end gap-4">
                    <label className="text-sm font-semibold">Filter by bank
                        <select aria-label="Filter by bank" className="mt-1 block rounded-md border-border" value={bankId} disabled={bulk.processing} onChange={event => { setBankId(event.target.value); bulk.reset('question_ids'); bulk.clearErrors(); }}>
                            <option value="">All banks</option>
                            {questionBanks.data.map(bank => <option key={bank.id} value={bank.id}>{bank.name}</option>)}
                        </select>
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" aria-label="Select all questions" checked={allSelected} ref={element => { if (element) element.indeterminate = selectedIds.length > 0 && !allSelected; }}
                            disabled={bulk.processing || !eligible.length} onChange={event => bulk.setData('question_ids', event.target.checked ? eligible.map(question => question.id) : [])} />
                        Select all {bankId ? 'in this bank' : 'listed questions'}
                    </label>
                    <label className="text-sm font-semibold">Set status
                        <select aria-label="Set status" className="mt-1 block rounded-md border-border" value={bulk.data.status} disabled={bulk.processing} onChange={event => bulk.setData('status', event.target.value)}>
                            {['draft', 'review', 'approved', 'rejected', 'archived'].map(status => <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>)}
                        </select>
                    </label>
                    <Button type="submit" disabled={bulk.processing || !selectedIds.length}>{bulk.processing ? 'Updating...' : 'Apply status'}</Button>
                </div>
                <p aria-live="polite" className="text-sm text-slate-600">{selectedIds.length} of {eligible.length} editable questions selected.</p>
                {Object.values(bulk.errors).map((error, index) => <p key={index} role="alert" className="text-sm text-danger">{error}</p>)}
            </form>

            <DataTable<Question>
                rows={visibleQuestions}
                emptyTitle="No questions found"
                columns={[
                    { key: 'selection', header: 'Select', render: question => <input type="checkbox" aria-label={'Select question: ' + question.stem} disabled={bulk.processing || question.can?.update !== true}
                        checked={selectedIds.includes(question.id)} onChange={event => bulk.setData('question_ids', event.target.checked ? [...selectedIds, question.id] : selectedIds.filter(id => id !== question.id))} /> },
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

function BulkTools({ importCourses, importModules, templateHref, uploadHref, questionBanks, topics, isSecondary, isInstitution, isProfessional, isCbt }: { importCourses:ImportCourse[]; importModules:ImportModule[]; templateHref: string; uploadHref: string; questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] }; isSecondary: boolean; isInstitution: boolean; isProfessional: boolean; isCbt: boolean }) {
    const form = useForm<{file:File|null;question_bank_id:string;subject_id:string;topic_id:string}>({file:null,question_bank_id:'',subject_id:'',topic_id:''});
    const [pickerVersion,setPickerVersion] = useState(0);
    const availableTopics = topics.data.filter(topic => topic.subject_id === form.data.subject_id);
    return <form aria-label="Import questions" className="mb-5 space-y-3 rounded border border-border bg-white p-4" onSubmit={event=>{
        event.preventDefault();
        form.post(uploadHref,{forceFormData:true,preserveScroll:true,onSuccess:()=>{form.reset();setPickerVersion(v=>v+1);}});
    }}>
        <h2 className="font-semibold">Import questions</h2>
        <div className="grid gap-3 md:grid-cols-3">
            <QuestionBankPicker courses={importCourses} moduleOptions={importModules} key={pickerVersion} banks={questionBanks.data} courseMode={isInstitution || isProfessional} value={form.data.question_bank_id} disabled={form.processing}
                onChange={bank=>form.setData({...form.data,question_bank_id:bank?.id ?? '',subject_id:bank?.subject_id ?? '',topic_id:''})} />
            {!isSecondary && !isInstitution && !isCbt && <label className="text-sm font-semibold">Topic (optional)<select aria-label="Import topic" className="mt-1 block w-full rounded border-border" disabled={!form.data.question_bank_id || form.processing} value={form.data.topic_id} onChange={event=>form.setData('topic_id',event.target.value)}>
                <option value="">None</option>{availableTopics.map(topic=><option key={topic.id} value={topic.id}>{topic.name}</option>)}
            </select></label>}
            <label className="text-sm font-semibold">Upload CSV<input key={pickerVersion} aria-label="Upload CSV" type="file" accept=".csv,text/csv" disabled={form.processing} onChange={event=>form.setData('file',event.target.files?.[0] ?? null)} /></label>
        </div>
        {Object.entries(form.errors).map(([key,message])=><p key={key} role="alert" className="text-sm text-danger">{message}</p>)}
        <div className="flex gap-3"><Button asChild type="button" variant="secondary"><a href={templateHref}><Download className="h-4 w-4" />Template</a></Button>
            <Button type="submit" disabled={form.processing || !form.data.file || !form.data.question_bank_id}>{form.processing ? 'Importing...' : 'Upload'}</Button></div>
    </form>;
}
