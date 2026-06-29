import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionBankForm } from './Form';
import { StatusOption, SubjectOption } from './types';

export default function CreateQuestionBank({ subjects, statuses }: { subjects: { data: SubjectOption[] }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Question Bank">
            <Head title="Create Question Bank" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Question Bank" title="Create Question Bank" description="Add a question-bank container for a subject." />
                <QuestionBankForm subjects={subjects} statuses={statuses} submitLabel="Create Bank" />
            </section>
        </PortalAppShell>
    );
}
