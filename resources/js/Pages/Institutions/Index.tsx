import { Head, Link } from '@inertiajs/react';
import { Building2, Eye, Plus } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function InstitutionsIndex({ institutions, can }: { institutions: any[]; can: { create: boolean } }) {
    return (
        <PortalAppShell title="Institutions">
            <Head title="Institutions" />
            <PageHeader
                eyebrow="Platform"
                title="Institutions"
                description="Manage higher institutions, faculties, departments, programmes, and courses."
                actions={
                    can.create ? (
                        <Button asChild type="button">
                            <Link href="/institutions/create">
                                <Plus className="h-4 w-4" />
                                New Institution
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <DataTable
                rows={institutions}
                emptyTitle="No institutions found"
                columns={[
                    { key: 'name', header: 'Name', render: (item) => <span className="font-semibold text-slateDark">{item.name}</span> },
                    { key: 'code', header: 'Code' },
                    { key: 'institution_type', header: 'Type' },
                    { key: 'email', header: 'Email' },
                    { key: 'faculties_count', header: 'Faculties' },
                    { key: 'programmes_count', header: 'Programmes' },
                    { key: 'courses_count', header: 'Courses' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (item) => <StatusBadge label={item.status} tone={item.status === 'active' ? 'success' : 'neutral'} />,
                    },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (item) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, onSelect: () => window.location.href = `/institutions/${item.id}` },
                                    { label: 'Manage structure', icon: Building2, onSelect: () => window.location.href = `/institutions/${item.id}/faculties` },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}
