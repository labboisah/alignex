import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Power } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Center } from './types';

type Props = {
    centers: {
        data: Center[];
    };
    can: {
        create: boolean;
    };
};

export default function CentersIndex({ centers, can }: Props) {
    return (
        <PortalAppShell title="Centers">
            <Head title="Centers" />
            <PageHeader
                eyebrow="Delivery"
                title="Centers"
                description="Manage CBT centers, facility capacity, contacts, and operating status."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/centers/create">
                                <Plus className="h-4 w-4" />
                                New Center
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <DataTable<Center>
                rows={centers.data}
                emptyTitle="No centers found"
                columns={[
                    { key: 'name', header: 'Name', render: (center) => <span className="font-semibold text-slateDark">{center.name}</span> },
                    { key: 'code', header: 'Code' },
                    { key: 'location', header: 'Location' },
                    { key: 'capacity', header: 'Capacity', render: (center) => String(center.capacity) },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'phone', header: 'Phone', render: (center) => center.phone || 'N/A' },
                    { key: 'email', header: 'Email' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (center) => <StatusBadge label={center.status_label} tone={center.status === 'active' ? 'success' : 'neutral'} />,
                    },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (center) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, onSelect: () => router.visit(`/centers/${center.id}`) },
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/centers/${center.id}/edit`) },
                                    {
                                        label: 'Deactivate',
                                        icon: Power,
                                        destructive: true,
                                        disabled: center.status === 'inactive',
                                        onSelect: () => router.patch(`/centers/${center.id}/deactivate`, {}, { preserveScroll: true }),
                                    },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}
