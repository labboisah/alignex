import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Calibration = { id: number; snapshot_id: number; version: number; status: string; specialist: string; fingerprint: string; source_reference: string; criteria_reference: string; validation_notes: string };
type Run = { id: number; level_id: number; status: string; error_code: string | null; replay_of: number | null; duration_ms: number; result: { theta: number; posterior_sd: number; interval_95: number[]; action: string; selected_item_id: string | null; stop_reason: string | null; experimental_classification: string; engine_version: string; coverage_satisfied: boolean; evidence_count: number } | null };
export default function AdaptiveResearch({ exam, can_manage, shadow_enabled, snapshots, calibrations, levels, runs }: {
    exam: { id: string; title: string }; can_manage: boolean; shadow_enabled: boolean;
    snapshots: { id: number; version: number; ready: boolean }[]; calibrations: Calibration[];
    levels: { id: number; number: number; progression_id: number; status: string }[]; runs: Run[];
}) {
    const base = '/exams/' + exam.id + '/adaptive/research';
    const form = useForm({ payload: '' });
    const evaluation = useForm({ calibration_id: '', level_id: '' });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    function action(url: string, data: Record<string, string>) {
        setBusy(true); setError('');
        router.post(url, data, { preserveScroll: true, onError: errors => setError(Object.values(errors).join(' ')), onFinish: () => setBusy(false) });
    }
    return <PortalAppShell title="Adaptive engine research">
        <Head title="Adaptive engine research" />
        <section className="mx-auto max-w-6xl space-y-6">
            <PageHeader title={exam.title} description="Calibration versions and experimental shadow evaluations." />
            <Link className="text-primary underline" href={'/results/adaptive/exams/' + exam.id}>Diagnostic results</Link>
            <p className="rounded border border-amber-300 bg-amber-50 p-4">Shadow estimates are experimental. They never select a candidate question, change marks, release results, or approve recruitment, certification or academic grades. Specialist validation and owner acceptance remain required.</p>
            <p>Engine connection: {shadow_enabled ? 'Shadow requests enabled' : 'Disabled'}. Latest 50 versions/runs and 100 levels are shown.</p>
            {error && <p role="alert" className="text-danger">{error}</p>}
            {can_manage && <div className="space-y-3 rounded border bg-white p-4">
                <h2 className="font-semibold">Import calibration data</h2>
                <p className="text-sm">Download a template for the correct frozen snapshot. Fill in the calibrated parameters, source, specialist, sample size and validation criteria. Blank parameters are deliberately invalid.</p>
                {snapshots.filter(s => s.ready).map(s => <a key={s.id} className="mr-4 inline-block text-primary underline" href={base + '/template/' + s.id}>Snapshot {s.id} (version {s.version}) template</a>)}
                {!snapshots.some(s => s.ready) && <p>No ready snapshots. Prepare the question pool first.</p>}
                <label className="block font-medium" htmlFor="calibration-json">Calibration JSON</label>
                <textarea id="calibration-json" className="h-48 w-full rounded border font-mono text-sm" value={form.data.payload} onChange={e => form.setData('payload', e.target.value)} />
                {Object.values(form.errors).length > 0 && <p role="alert" className="text-danger">{Object.values(form.errors).join(' ')}</p>}
                <Button disabled={form.processing || !form.data.payload} onClick={() => form.post(base + '/calibrations', { preserveScroll: true, onSuccess: () => form.reset() })}>{form.processing ? 'Importing...' : 'Import draft'}</Button>
            </div>}
            <div className="space-y-3 rounded border bg-white p-4">
                <h2 className="font-semibold">Calibration history</h2>
                {!calibrations.length && <p>No calibration data has been imported.</p>}
                {calibrations.map(c => <article key={c.id} className="space-y-2 border-t pt-3">
                    <h3 className="font-medium">Calibration {c.id}, snapshot {c.snapshot_id}, version {c.version}: {c.status}</h3>
                    <p className="text-sm">Specialist: {c.specialist}. Source: {c.source_reference}. Criteria: {c.criteria_reference}.</p>
                    <p className="text-sm">{c.validation_notes}</p>
                    <p className="break-all text-xs text-slate-500">{c.fingerprint}</p>
                    {can_manage && <div className="flex gap-3">
                        {c.status === 'draft' && <Button disabled={busy} onClick={() => action(base + '/calibrations/' + c.id, { action: 'review' })}>Review for shadow use</Button>}
                        {c.status !== 'revoked' && <Button variant="secondary" disabled={busy} onClick={() => { if (confirm('Revoke this calibration? Historical runs will be retained.')) action(base + '/calibrations/' + c.id, { action: 'revoke' }); }}>Revoke</Button>}
                    </div>}
                </article>)}
                <p className="text-sm text-slate-500">Review requires a different authorized user from the importer. Reviewed means available for experiments, not psychometrically validated.</p>
            </div>
            {can_manage && <div className="space-y-3 rounded border bg-white p-4">
                <h2 className="font-semibold">Evaluate a recorded level</h2>
                <label className="block">Reviewed calibration
                    <select className="ml-3 rounded border" value={evaluation.data.calibration_id} onChange={e => evaluation.setData('calibration_id', e.target.value)}>
                        <option value="">Select calibration</option>{calibrations.filter(c => c.status === 'reviewed').map(c => <option key={c.id} value={c.id}>{c.id}: snapshot {c.snapshot_id}, version {c.version}</option>)}
                    </select>
                </label>
                <label className="block">Level
                    <select className="ml-3 rounded border" value={evaluation.data.level_id} onChange={e => evaluation.setData('level_id', e.target.value)}>
                        <option value="">Select level</option>{levels.map(l => <option key={l.id} value={l.id}>Progression {l.progression_id}, level {l.number} ({l.status})</option>)}
                    </select>
                </label>
                {Object.values(evaluation.errors).length > 0 && <p role="alert" className="text-danger">{Object.values(evaluation.errors).join(' ')}</p>}
                <Button disabled={!shadow_enabled || evaluation.processing || !evaluation.data.level_id || !evaluation.data.calibration_id} onClick={() => evaluation.post(base + '/evaluate', { preserveScroll: true })}>{evaluation.processing ? 'Evaluating...' : 'Run shadow evaluation'}</Button>
            </div>}
            <div className="space-y-3 rounded border bg-white p-4">
                <h2 className="font-semibold">Evaluation and replay history</h2>
                {!runs.length && <p>No shadow evaluations have been recorded.</p>}
                {runs.map(run => <article key={run.id} className="space-y-2 border-t pt-3">
                    <h3 className="font-medium">Run {run.id}: {run.status} - level record {run.level_id} ({run.duration_ms} ms){run.replay_of ? ', replay of ' + run.replay_of : ''}</h3>
                    {run.error_code && <p className="text-danger">{run.error_code}. Candidate delivery and scoring are unchanged.</p>}
                    {run.result && <>
                        <p>Experimental ability: {run.result.theta}; posterior SD: {run.result.posterior_sd}; 95% posterior interval: [{run.result.interval_95.join(', ')}].</p>
                        <p>Evidence: {run.result.evidence_count}; coverage: {run.result.coverage_satisfied ? 'met' : 'not met'}; proposal: {run.result.action} {run.result.selected_item_id ?? run.result.stop_reason}.</p>
                        <p>Experimental classification: {run.result.experimental_classification}; engine: {run.result.engine_version}.</p>
                        {can_manage && <Button variant="secondary" disabled={busy || !shadow_enabled} onClick={() => action(base + '/runs/' + run.id + '/replay', {})}>Replay run {run.id}</Button>}
                    </>}
                </article>)}
            </div>
        </section>
    </PortalAppShell>;
}
