import { Head, Link, router } from '@inertiajs/react';
import { DataTable, PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Course = { id: number | string; name: string; code?: string | null };
type Lecturer = {
    id: number | string;
    name: string;
    email: string;
    courses: Course[];
};

type Props = {
    institution: { id: number | string; name: string };
    department: { id: number | string; name: string; code?: string | null };
    lecturers: Lecturer[];
};

export default function Lecturers({ institution, department, lecturers }: Props) {
    const path = `/institutions/${institution.id}/departments/${department.id}/lecturers`;

    const destroy = (lecturer: Lecturer) => {
        if (window.confirm(`Delete ${lecturer.name}?`)) {
            router.delete(`${path}/${lecturer.id}`, { preserveScroll: true });
        }
    };

    return (
        <PortalAppShell title="Lecturers">
            <Head title="Lecturers" />
            <PageHeader
                eyebrow={`${institution.name} / ${department.name}`}
                title="Lecturers"
                description="Manage lecturer logins and the department courses assigned to each lecturer."
                actions={(
                    <Button asChild>
                        <Link href={`${path}/create`}>Register Lecturer</Link>
                    </Button>
                )}
            />

            <DataTable<Lecturer> rows={lecturers} emptyTitle="No lecturers" columns={[
                { key: 'name', header: 'Name' },
                { key: 'email', header: 'Email' },
                {
                    key: 'courses',
                    header: 'Courses',
                    render: (lecturer) => lecturer.courses.length
                        ? lecturer.courses.map((course) => `${course.name} (${course.code ?? 'No code'})`).join(', ')
                        : 'No courses assigned',
                },
                {
                    key: 'actions',
                    header: 'Actions',
                    render: (lecturer) => (
                        <div className="flex gap-2">
                            <Button variant="secondary" className="h-9 px-3" asChild>
                                <Link href={`${path}/${lecturer.id}/edit`}>Edit</Link>
                            </Button>
                            <Button variant="danger" className="h-9 px-3" onClick={() => destroy(lecturer)}>Delete</Button>
                        </div>
                    ),
                },
            ]} />
        </PortalAppShell>
    );
}
