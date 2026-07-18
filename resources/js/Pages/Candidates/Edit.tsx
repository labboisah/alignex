import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { CandidateForm } from './Form';
import { Candidate, ScopeOption, StatusOption } from './types';

export default function EditCandidate({ candidate, ...props }: { candidate: { data: Candidate }; organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[]; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Candidate">
            <Head title="Edit Candidate" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Candidates" title="Edit Candidate" description="Update candidate identity and contact information." backHref={`/candidates/${candidate.data.id}`} />
                <CandidateForm candidate={candidate.data} {...props} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
