import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { SchoolForm } from './Form';
import { School, StatusOption } from './types';

export default function EditSchool({ school, statuses }: { school: { data: School }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit School">
            <Head title="Edit School" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Schools" title="Edit School" description="Update school details, capacity, and status." />
                <SchoolForm school={school.data} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
