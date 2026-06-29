import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, Eye, Pencil, Plus, Trash2, Upload, UserCircle2, UserPlus } from 'lucide-react';
import { FormEvent } from 'react';
import { ActionDropdown, AlertBanner, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Candidate, ExamOption, ImportReport } from './types';

export default function CandidatesIndex({ candidates, exams, can, importReport }: { candidates: { data: Candidate[] }; exams: { data: ExamOption[] }; can: { create: boolean }; importReport?: ImportReport | null }) {
    return (
        <PortalAppShell title="Candidates">
            <Head title="Candidates" />
            <PageHeader
                eyebrow="Exam Operations"
                title="Candidates"
                description="Create, import, and assign candidates to exams."
                actions={
                    <ProtectedAction allowed={can.create}>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild type="button" variant="secondary"><Link href="/dashboard"><ArrowLeft className="h-4 w-4" />Back</Link></Button>
                            <Button asChild type="button" variant="secondary"><Link href="/candidates/assignments"><UserPlus className="h-4 w-4" />Assign to Exam</Link></Button>
                            <Button asChild type="button"><Link href="/candidates/create"><Plus className="h-4 w-4" />New Candidate</Link></Button>
                        </div>
                    </ProtectedAction>
                }
            />

            <BulkTools exams={exams} />
            {importReport && <ImportReportPanel report={importReport} />}

            <DataTable<Candidate>
                rows={candidates.data}
                emptyTitle="No candidates found"
                columns={[
                    { key: 'photo_url', header: 'Photo', render: (candidate) => <CandidatePhoto candidate={candidate} /> },
                    { key: 'full_name', header: 'Full Name', render: (candidate) => <span className="font-semibold text-slateDark">{candidate.full_name}</span> },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'email', header: 'Email', render: (candidate) => candidate.email ?? 'N/A' },
                    { key: 'phone', header: 'Phone', render: (candidate) => candidate.phone ?? 'N/A' },
                    { key: 'organization_name', header: 'Organization', render: (candidate) => candidate.organization_name ?? candidate.school_name ?? candidate.center_name ?? 'N/A' },
                    { key: 'status', header: 'Status', render: (candidate) => <StatusBadge label={candidate.status_label} tone={candidate.status === 'active' ? 'success' : candidate.status === 'suspended' ? 'danger' : 'neutral'} /> },
                    { key: 'assigned_exams_count', header: 'Assigned Exams Count', render: (candidate) => String(candidate.assigned_exams_count ?? 0) },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (candidate) => (
                            <ActionDropdown
                                items={[
                                    { label: 'View', icon: Eye, onSelect: () => router.visit(`/candidates/${candidate.id}`) },
                                    { label: 'Edit', icon: Pencil, onSelect: () => router.visit(`/candidates/${candidate.id}/edit`) },
                                    { label: 'Delete', icon: Trash2, destructive: true, onSelect: () => window.confirm('Delete this candidate?') && router.delete(`/candidates/${candidate.id}`, { preserveScroll: true }) },
                                ]}
                            />
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}

function BulkTools({ exams }: { exams: { data: ExamOption[] } }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null; exam_id: string }>({ file: null, exam_id: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        post('/candidates/import', { forceFormData: true, preserveScroll: true, onSuccess: () => reset('file') });
    };

    return (
        <form onSubmit={submit} className="mb-5 grid gap-3 rounded-md border border-border bg-white p-4 shadow-sm lg:grid-cols-[auto_1fr_1fr_auto] lg:items-end">
            <Button asChild type="button" variant="secondary"><a href="/candidates/template"><Download className="h-4 w-4" />Template</a></Button>
            <label className="text-sm font-semibold text-slateDark">
                Exam
                <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.exam_id} onChange={(event) => setData('exam_id', event.target.value)} required>
                    <option value="">Choose exam</option>
                    {exams.data.map((exam) => <option key={exam.id} value={exam.id}>{exam.title} ({exam.exam_code})</option>)}
                </select>
                {errors.exam_id && <span className="mt-1 block text-sm text-danger">{errors.exam_id}</span>}
            </label>
            <label className="text-sm font-semibold text-slateDark">
                Upload CSV
                <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept=".csv,text/csv" onChange={(event) => setData('file', event.target.files?.[0] ?? null)} />
                {errors.file && <span className="mt-1 block text-sm text-danger">{errors.file}</span>}
            </label>
            <Button type="submit" disabled={processing || !data.file || !data.exam_id}><Upload className="h-4 w-4" />Upload</Button>
        </form>
    );
}

function ImportReportPanel({ report }: { report: ImportReport }) {
    return (
        <div className="mb-5 rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slateDark">CSV Import Results</h2>
                    <p className="text-sm text-slate-600">{report.successful.length} successful, {report.failed.length} failed, {report.duplicates.length} duplicates.</p>
                </div>
                {report.error_report_url && <Button asChild type="button" variant="secondary"><a href={report.error_report_url}><Download className="h-4 w-4" />Error Report</a></Button>}
            </div>
            {(report.failed.length > 0 || report.duplicates.length > 0) && <AlertBanner className="mt-4" tone="warning" title="Some rows need attention" message="Review failed and duplicate rows, then upload a corrected CSV." />}
            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <ReportList title="Successful Rows" rows={report.successful.map((row) => `Row ${row.row}: ${row.registration_number} ${row.name}`)} />
                <ReportList title="Failed Rows" rows={report.failed.map((row) => `Row ${row.row}: ${row.registration_number} - ${row.reason}`)} />
                <ReportList title="Duplicates" rows={report.duplicates.map((row) => `Row ${row.row}: ${row.registration_number} - ${row.reason}`)} />
            </div>
        </div>
    );
}

function ReportList({ title, rows }: { title: string; rows: string[] }) {
    return (
        <div className="rounded-md border border-border p-3">
            <div className="text-sm font-semibold text-slateDark">{title}</div>
            <div className="mt-2 max-h-36 space-y-1 overflow-auto text-sm text-slate-600">
                {rows.length === 0 ? <div>None</div> : rows.map((row) => <div key={row}>{row}</div>)}
            </div>
        </div>
    );
}

function CandidatePhoto({ candidate }: { candidate: Candidate }) {
    if (candidate.photo_url) {
        return <img src={candidate.photo_url} alt="" className="h-10 w-10 rounded-full object-cover" />;
    }

    return <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-500"><UserCircle2 className="h-6 w-6" /></div>;
}
