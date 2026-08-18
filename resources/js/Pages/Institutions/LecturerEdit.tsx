import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { LecturerForm, LecturerPayload } from './LecturerForm';

type Course = { id: number | string; name: string; code?: string | null; programme?: { name: string } | null };

type Props = {
    institution: { id: number | string; name: string };
    department: { id: number | string; name: string; code?: string | null };
    lecturer: LecturerPayload;
    courses: Course[];
};

export default function LecturerEdit({ institution, department, lecturer, courses }: Props) {
    const indexUrl = `/institutions/${institution.id}/departments/${department.id}/lecturers`;

    return (
        <PortalAppShell title="Edit Lecturer">
            <Head title="Edit Lecturer" />
            <PageHeader
                eyebrow={`${institution.name} / ${department.name}`}
                title="Edit Lecturer"
                description="Update lecturer details and adjust assigned course access."
                backHref={indexUrl}
            />
            <LecturerForm
                mode="edit"
                courses={courses}
                lecturer={lecturer}
                submitUrl={`${indexUrl}/${lecturer.id}`}
                cancelUrl={indexUrl}
            />
        </PortalAppShell>
    );
}
