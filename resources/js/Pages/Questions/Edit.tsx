import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionForm } from './Form';
import { Question, QuestionBankOption, SelectOption, SubjectOption, TopicOption } from './types';

export default function EditQuestion({ question, questionBanks, subjects, topics, difficulties, statuses }: { question: { data: Question }; questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] }; difficulties: SelectOption[]; statuses: SelectOption[] }) {
    return (
        <PortalAppShell title="Edit Question">
            <Head title="Edit Question" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Questions" title="Edit Question" description="Update question text, image, options, and answer key." />
                <QuestionForm question={question.data} questionBanks={questionBanks} subjects={subjects} topics={topics} difficulties={difficulties} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
