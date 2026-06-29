import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionBankForm } from './Form';
import { QuestionBank, StatusOption, SubjectOption } from './types';

export default function EditQuestionBank({ questionBank, subjects, statuses }: { questionBank: { data: QuestionBank }; subjects: { data: SubjectOption[] }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Question Bank">
            <Head title="Edit Question Bank" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Question Bank" title="Edit Question Bank" description="Update the bank subject, name, code, and lifecycle status." />
                <QuestionBankForm questionBank={questionBank.data} subjects={subjects} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
