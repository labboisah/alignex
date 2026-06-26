import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { OrganizationForm } from './Form';
import { Organization, StatusOption } from './types';

export default function EditOrganization({ organization, statuses }: { organization: { data: Organization }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Organization">
            <Head title="Edit Organization" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Organizations" title="Edit Organization" description="Update organization contact and operational status." />
                <OrganizationForm organization={organization.data} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
