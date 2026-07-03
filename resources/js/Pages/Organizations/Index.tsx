import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Power } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Organization } from './types';

type Props = {
    organizations: {
        data: Organization[];
    };
    can: {
        create: boolean;
    };
};

export default function OrganizationsIndex({ organizations, can }: Props) {
    return (
        <PortalAppShell title="Organizations">
            <Head title="Organizations" />
            <PageHeader
                eyebrow="Platform"
                title="Organizations"
                description="Manage examination owners that use AlignEx to create, deliver, and review exams."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild type="button">
                            <Link href="/organizations/create">
                                <Plus className="h-4 w-4" />
                                New Organization
                            </Link>
                        </Button>
                    </ProtectedAction>
                }
            />

            <DataTable<Organization>
                rows={organizations.data}
                emptyTitle="No organizations found"
                columns={[
                    { key: 'name', header: 'Name', render: (organization) => <span className="font-semibold text-slateDark">{organization.name}</span> },
                    { key: 'organization_type', header: 'Organization Type', render: (organization) => organization.organization_type_label || 'N/A' },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'email', header: 'Email' },
                    { key: 'phone', header: 'Phone', render: (organization) => organization.phone || 'N/A' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (organization) => (
                            <StatusBadge label={organization.status_label} tone={organization.status === 'active' ? 'success' : 'neutral'} />
                        ),
                    },
                    { key: 'exams_count', header: 'Exams Count', render: (organization) => organization.exams_count ?? 0 },
                    { key: 'candidates_count', header: 'Candidates Count', render: (organization) => organization.candidates_count ?? 0 },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (organization) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, onSelect: () => router.visit(`/organizations/${organization.id}`) },
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/organizations/${organization.id}/edit`) },
                                    {
                                        label: 'Deactivate',
                                        icon: Power,
                                        destructive: true,
                                        disabled: organization.status === 'inactive',
                                        onSelect: () => router.patch(`/organizations/${organization.id}/deactivate`, {}, { preserveScroll: true }),
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
