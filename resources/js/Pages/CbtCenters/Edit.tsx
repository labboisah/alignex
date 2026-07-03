import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CbtCenterForm } from './Form';
import { CbtCenter, OptionRow } from './types';

export default function Edit({ center, organizations, statuses }: { center: CbtCenter; organizations: OptionRow[]; statuses: { value: string; label: string }[] }) {
    return (
        <PortalAppShell title={`Edit ${center.name}`}>
            <Head title={`Edit ${center.name}`} />
            <PageHeader eyebrow="CBT Centers" title={`Edit ${center.name}`} description="Update center profile, contact, location, and operational status." />
            <CbtCenterForm center={center} organizations={organizations} statuses={statuses} submitLabel="Save Changes" />
        </PortalAppShell>
    );
}
