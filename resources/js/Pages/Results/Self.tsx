import { Head, Link } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { ResultRow } from './types';

export default function SelfResult() {
    return <Lookup title="Candidate Result" endpoint="/api/candidate/result" fields={['exam_code', 'identifier']} />;
}

export function Lookup({ title, endpoint, fields }: { title: string; endpoint: string; fields: string[] }) {
    const [form, setForm] = useState<Record<string, string>>({});
    const [result, setResult] = useState<ResultRow | null>(null);
    const [error, setError] = useState('');

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        setError('');
        setResult(null);
        const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(form) });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.valid === false) {
            setError(payload.message ?? 'Result was not found or is not available.');
            return;
        }
        setResult(payload.result);
    };

    return (
        <main className="min-h-screen bg-surface px-4 py-10 text-slateDark">
            <Head title={title} />
            <section className="mx-auto max-w-lg rounded-md border border-border bg-white p-6 shadow-sm">
                <Link href="/" className="text-sm font-semibold text-primary">AlignEx</Link>
                <h1 className="mt-4 text-2xl font-bold">{title}</h1>
                <form onSubmit={submit} className="mt-5 space-y-4">
                    {fields.map((field) => <label key={field} className="block text-sm font-semibold">{field.replaceAll('_', ' ')}<input className="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={form[field] ?? ''} onChange={(event) => setForm((next) => ({ ...next, [field]: event.target.value }))} required /></label>)}
                    {error && <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm font-semibold text-danger">{error}</div>}
                    <Button type="submit" className="w-full">Check Result</Button>
                </form>
                {result && <ResultCard result={result} />}
            </section>
        </main>
    );
}

export function ResultCard({ result }: { result: ResultRow }) {
    return (
        <div className="mt-6 rounded-md border border-border bg-surface p-4">
            <div className="font-bold">{result.candidate_name}</div>
            <div className="text-sm text-slate-600">{result.registration_number} | {result.exam_title}</div>
            <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
                <Info label="Score" value={`${result.score}/${result.total_marks}`} />
                <Info label="Percentage" value={`${result.percentage}%`} />
                <Info label="Grade" value={result.grade} />
                <Info label="Status" value={result.status} />
                <Info label="Duration" value={result.duration_used} />
                <Info label="Hash" value={result.result_hash} />
            </div>
        </div>
    );
}

function Info({ label, value }: { label: string; value: string | number }) {
    return <div><div className="text-xs font-semibold uppercase text-slate-500">{label}</div><div className="break-all font-bold text-slateDark">{value}</div></div>;
}
