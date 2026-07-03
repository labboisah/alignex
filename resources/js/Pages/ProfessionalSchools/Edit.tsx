import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ProfessionalSchoolForm } from './Form';

export default function EditProfessionalSchool(props: Parameters<typeof ProfessionalSchoolForm>[0]) {
    return (
        <PortalAppShell title="Edit Professional School">
            <Head title="Edit Professional School" />
            <PageHeader eyebrow="Training" title="Edit Professional School" description="Update professional school profile and contact details." />
            <ProfessionalSchoolForm {...props} />
        </PortalAppShell>
    );
}
