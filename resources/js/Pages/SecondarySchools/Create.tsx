import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SecondarySchoolForm } from './Form';

export default function CreateSecondarySchool({ organizations, statuses }: { organizations: []; statuses: [] }) {
    return (
        <PortalAppShell title="Create Secondary School">
            <Head title="Create Secondary School" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Secondary Schools" title="Create Secondary School" description="Add a school that follows academic sessions, terms, classes, and terminal exams." />
                <SecondarySchoolForm organizations={organizations} statuses={statuses} />
            </section>
        </PortalAppShell>
    );
}
