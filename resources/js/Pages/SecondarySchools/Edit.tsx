import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SecondarySchoolForm } from './Form';

export default function EditSecondarySchool({ secondarySchool, organizations, statuses }: { secondarySchool: Record<string, unknown>; organizations: []; statuses: [] }) {
    return (
        <PortalAppShell title="Edit Secondary School">
            <Head title="Edit Secondary School" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Secondary Schools" title="Edit Secondary School" description="Update school profile and contact information." />
                <SecondarySchoolForm school={secondarySchool as never} organizations={organizations} statuses={statuses} />
            </section>
        </PortalAppShell>
    );
}
