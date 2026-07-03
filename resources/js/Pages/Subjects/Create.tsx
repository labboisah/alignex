import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SubjectForm } from './Form';
import { ClassOption, ScopeOption, StatusOption } from './types';

export default function CreateSubject({ statuses, organizations, schools, centers, classes = [] }: { statuses: StatusOption[]; organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[]; classes?: ClassOption[] }) {
    return (
        <PortalAppShell title="Create Subject">
            <Head title="Create Subject" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Subjects" title="Create Subject" description="Add a subject to the question-bank catalogue." />
                <SubjectForm statuses={statuses} organizations={organizations} schools={schools} centers={centers} classes={classes} submitLabel="Create Subject" />
            </section>
        </PortalAppShell>
    );
}
