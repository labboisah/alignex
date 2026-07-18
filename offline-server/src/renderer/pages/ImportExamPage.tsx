import { CheckCircle2, Download, FileJson, Info, Loader2, ShieldCheck, UploadCloud, XCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '../components/ui/alert';
import { Badge } from '../components/ui/badge';
import { Button } from '../components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { sampleExamPackage } from '../lib/sampleExamPackage';
import { cn } from '@/lib/utils';

type ImportSummary = {
    exam_id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    candidate_count: number;
    question_count: number;
    duration_minutes: number;
    status: string;
};

type ToastState = {
    tone: 'success' | 'danger';
    message: string;
};

type ImportStage = 'idle' | 'reading' | 'validating' | 'importing' | 'success' | 'failed';

const apiBaseUrl = 'http://127.0.0.1:4080';
const sampleJson = JSON.stringify(sampleExamPackage, null, 2);

export function ImportExamPage({ onViewImportedExams }: { onViewImportedExams: () => void }) {
    const inputRef = useRef<HTMLInputElement | null>(null);
    const [selectedFileName, setSelectedFileName] = useState<string | null>(null);
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [dragging, setDragging] = useState(false);
    const [stage, setStage] = useState<ImportStage>('idle');
    const [errors, setErrors] = useState<string[]>([]);
    const [summary, setSummary] = useState<ImportSummary | null>(null);
    const [toast, setToast] = useState<ToastState | null>(null);
    const [examCode, setExamCode] = useState('');

    const importing = stage === 'reading' || stage === 'validating' || stage === 'importing';

    function showToast(nextToast: ToastState) {
        setToast(nextToast);
        window.setTimeout(() => setToast(null), 4200);
    }

    function downloadSamplePackage() {
        const blob = new Blob([sampleJson], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'alignex-sample-exam-package.json';
        link.click();
        URL.revokeObjectURL(url);
    }

    function acceptFile(file: File | undefined) {
        if (!file) {
            return;
        }

        setSelectedFile(file);
        setSelectedFileName(file.name);
        setErrors([]);
        setSummary(null);
        setStage('idle');
    }

    async function importSelectedPackage() {
        if (!selectedFile || importing) {
            return;
        }

        try {
            setErrors([]);
            setSummary(null);
            setStage('reading');
            const text = await selectedFile.text();

            setStage('validating');
            const parsed = JSON.parse(text) as unknown;

            setStage('importing');
            const response = await fetch(`${apiBaseUrl}/api/exams/import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(parsed),
            });

            const data = await response.json() as { summary?: ImportSummary; message?: string; errors?: string[] };

            if (!response.ok) {
                throw new ImportError(data.errors ?? [data.message ?? `Import failed with status ${response.status}.`]);
            }

            setSummary(data.summary ?? null);
            setStage('success');
            showToast({ tone: 'success', message: 'Exam package imported successfully.' });
        } catch (caught) {
            const nextErrors = caught instanceof ImportError
                ? caught.errors
                : caught instanceof SyntaxError
                    ? ['Selected file is not valid JSON.']
                    : [caught instanceof Error ? caught.message : 'Exam package import failed.'];

            setErrors(nextErrors);
            setStage('failed');
            showToast({ tone: 'danger', message: 'Exam package import failed.' });
        }
    }

    async function importPackageByCode() {
        if (!examCode.trim() || importing) {
            return;
        }

        try {
            setErrors([]);
            setSummary(null);
            setStage('validating');

            setStage('importing');
            const response = await fetch(`${apiBaseUrl}/api/exams/import-code`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    exam_code: examCode.trim(),
                }),
            });

            const data = await response.json() as { summary?: ImportSummary; message?: string; errors?: string[] };

            if (!response.ok) {
                throw new ImportError(data.errors ?? [data.message ?? `Import failed with status ${response.status}.`]);
            }

            setSummary(data.summary ?? null);
            setStage('success');
            showToast({ tone: 'success', message: 'Exam imported successfully.' });
        } catch (caught) {
            const nextErrors = caught instanceof ImportError
                ? caught.errors
                : [caught instanceof Error ? caught.message : 'Exam import failed.'];

            setErrors(nextErrors);
            setStage('failed');
            showToast({ tone: 'danger', message: 'Exam import failed.' });
        }
    }

    return (
        <div className="space-y-6">
            {toast && <Toast tone={toast.tone} message={toast.message} />}

            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slateDark">Import Exam</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Fetch the online-created exam package for offline delivery on this center server.
                    </p>
                </div>

                <Button onClick={downloadSamplePackage}>
                    <Download className="h-4 w-4" />
                    Download Sample Package
                </Button>
            </div>

            <Alert>
                <Info className="absolute left-4 top-4 h-4 w-4 text-info" />
                <div className="pl-6">
                    <AlertTitle>Import is local to this center server</AlertTitle>
                    <AlertDescription>
                        The secure browser client will later connect to this offline server to write the fetched exam package.
                    </AlertDescription>
                </div>
            </Alert>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <CardTitle>Package Format</CardTitle>
                                <CardDescription>Supported MVP file type and top-level sections.</CardDescription>
                            </div>
                            <Badge variant="secondary">.json</Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            {['manifest', 'subjects', 'questions', 'options', 'candidates'].map((item) => (
                                <div key={item} className="rounded-md border border-border bg-lightBackground px-3 py-2 text-sm font-medium text-slateDark">
                                    {item}
                                </div>
                            ))}
                        </div>

                        <div className="rounded-md border border-border p-4">
                            <div className="flex items-center gap-2 text-sm font-semibold text-slateDark">
                                <ShieldCheck className="h-4 w-4 text-primary" />
                                Security rule
                            </div>
                            <p className="mt-2 text-sm leading-6 text-slate-500">
                                MVP packages must not contain correct answers, answer keys, correctness flags, scoring rubrics, or plain candidate access codes.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fetch Online Exam</CardTitle>
                        <CardDescription>Enter the normal online exam code configured on the portal.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 rounded-md border border-border bg-lightBackground p-4">
                            <div>
                                <label className="text-sm font-semibold text-slateDark" htmlFor="exam-code">
                                    Online exam code
                                </label>
                                <div className="relative mt-2">
                                    <FileJson className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        className="h-10 w-full rounded-md border border-border bg-white pl-9 pr-3 text-sm font-medium uppercase text-slateDark outline-none transition-colors placeholder:normal-case placeholder:font-normal placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/15"
                                        id="exam-code"
                                        onChange={(event) => setExamCode(event.target.value.toUpperCase())}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                void importPackageByCode();
                                            }
                                        }}
                                        placeholder="Example: MTH-JSS3-001"
                                        value={examCode}
                                    />
                                </div>
                            </div>

                            <Button className="w-full sm:w-fit" disabled={!examCode.trim() || importing} onClick={() => void importPackageByCode()}>
                                {importing && <Loader2 className="h-4 w-4 animate-spin" />}
                                Fetch Exam Package
                            </Button>
                        </div>

                        <ValidationProgress stage={stage} />

                        {errors.length > 0 && (
                            <Alert className="border-danger/30 bg-danger/5">
                                <XCircle className="absolute left-4 top-4 h-4 w-4 text-danger" />
                                <div className="pl-6">
                                    <AlertTitle>Validation errors</AlertTitle>
                                    <AlertDescription>
                                        <ul className="mt-2 list-disc space-y-1 pl-4">
                                            {errors.map((error) => <li key={error}>{error}</li>)}
                                        </ul>
                                    </AlertDescription>
                                </div>
                            </Alert>
                        )}

                        <div className="flex flex-wrap gap-3">
                            <Button onClick={onViewImportedExams} type="button" variant="secondary">
                                View Imported Exams
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Upload Package</CardTitle>
                    <CardDescription>Drag and drop a JSON exam package or choose one from this machine.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                        <button
                            className={cn(
                                'flex min-h-56 w-full flex-col items-center justify-center rounded-md border border-dashed p-6 text-center transition-colors',
                                dragging ? 'border-primary bg-primary/5' : 'border-border bg-lightBackground hover:border-primary/60',
                            )}
                            onClick={() => inputRef.current?.click()}
                            onDragEnter={(event) => {
                                event.preventDefault();
                                setDragging(true);
                            }}
                            onDragOver={(event) => event.preventDefault()}
                            onDragLeave={(event) => {
                                event.preventDefault();
                                setDragging(false);
                            }}
                            onDrop={(event) => {
                                event.preventDefault();
                                setDragging(false);
                                acceptFile(event.dataTransfer.files[0]);
                            }}
                            type="button"
                        >
                            <UploadCloud className="h-10 w-10 text-primary" />
                            <span className="mt-4 text-base font-semibold text-slateDark">Drop exam package here</span>
                            <span className="mt-2 text-sm text-slate-500">Supported file type: .json</span>
                            {selectedFileName && (
                                <span className="mt-4 inline-flex items-center gap-2 rounded-md border border-border bg-white px-3 py-2 text-sm font-medium text-slateDark">
                                    <FileJson className="h-4 w-4 text-info" />
                                    {selectedFileName}
                                </span>
                            )}
                        </button>

                        <input
                            ref={inputRef}
                            accept=".json,application/json"
                            className="hidden"
                            onChange={(event) => acceptFile(event.target.files?.[0])}
                            type="file"
                        />

                        <ValidationProgress stage={stage} />

                        {errors.length > 0 && (
                            <Alert className="border-danger/30 bg-danger/5">
                                <XCircle className="absolute left-4 top-4 h-4 w-4 text-danger" />
                                <div className="pl-6">
                                    <AlertTitle>Validation errors</AlertTitle>
                                    <AlertDescription>
                                        <ul className="mt-2 list-disc space-y-1 pl-4">
                                            {errors.map((error) => <li key={error}>{error}</li>)}
                                        </ul>
                                    </AlertDescription>
                                </div>
                            </Alert>
                        )}

                        <div className="flex flex-wrap gap-3">
                            <Button disabled={!selectedFile || importing} onClick={() => void importSelectedPackage()}>
                                {importing && <Loader2 className="h-4 w-4 animate-spin" />}
                                Import Package
                            </Button>
                            <Button onClick={onViewImportedExams} type="button" variant="secondary">
                                View Imported Exams
                            </Button>
                        </div>
                </CardContent>
            </Card>

            {summary && <ImportSummaryCard summary={summary} onViewImportedExams={onViewImportedExams} />}

            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle>Sample Package Structure</CardTitle>
                            <CardDescription>This is the JSON downloaded by the sample package button.</CardDescription>
                        </div>
                        <Badge variant="warning">Preview</Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <pre className="max-h-[520px] overflow-auto rounded-md bg-slateDark p-4 text-xs leading-6 text-slate-100">
                        <code>{sampleJson}</code>
                    </pre>
                </CardContent>
            </Card>
        </div>
    );
}

class ImportError extends Error {
    readonly errors: string[];

    constructor(errors: string[]) {
        super('Import failed.');
        this.errors = errors;
    }
}

function ValidationProgress({ stage }: { stage: ImportStage }) {
    const steps = [
        { id: 'reading', label: 'Read file' },
        { id: 'validating', label: 'Validate JSON' },
        { id: 'importing', label: 'Save to SQLite' },
    ] as const;

    return (
        <div className="grid gap-2 rounded-md border border-border bg-white p-3 sm:grid-cols-3">
            {steps.map((step) => {
                const active = stage === step.id;
                const complete = stage === 'success' || steps.findIndex((item) => item.id === stage) > steps.findIndex((item) => item.id === step.id);

                return (
                    <div key={step.id} className="flex items-center gap-2 text-sm">
                        {active ? (
                            <Loader2 className="h-4 w-4 animate-spin text-info" />
                        ) : complete ? (
                            <CheckCircle2 className="h-4 w-4 text-success" />
                        ) : (
                            <span className="h-4 w-4 rounded-full border border-border" />
                        )}
                        <span className={active || complete ? 'font-semibold text-slateDark' : 'text-slate-500'}>{step.label}</span>
                    </div>
                );
            })}
        </div>
    );
}

function ImportSummaryCard({ summary, onViewImportedExams }: { summary: ImportSummary; onViewImportedExams: () => void }) {
    const items = [
        ['Exam Title', summary.title],
        ['Exam Code', summary.exam_code],
        ['Organization', summary.organization_name],
        ['Candidates', String(summary.candidate_count)],
        ['Questions', String(summary.question_count)],
        ['Duration', `${summary.duration_minutes} minutes`],
        ['Status', summary.status],
    ];

    return (
        <Card className="border-success/30">
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <CheckCircle2 className="h-5 w-5 text-success" />
                            Package Imported
                        </CardTitle>
                        <CardDescription>The exam package is ready on this center server.</CardDescription>
                    </div>
                    <Button onClick={onViewImportedExams} variant="secondary">View Imported Exams</Button>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4 md:grid-cols-4">
                {items.map(([label, value]) => (
                    <div key={label} className="min-w-0">
                        <div className="text-xs font-medium uppercase text-slate-500">{label}</div>
                        <div className="mt-1 truncate font-semibold text-slateDark">{value}</div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function Toast({ tone, message }: ToastState) {
    return (
        <div className="fixed right-6 top-6 z-50 rounded-md border border-border bg-white px-4 py-3 shadow-lg">
            <div className="flex items-center gap-3 text-sm font-semibold text-slateDark">
                {tone === 'success' ? <CheckCircle2 className="h-5 w-5 text-success" /> : <XCircle className="h-5 w-5 text-danger" />}
                {message}
            </div>
        </div>
    );
}
