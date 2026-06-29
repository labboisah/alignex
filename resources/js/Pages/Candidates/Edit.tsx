import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CandidateForm } from './Form';
import { Candidate, ScopeOption, StatusOption } from './types';

export default function EditCandidate({ candidate, ...props }: { candidate: { data: Candidate }; organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[]; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Edit Candidate">
            <Head title="Edit Candidate" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Candidates" title="Edit Candidate" description="Update candidate identity and contact information." actions={<Button asChild type="button" variant="secondary"><Link href={`/candidates/${candidate.data.id}`}><ArrowLeft className="h-4 w-4" />Back</Link></Button>} />
                <CandidateForm candidate={candidate.data} {...props} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
