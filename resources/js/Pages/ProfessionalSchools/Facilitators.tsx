import { Head, Link, router } from '@inertiajs/react';
import { DataTable, PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Course = { id: number | string; name: string; code?: string | null };
type Facilitator = {
    id: number | string;
    name: string;
    email: string;
    courses: Course[];
};

type Props = {
    professionalSchool: { id: number | string; name: string };
    facilitators: Facilitator[];
};

export default function Facilitators({ professionalSchool, facilitators }: Props) {
    const path = `/professional-schools/${professionalSchool.id}/facilitators`;

    const destroy = (facilitator: Facilitator) => {
        if (window.confirm(`Delete ${facilitator.name}?`)) {
            router.delete(`${path}/${facilitator.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Facilitators">
            <Head title="Facilitators" />
            <PageHeader
                eyebrow={professionalSchool.name}
                title="Facilitators"
                description="Manage facilitator logins and the professional courses assigned to each facilitator."
                actions={(
                    <Button asChild>
                        <Link href={`${path}/create`}>Register Facilitator</Link>
                    </Button>
                )}
            />

            <DataTable<Facilitator> rows={facilitators} emptyTitle="No facilitators" columns={[
                { key: 'name', header: 'Name' },
                { key: 'email', header: 'Email' },
                {
                    key: 'courses',
                    header: 'Courses',
                    render: (facilitator) => facilitator.courses.length
                        ? facilitator.courses.map((course) => `${course.name} (${course.code ?? 'No code'})`).join(', ')
                        : 'No courses assigned',
                },
                {
                    key: 'actions',
                    header: 'Actions',
                    render: (facilitator) => (
                        <div className="flex gap-2">
                            <Button variant="secondary" className="h-9 px-3" asChild>
                                <Link href={`${path}/${facilitator.id}/edit`}>Edit</Link>
                            </Button>
                            <Button variant="danger" className="h-9 px-3" onClick={() => destroy(facilitator)}>Delete</Button>
                        </div>
                    ),
                },
            ]} />
        </PortalAppShell>
    );
}
