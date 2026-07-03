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
    programmes_count: number;
    candidates_count: number;
    exams_count: number;
};

export default function ProfessionalSchoolsIndex({ professionalSchools, can }: { professionalSchools: School[]; can: { create: boolean } }) {
    return (
        <PortalAppShell title="Professional Schools">
            <Head title="Professional Schools" />
            <PageHeader
                eyebrow="Training"
                title="Professional Schools"
                description="Manage academies, bootcamps, vocational training providers, and certification exam owners."
                actions={<ProtectedAction allowed={can.create}><Button asChild><Link href="/professional-schools/create"><Plus className="h-4 w-4" />New Professional School</Link></Button></ProtectedAction>}
            />
            <DataTable<School>
                rows={professionalSchools}
                emptyTitle="No professional schools found"
                columns={[
                    { key: 'name', header: 'Name', render: (school) => <span className="font-semibold text-slateDark">{school.name}</span> },
                    { key: 'organization_name', header: 'Organization', render: (school) => school.organization_name || 'Standalone' },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'email', header: 'Email' },
                    { key: 'status', header: 'Status', render: (school) => <StatusBadge label={school.status_label} tone={school.status === 'active' ? 'success' : 'neutral'} /> },
                    { key: 'programmes_count', header: 'Programmes' },
                    { key: 'candidates_count', header: 'Candidates' },
                    { key: 'exams_count', header: 'Exams' },
                    { key: 'actions', header: 'Actions', render: (school) => <ActionDropdown items={[{ label: 'View', icon: Eye, onSelect: () => router.visit(`/professional-schools/${school.id}`) }, { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/professional-schools/${school.id}/edit`) }]} /> },
                ]}
            />
        </PortalAppShell>
    );
}
