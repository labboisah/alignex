import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SubjectForm } from './Form';
import { ClassOption, StatusOption, Subject } from './types';

export default function EditSubject({ subject, statuses, classes = [] }: { subject: { data: Subject }; statuses: StatusOption[]; classes?: ClassOption[] }) {
    return (
        <PortalAppShell title="Edit Subject">
            <Head title="Edit Subject" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Subjects" title="Edit Subject" description="Update subject name, code, description, and status." />
                <SubjectForm subject={subject.data} statuses={statuses} classes={classes} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
