import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus } from 'lucide-react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CbtCenter } from './types';

export default function Index({ centers, can }: { centers: CbtCenter[]; can: { create: boolean } }) {
    return (
        <PortalAppShell title="CBT Centers">
            <Head title="CBT Centers" />
            <PageHeader
                eyebrow="Centers"
                title="CBT Centers"
                description="Manage standalone CBT centers for traditional, adaptive, recruitment, certification, practice, assessment, and general exams."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <Button asChild><Link href="/cbt-centers/create"><Plus className="h-4 w-4" />New Center</Link></Button>
                    </ProtectedAction>
                }
            />
            <DataTable<CbtCenter>
                rows={centers}
                emptyTitle="No CBT centers found"
                columns={[
                    { key: 'name', header: 'Name', render: (center) => <span className="font-semibold text-slateDark">{center.name}</span> },
                    { key: 'location', header: 'Location' },
                    { key: 'capacity', header: 'Capacity' },
                    { key: 'organization_name', header: 'Organization', render: (center) => center.organization_name || 'Standalone' },
                    { key: 'candidates_count', header: 'Candidates', render: (center) => center.candidates_count ?? 0 },
                    { key: 'question_banks_count', header: 'Banks', render: (center) => center.question_banks_count ?? 0 },
                    { key: 'exams_count', header: 'Exams', render: (center) => center.exams_count ?? 0 },
                    { key: 'status', header: 'Status', render: (center) => <StatusBadge label={center.status_label} tone={center.status === 'active' ? 'success' : 'neutral'} /> },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (center) => <ActionDropdown items={[
                            { label: 'View', icon: Eye, onSelect: () => router.visit(`/cbt-centers/${center.id}`) },
                            { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/cbt-centers/${center.id}/edit`) },
                        ]} />,
                    },
                ]}
            />
        </PortalAppShell>
    );
}
