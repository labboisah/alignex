import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionForm } from './Form';
import { QuestionBankOption, SelectOption, SubjectOption, TopicOption } from './types';

export default function CreateQuestion({ questionBanks, subjects, topics, difficulties, statuses }: { questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] }; difficulties: SelectOption[]; statuses: SelectOption[] }) {
    return (
        <PortalAppShell title="Create Question">
            <Head title="Create Question" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Questions" title="Create Question" description="Add a single-choice question with options and a correct answer." />
                <QuestionForm questionBanks={questionBanks} subjects={subjects} topics={topics} difficulties={difficulties} statuses={statuses} submitLabel="Create Question" />
            </section>
        </PortalAppShell>
    );
}
