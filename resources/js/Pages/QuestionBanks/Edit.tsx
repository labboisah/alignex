import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionBankForm } from './Form';
import { CourseOption, QuestionBank, StatusOption, SubjectOption } from './types';

export default function EditQuestionBank({ questionBank, subjects, courses = [], statuses }: { questionBank: { data: QuestionBank }; subjects: { data: SubjectOption[] }; courses?: CourseOption[]; statuses: StatusOption[] }) {
    const isInstitution = courses.length > 0 || Boolean(questionBank.data.institution_id);

    return (
        <PortalAppShell title="Edit Question Bank">
            <Head title="Edit Question Bank" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Question Bank" title="Edit Question Bank" description={isInstitution ? 'Update the bank course, name, code, and lifecycle status.' : 'Update the bank subject, name, code, and lifecycle status.'} />
                <QuestionBankForm questionBank={questionBank.data} subjects={subjects} courses={courses} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
