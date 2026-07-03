import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ProfessionalSchoolForm } from './Form';

export default function CreateProfessionalSchool(props: Parameters<typeof ProfessionalSchoolForm>[0]) {
    return (
        <PortalAppShell title="New Professional School">
            <Head title="New Professional School" />
            <PageHeader eyebrow="Training" title="New Professional School" description="Create a separate professional training and certification owner." />
            <ProfessionalSchoolForm {...props} />
        </PortalAppShell>
    );
}
