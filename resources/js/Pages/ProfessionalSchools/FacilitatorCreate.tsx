import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { FacilitatorForm } from './FacilitatorForm';

type Course = { id: number | string; name: string; code?: string | null; programme?: { name: string } | null };

type Props = {
    professionalSchool: { id: number | string; name: string };
    courses: Course[];
};

export default function FacilitatorCreate({ professionalSchool, courses }: Props) {
    const indexUrl = `/professional-schools/${professionalSchool.id}/facilitators`;

    return (
        <PortalAppShell title="Register Facilitator">
            <Head title="Register Facilitator" />
            <PageHeader
                eyebrow={professionalSchool.name}
                title="Register Facilitator"
                description="Create a facilitator login and select the courses this facilitator can manage."
                backHref={indexUrl}
            />
            <FacilitatorForm
                mode="create"
                courses={courses}
                submitUrl={indexUrl}
                cancelUrl={indexUrl}
            />
        </PortalAppShell>
    );
}
