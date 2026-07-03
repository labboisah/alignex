import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CbtCenterForm } from './Form';
import { OptionRow } from './types';

export default function Create({ organizations, statuses }: { organizations: OptionRow[]; statuses: { value: string; label: string }[] }) {
    return (
        <PortalAppShell title="New CBT Center">
            <Head title="New CBT Center" />
            <PageHeader eyebrow="CBT Centers" title="New CBT Center" description="Register a standalone CBT center for general examination delivery." />
            <CbtCenterForm organizations={organizations} statuses={statuses} submitLabel="Create Center" />
        </PortalAppShell>
    );
}
