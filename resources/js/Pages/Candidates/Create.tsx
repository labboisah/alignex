import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CandidateForm } from './Form';
import { ScopeOption, StatusOption } from './types';

export default function CreateCandidate(props: { organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[]; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Candidate">
            <Head title="Create Candidate" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Candidates" title="Create Candidate" description="Add a candidate record for exam assignment and delivery." backHref="/candidates" />
                <CandidateForm {...props} submitLabel="Save Candidate" />
            </section>
        </PortalAppShell>
    );
}
