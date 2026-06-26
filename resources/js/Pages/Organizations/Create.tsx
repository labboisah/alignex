import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { OrganizationForm } from './Form';
import { StatusOption } from './types';

export default function CreateOrganization({ statuses }: { statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Organization">
            <Head title="Create Organization" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Organizations" title="Create Organization" description="Add a new examination owner to the AlignEx platform." />
                <OrganizationForm statuses={statuses} submitLabel="Create Organization" />
            </section>
        </PortalAppShell>
    );
}
