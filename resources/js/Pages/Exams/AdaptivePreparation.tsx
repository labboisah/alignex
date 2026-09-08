import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Area = { area_key: string; question_count: number; available: number; required: number; difficulty: Record<string, number> };
type Props = {
    exam: { id: string; title: string };
    readiness: { ready: boolean; warnings: string[]; areas: Area[]; item_count: number };
    snapshots: { id: number; version: number; ready: boolean; created_at: string }[];
};
export default function AdaptivePreparation({ exam, readiness, snapshots }: Props) {
    const { post, processing, errors } = useForm({});
    return <PortalAppShell title="Adaptive preparation">
        <Head title="Adaptive preparation" />
        <section className="mx-auto max-w-6xl space-y-5">
            <PageHeader title={exam.title} description="Check coverage and freeze an immutable configuration and question-pool version. This does not publish or start the exam." actions={<Button asChild variant="secondary"><Link href={'/exams/' + exam.id}>Back to exam</Link></Button>} />
            <div role="status" className="rounded-md border border-border bg-white p-4">
                <p className="font-semibold">{readiness.ready ? 'Pool checks passed' : 'Pool needs attention'}</p>
                <p className="text-sm text-slate-600">{readiness.item_count} eligible questions. Live adaptive delivery remains disabled.</p>
                {readiness.warnings.length > 0 && <ul className="mt-3 list-disc pl-5 text-sm text-amber-800">{readiness.warnings.map((warning, index) => <li key={index}>{warning}</li>)}</ul>}
            </div>
            {Object.values(errors).length > 0 && <div role="alert" className="text-sm text-danger">{Object.values(errors).join(' ')}</div>}
            <div className="overflow-x-auto rounded-md border border-border bg-white p-4">
                {readiness.areas.length === 0 ? <p>No paper rows are configured. Edit the exam to add a blueprint.</p> :
                    <table className="w-full text-left text-sm"><thead><tr><th>Area</th><th>Quota</th><th>Required fresh pool</th><th>Available</th><th>Easy / Medium / Hard</th></tr></thead>
                        <tbody>{readiness.areas.map(area => <tr key={area.area_key}><td className="py-2">{area.area_key}</td><td>{area.question_count}</td><td>{area.required}</td><td>{area.available}</td><td>{area.difficulty.easy} / {area.difficulty.medium} / {area.difficulty.hard}</td></tr>)}</tbody>
                    </table>}
            </div>
            <Button disabled={processing} onClick={() => post('/exams/' + exam.id + '/adaptive/prepare', { preserveScroll: true })}>{processing ? 'Saving snapshot...' : 'Save preparation snapshot'}</Button>
            <div className="rounded-md border border-border bg-white p-4">
                <h2 className="font-semibold">Snapshot history</h2>
                {snapshots.length === 0 ? <p className="mt-2 text-sm text-slate-600">No snapshots yet. Save one to preserve this configuration and its pool.</p> :
                    <ul className="mt-2 space-y-2 text-sm">{snapshots.map(snapshot => <li key={snapshot.id}>Version {snapshot.version}: {snapshot.ready ? 'pool checks passed' : 'needs attention'} — {snapshot.created_at}</li>)}</ul>}
            </div>
        </section>
    </PortalAppShell>;
}
