import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Power } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { School } from './types';

type Props = {
    schools: {
        data: School[];
    };
    can: {
        create: boolean;
    };
};

export default function SchoolsIndex({ schools, can }: Props) {
    return (
        <PortalAppShell title="Schools">
            <Head title="Schools" />
            <PageHeader
                eyebrow="Schools"
                title="Schools"
                description="Manage school profiles, capacity, contacts, and operating status."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/schools/create">
                                <Plus className="h-4 w-4" />
                                New School
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <DataTable<School>
                rows={schools.data}
                emptyTitle="No schools found"
                columns={[
                    { key: 'name', header: 'Name', render: (school) => <span className="font-semibold text-slateDark">{school.name}</span> },
                    { key: 'location', header: 'Location' },
                    { key: 'capacity', header: 'Capacity', render: (school) => String(school.capacity) },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'phone', header: 'Phone', render: (school) => school.phone || 'N/A' },
                    { key: 'email', header: 'Email' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (school) => <StatusBadge label={school.status_label} tone={school.status === 'active' ? 'success' : 'neutral'} />,
                    },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (school) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, onSelect: () => router.visit(`/schools/${school.id}`) },
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/schools/${school.id}/edit`) },
                                    {
                                        label: 'Deactivate',
                                        icon: Power,
                                        destructive: true,
                                        disabled: school.status === 'inactive',
                                        onSelect: () => router.patch(`/schools/${school.id}/deactivate`, {}, { preserveScroll: true }),
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
