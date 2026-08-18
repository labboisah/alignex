import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { LecturerForm } from './LecturerForm';

type Course = { id: number | string; name: string; code?: string | null; programme?: { name: string } | null };

type Props = {
    institution: { id: number | string; name: string };
    department: { id: number | string; name: string; code?: string | null };
    courses: Course[];
};

export default function LecturerCreate({ institution, department, courses }: Props) {
    const indexUrl = `/institutions/${institution.id}/departments/${department.id}/lecturers`;

    return (
        <PortalAppShell title="Register Lecturer">
            <Head title="Register Lecturer" />
            <PageHeader
                eyebrow={`${institution.name} / ${department.name}`}
                title="Register Lecturer"
                description="Create a lecturer login and select the courses this lecturer can manage."
                backHref={indexUrl}
            />
            <LecturerForm
                mode="create"
                courses={courses}
                submitUrl={indexUrl}
                cancelUrl={indexUrl}
            />
        </PortalAppShell>
    );
}
