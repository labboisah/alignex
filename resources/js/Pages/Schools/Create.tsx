import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SchoolForm } from './Form';
import { StatusOption } from './types';

export default function CreateSchool({ statuses }: { statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create School">
            <Head title="Create School" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Schools" title="Create School" description="Add a school to the platform." />
                <SchoolForm statuses={statuses} submitLabel="Create School" />
            </section>
        </PortalAppShell>
    );
}
