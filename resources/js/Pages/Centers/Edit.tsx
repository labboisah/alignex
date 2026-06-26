import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CenterForm } from './Form';
import { Center, StatusOption } from './types';

export default function EditCenter({ center, statuses }: { center: { data: Center }; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Center">
            <Head title="Edit Center" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Centers" title="Edit Center" description="Update CBT center facility details, capacity, and status." />
                <CenterForm center={center.data} statuses={statuses} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
