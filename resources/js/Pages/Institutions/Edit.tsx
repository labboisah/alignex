import { Head } from '@inertiajs/react';
import { PortalAppShell, PageHeader } from '@/Components/Platform';
import InstitutionForm from './Form';
export default function Edit({institution}: {institution: Record<string,any>}) { return <PortalAppShell title="Edit Institution"><Head title="Edit Institution" /><PageHeader title="Edit Institution" backHref={'/institutions/'+institution.id} /><InstitutionForm institution={institution} /></PortalAppShell>; }
