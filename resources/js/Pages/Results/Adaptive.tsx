import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Marks = { original: string; earned: string; penalty: string; recoverable: string; closed: string };
type Item = { step: number; question_id: string; area_key: string; topic_id: string | null; difficulty: string; issued_at: string; committed_at: string | null; correct: boolean | null; earned_marks: string };
type Coverage = { area_key: string; planned: number; issued: number; committed: number; correct: number; untagged_committed: number; topics: { topic_id: string; issued: number; committed: number }[] };
type Level = { number: number; attempt_id: string; status: string; is_practice: boolean; stop_reason: string | null; incoming_marks: string; penalty_percent: number; penalty_marks: string; available_marks: string; earned_marks: string; planned: number; issued: number; committed: number; correct: number; raw_accuracy_percent: number | null; coverage: Coverage[]; path: Item[] };
type Report = {
    progression_id: number; exam_id: string; exam_title: string; candidate_name: string; registration_number: string; owner_key: string;
    interpretation: string; status: string; stop_reason: string | null; candidate_result_released: boolean;
    snapshot: { id: number; version: number; engine_version: string; scoring_version: string; fingerprint: string };
    criteria: { mastery_threshold_percent: number | string; min_evidence_per_area: number };
    marks: Marks; areas: { area_key: string; mastery: string; scored_evidence_count: number; marks: Marks }[]; levels: Level[];
};
export default function Adaptive({ report }: { report: Report }) {
    const [exporting, setExporting] = useState(false);
    const [message, setMessage] = useState('');
    const exportCsv = async () => {
        setExporting(true); setMessage('');
        try {
            const response = await fetch(`/results/adaptive/progressions/${report.progression_id}/export.csv`, { headers: { Accept: 'text/csv' } });
            if (!response.ok || !response.headers.get('content-type')?.includes('text/csv')) throw new Error('Export could not be downloaded. Check your access and try again.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a'); link.href = url; link.download = `adaptive-progression-${report.progression_id}.csv`; link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000); setMessage('Report downloaded.');
        } catch (error) { setMessage(error instanceof Error ? error.message : 'Export failed. Try again.'); }
        finally { setExporting(false); }
    };
    return <PortalAppShell title="Adaptive progression report">
        <Head title="Adaptive progression report" />
        <section className="mx-auto max-w-7xl space-y-6">
            <PageHeader eyebrow={report.exam_title} title={report.candidate_name} description={`Registration: ${report.registration_number} • Progression ${report.progression_id}`} />
            <div className="flex flex-wrap items-center gap-4">
                <Link className="text-primary underline" href={`/results/adaptive/exams/${report.exam_id}`}>All progressions</Link>
                <Button onClick={exportCsv} disabled={exporting}>{exporting ? 'Downloading…' : 'Download diagnostic CSV'}</Button>
                <span role="status" aria-live="polite">{message}</span>
            </div>
            <p className="rounded-md border border-amber-300 bg-amber-50 p-4">{report.interpretation}</p>
            <p>Scored progression: <strong>{report.status}</strong> • Stop reason: {report.stop_reason ?? 'Not stopped'} • Candidate aggregate: <strong>{report.candidate_result_released ? 'Released' : 'Withheld'}</strong></p>
            <div className="rounded-md border bg-white p-4">
                <h2 className="font-semibold">Recovered marks</h2>
                <MarkSummary marks={report.marks} />
                <p className="mt-2 text-sm text-slate-500">Original = earned + penalty + recoverable + closed. Open balances are provisional. Unscored practice adds no credit.</p>
            </div>
            <div className="overflow-x-auto rounded-md border bg-white p-4">
                <h2 className="mb-3 font-semibold">Area evidence and rule-based mastery</h2>
                <table className="w-full text-left text-sm"><thead><tr><th>Area</th><th>Mastery</th><th>Scored evidence</th><th>Earned</th><th>Recoverable</th><th>Closed</th></tr></thead>
                    <tbody>{report.areas.map(area => <tr key={area.area_key} className="border-t"><td className="py-2">{area.area_key}</td><td>{area.mastery.replaceAll('_', ' ')}</td><td>{area.scored_evidence_count}</td><td>{area.marks.earned}</td><td>{area.marks.recoverable}</td><td>{area.marks.closed}</td></tr>)}</tbody>
                </table>
                <p className="mt-3 text-sm text-slate-500">Areas identify frozen paper rows. Mastery requires at least {report.criteria.mastery_threshold_percent}% correct against the planned area quota in the latest scored level and {report.criteria.min_evidence_per_area} committed responses; it is separate from recovered marks. Practice does not update scored mastery.</p>
            </div>
            {report.levels.map(level => <article key={level.attempt_id} className="space-y-3 rounded-md border bg-white p-4">
                <h2 className="text-lg font-semibold">Level {level.number} — {level.is_practice ? 'Unscored practice' : 'Scored diagnostic level'}</h2>
                <p>{level.status} • {level.stop_reason ?? 'Not stopped'}</p>
                <p>Incoming {level.incoming_marks} − penalty {level.penalty_marks} ({level.penalty_percent}%) = available {level.available_marks}. Earned: {level.earned_marks}.</p>
                <p>Planned: {level.planned} • Issued: {level.issued} • Committed: {level.committed} • Correct: {level.correct} • Raw accuracy among committed responses: {level.raw_accuracy_percent === null ? 'No evidence' : `${level.raw_accuracy_percent}%`}.</p>
                <p className="text-sm text-slate-500">Raw accuracy excludes unconfirmed questions. It is not completion, recovered score, or validated ability.</p>
                {level.coverage.map(area => <div key={area.area_key} className="rounded border p-3 text-sm">
                    <strong>{area.area_key}</strong>: {area.committed}/{area.planned} planned responses committed; {area.issued} issued; {area.correct} correct.
                    <p>Untagged committed questions: {area.untagged_committed}.</p>
                    {area.topics.map(topic => <p key={topic.topic_id}>Topic {topic.topic_id}: {topic.issued} issued, {topic.committed} committed.</p>)}
                </div>)}
                <details>
                    <summary className="cursor-pointer font-semibold">Item path — {level.path.length} issued questions</summary>
                    <div className="mt-3 overflow-x-auto"><table className="w-full text-left text-sm">
                        <thead><tr><th>Step</th><th>Question ID</th><th>Area / topic</th><th>Difficulty</th><th>Response</th><th>Earned</th></tr></thead>
                        <tbody>{level.path.map(item => <tr key={item.step} className="border-t"><td className="py-2">{item.step}</td><td>{item.question_id}</td><td>{item.area_key} / {item.topic_id ?? 'Untagged'}</td><td>{item.difficulty}</td><td>{item.correct === null ? 'Unconfirmed' : item.correct ? 'Correct' : 'Incorrect'}</td><td>{item.earned_marks}</td></tr>)}</tbody>
                    </table>{!level.path.length && <p className="py-3 text-slate-500">No question was issued for this level.</p>}</div>
                </details>
            </article>)}
            {!report.levels.length && <p>No levels have been prepared.</p>}
            <footer className="break-all rounded-md border p-4 text-sm">
                Owner: {report.owner_key} • Engine: {report.snapshot.engine_version} • Scoring: {report.snapshot.scoring_version} • Configuration version: {report.snapshot.version} (snapshot {report.snapshot.id})
                <p>Fingerprint: {report.snapshot.fingerprint}</p>
            </footer>
        </section>
    </PortalAppShell>;
}
function MarkSummary({ marks }: { marks: Marks }) {
    return <dl className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-5">{Object.entries(marks).map(([key, value]) => <div key={key}><dt className="capitalize text-slate-500">{key}</dt><dd className="text-xl font-semibold">{value}</dd></div>)}</dl>;
}
