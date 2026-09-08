import { Head, Link } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';

type Row = { id: number; first_name: string; last_name: string; candidate_number: string; status: string; stop_reason: string | null };
export default function AdaptiveIndex({ exam, progressions, interpretation, rollout }: {
    exam: { id: string; title: string };
    progressions: { data: Row[]; prev_page_url: string | null; next_page_url: string | null; total: number };
    interpretation: string;
    rollout: { can_publish: boolean };
}) {
    return <PortalAppShell title="Adaptive diagnostic reports">
        <Head title="Adaptive diagnostic reports" />
        <section className="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow="Results" title={exam.title} description="Adaptive diagnostic reports — one record per candidate, with all recovery levels retained." />
            <p className="rounded-md border border-amber-300 bg-amber-50 p-4">{interpretation}</p>
            <p>New pilot starts: <strong>{rollout.can_publish ? 'Enabled for this approved exam' : 'Disabled'}</strong>. {progressions.total} candidate progressions.</p>
            <div className="overflow-x-auto rounded-md border bg-white p-4">
                <table className="w-full text-left text-sm">
                    <thead><tr><th className="p-2">Candidate</th><th>Registration</th><th>Status</th><th>Stop reason</th><th>Report</th></tr></thead>
                    <tbody>{progressions.data.map(row => <tr key={row.id} className="border-t">
                        <td className="p-2">{row.first_name} {row.last_name}</td><td>{row.candidate_number}</td><td>{row.status}</td><td>{row.stop_reason ?? 'In progress'}</td>
                        <td><Link className="font-semibold text-primary underline" href={`/results/adaptive/progressions/${row.id}`}>View progression</Link></td>
                    </tr>)}</tbody>
                </table>
                {!progressions.data.length && <p className="py-6 text-slate-500">No adaptive candidate progressions have been prepared.</p>}
            </div>
            <nav aria-label="Report pages" className="flex gap-6">
                {progressions.prev_page_url && <Link href={progressions.prev_page_url}>Previous page</Link>}
                {progressions.next_page_url && <Link href={progressions.next_page_url}>Next page</Link>}
            </nav>
        </section>
    </PortalAppShell>;
}
