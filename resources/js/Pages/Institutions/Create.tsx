import { Head } from '@inertiajs/react';
import { PortalAppShell, PageHeader } from '@/Components/Platform';
import InstitutionForm from './Form';
export default function Create() { return <PortalAppShell title="New Institution"><Head title="New Institution" /><PageHeader title="New Institution" backHref="/institutions" /><InstitutionForm /></PortalAppShell>; }
