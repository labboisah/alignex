import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SubjectForm } from './Form';
import { ScopeOption, StatusOption } from './types';

export default function CreateSubject({ statuses, organizations, schools, centers }: { statuses: StatusOption[]; organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[] }) {
    return (
        <PortalAppShell title="Create Subject">
            <Head title="Create Subject" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Subjects" title="Create Subject" description="Add a subject to the question-bank catalogue." />
                <SubjectForm statuses={statuses} organizations={organizations} schools={schools} centers={centers} submitLabel="Create Subject" />
            </section>
        </PortalAppShell>
    );
}
