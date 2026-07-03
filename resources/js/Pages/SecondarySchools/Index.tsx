import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type School = Record<string, unknown> & {
    id: number;
    name: string;
    code: string;
    organization_name?: string | null;
    contact_person: string;
    email: string;
    phone?: string | null;
    status: string;
    status_label: string;
    students_count: number;
    classes_count: number;
    exams_count: number;
};

export default function SecondarySchoolsIndex({ secondarySchools, can }: { secondarySchools: School[]; can: { create: boolean } }) {
    return (
        <PortalAppShell title="Secondary Schools">
            <Head title="Secondary Schools" />
            <PageHeader
                eyebrow="Academic"
                title="Secondary Schools"
                description="Manage secondary schools as separate academic exam owners."
                actions={<ProtectedAction allowed={can.create}><Button asChild><Link href="/secondary-schools/create"><Plus className="h-4 w-4" />New Secondary School</Link></Button></ProtectedAction>}
            />
            <DataTable<School>
                rows={secondarySchools}
                emptyTitle="No secondary schools found"
                columns={[
                    { key: 'name', header: 'Name', render: (school) => <span className="font-semibold text-slateDark">{school.name}</span> },
                    { key: 'organization_name', header: 'Organization', render: (school) => school.organization_name || 'Standalone' },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'email', header: 'Email' },
                    { key: 'phone', header: 'Phone', render: (school) => school.phone || 'N/A' },
                    { key: 'status', header: 'Status', render: (school) => <StatusBadge label={school.status_label} tone={school.status === 'active' ? 'success' : 'neutral'} /> },
                    { key: 'students_count', header: 'Students Count' },
                    { key: 'classes_count', header: 'Classes Count' },
                    { key: 'exams_count', header: 'Exams Count' },
                    { key: 'actions', header: 'Actions', render: (school) => <ActionDropdown items={[{ label: 'View', icon: Eye, onSelect: () => router.visit(`/secondary-schools/${school.id}`) }, { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/secondary-schools/${school.id}/edit`) }]} /> },
                ]}
            />
        </PortalAppShell>
    );
}
