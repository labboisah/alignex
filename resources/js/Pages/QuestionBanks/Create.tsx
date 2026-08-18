import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { QuestionBankForm } from './Form';
import { CourseOption, StatusOption, SubjectOption } from './types';

export default function CreateQuestionBank({ subjects, courses = [], statuses }: { subjects: { data: SubjectOption[] }; courses?: CourseOption[]; statuses: StatusOption[] }) {
    const isInstitution = courses.length > 0;

    return (
        <PortalAppShell title="Create Question Bank">
            <Head title="Create Question Bank" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Question Bank" title="Create Question Bank" description={isInstitution ? 'Add a question-bank container for an institution course.' : 'Add a question-bank container for a subject.'} />
                <QuestionBankForm subjects={subjects} courses={courses} statuses={statuses} submitLabel="Create Bank" />
            </section>
        </PortalAppShell>
    );
}
