import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { CandidateForm } from './Form';
import { ScopeOption, StatusOption } from './types';

export default function CreateCandidate(props: { organizations: ScopeOption[]; schools: ScopeOption[]; centers: ScopeOption[]; statuses: StatusOption[] }) {
    return (
        <PortalAppShell title="Create Candidate">
            <Head title="Create Candidate" />
            <section className="mx-auto max-w-5xl">
                <PageHeader eyebrow="Candidates" title="Create Candidate" description="Add a candidate record for exam assignment and delivery." actions={<Button asChild type="button" variant="secondary"><Link href="/candidates"><ArrowLeft className="h-4 w-4" />Back</Link></Button>} />
                <CandidateForm {...props} submitLabel="Save Candidate" />
            </section>
        </PortalAppShell>
    );
}
