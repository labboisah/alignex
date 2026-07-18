import { Head, router, useForm } from '@inertiajs/react';
import { Download, KeyRound, ListChecks, Save, Upload } from 'lucide-react';
import { FormEvent } from 'react';
import { AlertBanner, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type RecruitmentExam = {
    id: string;
    title: string;
    exam_code: string;
    total_marks: number | string;
    pass_mark: number | string;
};

type RecruitmentSettings = {
    cutoff_score: number;
    auto_shortlist: boolean;
    shortlist_limit: number | null;
    negative_marking: boolean;
    negative_mark_value: number;
};

type RankingRow = Record<string, unknown> & {
    rank: number;
    attempt_id: string;
    candidate_id: string;
    candidate_name: string;
    registration_number: string;
    email?: string | null;
    phone?: string | null;
    score: number;
    total_marks: number;
    percentage: number;
    status: string;
    submitted_at?: string | null;
    ip_address?: string | null;
    device_fingerprint?: string | null;
    suspicious_events: number;
};

type AnomalyRow = {
    type: string;
    description: string;
    candidate_name: string;
    registration_number: string;
    ip_address?: string | null;
    device_fingerprint?: string | null;
};

type Anomalies = {
    duplicate_logins: AnomalyRow[];
    shared_devices: AnomalyRow[];
    shared_ips: AnomalyRow[];
};

export default function RecruitmentShow({
    exam,
    settings,
    ranking,
    shortlisted,
    anomalies,
}: {
    exam: RecruitmentExam;
    settings: RecruitmentSettings;
    ranking: RankingRow[];
    shortlisted: RankingRow[];
    anomalies: Anomalies;
}) {
    const anomalyRows = [...anomalies.duplicate_logins, ...anomalies.shared_devices, ...anomalies.shared_ips];

    return (
        <PortalAppShell title={`Recruitment - ${exam.title}`}>
            <Head title={`Recruitment - ${exam.title}`} />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Recruitment"
                    title={exam.title}
                    description={`${exam.exam_code} recruitment settings, shortlist, ranking, and access controls.`}
                    backHref={`/exams/${exam.id}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button asChild type="button" variant="secondary"><a href={`/exams/${exam.id}/recruitment/shortlist.csv`}><Download className="h-4 w-4" />Shortlist</a></Button>
                            <Button asChild type="button" variant="secondary"><a href={`/exams/${exam.id}/recruitment/access-codes.csv`}><Download className="h-4 w-4" />Access Codes</a></Button>
                        </div>
                    }
                />

                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-5">
                        <RankingReport rows={ranking} />
                        <ShortlistPanel exam={exam} rows={shortlisted} />
                        <AnomalyReport rows={anomalyRows} />
                    </div>
                    <div className="space-y-5">
                        <SettingsPanel exam={exam} settings={settings} />
                        <BulkImportPanel exam={exam} />
                        <AccessCodePanel exam={exam} />
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function SettingsPanel({ exam, settings }: { exam: RecruitmentExam; settings: RecruitmentSettings }) {
    const { data, setData, patch, processing, errors } = useForm({
        cutoff_score: String(settings.cutoff_score ?? ''),
        auto_shortlist: settings.auto_shortlist,
        shortlist_limit: settings.shortlist_limit ? String(settings.shortlist_limit) : '',
        negative_marking: settings.negative_marking,
        negative_mark_value: String(settings.negative_mark_value ?? 0),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(`/exams/${exam.id}/recruitment/settings`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Recruitment Settings</h2>
            <div className="mt-4 space-y-4">
                <TextInput label="Cut-off Score" value={data.cutoff_score} error={errors.cutoff_score} type="number" step="0.01" onChange={(value) => setData('cutoff_score', value)} />
                <TextInput label="Shortlist Limit" value={data.shortlist_limit} error={errors.shortlist_limit} type="number" onChange={(value) => setData('shortlist_limit', value)} />
                <Toggle label="Automatic shortlist" checked={data.auto_shortlist} onChange={(checked) => setData('auto_shortlist', checked)} />
                <Toggle label="Negative marking" checked={data.negative_marking} onChange={(checked) => setData('negative_marking', checked)} />
                <TextInput label="Negative mark value" value={data.negative_mark_value} error={errors.negative_mark_value} type="number" step="0.01" onChange={(value) => setData('negative_mark_value', value)} />
                <Button type="submit" disabled={processing} className="w-full"><Save className="h-4 w-4" />Save Settings</Button>
            </div>
        </form>
    );
}

function BulkImportPanel({ exam }: { exam: RecruitmentExam }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; exam_id: string }>({ file: null, exam_id: exam.id });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/candidates/import', { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file') });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Bulk Import</h2>
            <p className="mt-1 text-sm text-slate-600">Uploaded candidates are assigned to this recruitment exam.</p>
            <input type="hidden" value={data.exam_id} onChange={(event) => setData('exam_id', event.target.value)} />
            <div className="mt-4 space-y-3">
                <Button asChild type="button" variant="secondary" className="w-full"><a href="/candidates/template"><Download className="h-4 w-4" />Download Template</a></Button>
                <label className="block text-sm font-semibold text-slateDark">
                    Upload CSV
                    <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept=".csv,text/csv" onChange={(event) => setData('file', event.target.files?.[0] ?? null)} />
                    {errors.file && <span className="mt-1 block text-sm text-danger">{errors.file}</span>}
                </label>
                <Button type="submit" disabled={processing || !data.file} className="w-full"><Upload className="h-4 w-4" />Upload Candidates</Button>
            </div>
        </form>
    );
}

function AccessCodePanel({ exam }: { exam: RecruitmentExam }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Access Codes</h2>
            <p className="mt-1 text-sm text-slate-600">Generate per-candidate access codes after papers have been created.</p>
            <div className="mt-4 space-y-3">
                <Button type="button" className="w-full" onClick={() => router.post(`/exams/${exam.id}/recruitment/access-codes`, {}, { preserveScroll: true })}><KeyRound className="h-4 w-4" />Generate Codes</Button>
                <Button asChild type="button" variant="secondary" className="w-full"><a href={`/exams/${exam.id}/recruitment/access-codes.csv`}><Download className="h-4 w-4" />Export Codes</a></Button>
            </div>
        </div>
    );
}

function RankingReport({ rows }: { rows: RankingRow[] }) {
    return (
        <section>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-slateDark">Ranking Report</h2>
                    <p className="text-sm text-slate-600">Submitted candidates ranked by server-calculated score.</p>
                </div>
            </div>
            <DataTable<RankingRow>
                rows={rows}
                emptyTitle="No submitted recruitment results"
                columns={[
                    { key: 'rank', header: 'Rank', render: (row) => <span className="font-bold text-slateDark">#{row.rank}</span> },
                    { key: 'candidate_name', header: 'Candidate', render: (row) => <span className="font-semibold text-slateDark">{row.candidate_name || 'N/A'}</span> },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'score', header: 'Score', render: (row) => `${row.score}/${row.total_marks}` },
                    { key: 'percentage', header: 'Percentage', render: (row) => `${row.percentage}%` },
                    { key: 'suspicious_events', header: 'Suspicious' },
                    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replaceAll('_', ' ')} tone={row.status === 'shortlisted' ? 'success' : row.status === 'not_shortlisted' ? 'danger' : 'neutral'} /> },
                ]}
            />
        </section>
    );
}

function ShortlistPanel({ exam, rows }: { exam: RecruitmentExam; rows: RankingRow[] }) {
    return (
        <section className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slateDark">Shortlist Management</h2>
                    <p className="text-sm text-slate-600">{rows.length} candidates currently shortlisted.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" onClick={() => router.post(`/exams/${exam.id}/recruitment/shortlist`, {}, { preserveScroll: true })}><ListChecks className="h-4 w-4" />Apply Shortlist</Button>
                    <Button asChild type="button" variant="secondary"><a href={`/exams/${exam.id}/recruitment/shortlist.csv`}><Download className="h-4 w-4" />Export</a></Button>
                </div>
            </div>
            <div className="mt-4 overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-border text-xs uppercase text-slate-500"><tr><th className="py-2">Rank</th><th>Candidate</th><th>Registration Number</th><th>Score</th></tr></thead>
                    <tbody className="divide-y divide-border">
                        {rows.length === 0 && <tr><td colSpan={4} className="py-4 text-slate-500">No shortlisted candidates yet.</td></tr>}
                        {rows.map((row) => <tr key={row.attempt_id}><td className="py-3 font-semibold">#{row.rank}</td><td>{row.candidate_name}</td><td>{row.registration_number}</td><td>{row.score}/{row.total_marks}</td></tr>)}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function AnomalyReport({ rows }: { rows: AnomalyRow[] }) {
    return (
        <section>
            <div className="mb-3">
                <h2 className="text-lg font-semibold text-slateDark">Device/IP Anomaly Report</h2>
                <p className="text-sm text-slate-600">Duplicate logins, shared devices, and shared IP addresses.</p>
            </div>
            {rows.length > 0 && <AlertBanner className="mb-4" tone="warning" title={`${rows.length} anomaly records found`} message="Review these records as evidence, not automatic proof of malpractice." />}
            <DataTable<AnomalyRow & Record<string, unknown>>
                rows={rows}
                emptyTitle="No device or IP anomalies"
                columns={[
                    { key: 'type', header: 'Type', render: (row) => <StatusBadge label={row.type} tone={row.type === 'Duplicate login' ? 'warning' : 'info'} /> },
                    { key: 'candidate_name', header: 'Candidate' },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'description', header: 'Details' },
                    { key: 'ip_address', header: 'IP Address', render: (row) => row.ip_address ?? 'N/A' },
                    { key: 'device_fingerprint', header: 'Device', render: (row) => row.device_fingerprint ?? 'N/A' },
                ]}
            />
        </section>
    );
}

function TextInput({ label, value, error, type = 'text', step, onChange }: { label: string; value: string; error?: string; type?: string; step?: string; onChange: (value: string) => void }) {
    return (
        <label className="block text-sm font-semibold text-slateDark">
            {label}
            <input className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" type={type} step={step} value={value} onChange={(event) => onChange(event.target.value)} />
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm font-semibold text-slateDark">
            {label}
            <input className="h-4 w-4 rounded border-border text-primary focus:ring-primary" type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
        </label>
    );
}
