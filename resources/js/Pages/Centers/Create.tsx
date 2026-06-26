import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CenterForm } from './Form';
import { StatusOption } from './types';

export default function CreateCenter({ statuses }: { statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Center">
            <Head title="Create Center" />
            <section className="mx-auto max-w-4xl">
                <PageHeader eyebrow="Centers" title="Create Center" description="Add a CBT delivery facility to the platform." />
                <CenterForm statuses={statuses} submitLabel="Create Center" />
            </section>
        </PortalAppShell>
    );
}
