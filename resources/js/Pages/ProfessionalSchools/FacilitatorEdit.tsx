import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { FacilitatorForm, FacilitatorPayload } from './FacilitatorForm';

type Course = { id: number | string; name: string; code?: string | null; programme?: { name: string } | null };

type Props = {
    professionalSchool: { id: number | string; name: string };
    facilitator: FacilitatorPayload;
    courses: Course[];
};

export default function FacilitatorEdit({ professionalSchool, facilitator, courses }: Props) {
    const indexUrl = `/professional-schools/${professionalSchool.id}/facilitators`;

    return (
        <PortalAppShell title="Edit Facilitator">
            <Head title="Edit Facilitator" />
            <PageHeader
                eyebrow={professionalSchool.name}
                title="Edit Facilitator"
                description="Update facilitator details and adjust assigned course access."
                backHref={indexUrl}
            />
            <FacilitatorForm
                mode="edit"
                courses={courses}
                facilitator={facilitator}
                submitUrl={`${indexUrl}/${facilitator.id}`}
                cancelUrl={indexUrl}
            />
        </PortalAppShell>
    );
}
