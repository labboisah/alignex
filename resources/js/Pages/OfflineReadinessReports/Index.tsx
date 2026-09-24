import { Head, Link } from '@inertiajs/react';
import { Download, FileCheck2, Gauge, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';

type ReadinessReport = {
    id: string;
    drill_id: string;
    drill_name: string;
    readiness_status: 'ready' | 'incomplete';
    content_pack_version: string;
    expected_clients: number;
    connected_clients: number;
    completed_clients: number;
    failed_clients: number;
    total_questions: number;
    total_answers: number;
    total_submissions: number;
    event_count: number;
    payload_hash: string;
    organization_name: string | null;
    center_name: string | null;
    started_at: string | null;
    closed_at: string | null;
    uploaded_at: string | null;
};

export default function Index({ reports }: { reports: ReadinessReport[] }) {
    return (
        <>
            <Head title="Autoboot Evidence" />
            <main className="min-h-screen bg-surface px-6 py-8 text-slateDark lg:px-8">
                <div className="mx-auto max-w-7xl">
                    <div className="flex flex-col gap-5 border-b border-border pb-6 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-primary">Center operations</p>
                            <h1 className="mt-2 text-3xl font-bold text-primaryDark">Autoboot Evidence</h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Download the checksum-verified evidence retained with each accepted readiness report. Inspect server logs and SQLite artifacts alongside this bundle when diagnosing a failed run.</p>
                        </div>
                        <Button asChild variant="outline"><Link href="/offline-readiness-packages">Readiness packages</Link></Button>
                    </div>

                    {reports.length === 0 ? (
                        <div className="mt-8 border border-dashed border-border bg-white px-6 py-14 text-center">
                            <FileCheck2 className="mx-auto h-8 w-8 text-slate-400" />
                            <h2 className="mt-4 text-lg font-semibold">No accepted readiness reports</h2>
                            <p className="mt-2 text-sm text-slate-600">Reports appear here after a Center Server uploads a checksum-verified drill result.</p>
                        </div>
                    ) : (
                        <div className="mt-8 overflow-x-auto border border-border bg-white">
                            <table className="w-full min-w-[980px] text-left text-sm">
                                <thead className="border-b border-border bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-5 py-3">Drill</th>
                                        <th className="px-5 py-3">Outcome</th>
                                        <th className="px-5 py-3">Clients</th>
                                        <th className="px-5 py-3">Activity</th>
                                        <th className="px-5 py-3">Closed</th>
                                        <th className="px-5 py-3"><span className="sr-only">Download evidence</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {reports.map((report) => <tr key={report.id} className="align-top">
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-slateDark">{report.drill_name}</div>
                                            <div className="mt-1 font-mono text-xs text-slate-500">{report.drill_id}</div>
                                            <div className="mt-2 text-xs text-slate-500">{report.center_name ?? report.organization_name ?? 'Center not recorded'} · {report.content_pack_version}</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className={report.readiness_status === 'ready' ? 'inline-flex border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-success' : 'inline-flex border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800'}>{report.readiness_status}</span>
                                            <div className="mt-2 text-xs text-slate-500">{report.failed_clients} failed</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-2 font-semibold"><Users className="h-4 w-4 text-primary" />{report.connected_clients} / {report.expected_clients}</div>
                                            <div className="mt-1 text-xs text-slate-500">{report.completed_clients} completed · {report.total_submissions} submitted</div>
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-2 font-semibold"><Gauge className="h-4 w-4 text-primary" />{report.total_answers} answers</div>
                                            <div className="mt-1 text-xs text-slate-500">{report.total_questions} questions · {report.event_count} events</div>
                                        </td>
                                        <td className="px-5 py-4 text-slate-600">{formatDate(report.closed_at)}<div className="mt-2 max-w-44 truncate font-mono text-xs text-slate-400" title={report.payload_hash}>{report.payload_hash}</div></td>
                                        <td className="px-5 py-4 text-right"><Button asChild size="icon" variant="outline"><a href={`/offline-readiness-reports/${report.id}/evidence`} title={`Download evidence for ${report.drill_name}`} aria-label={`Download evidence for ${report.drill_name}`}><Download className="h-4 w-4" /></a></Button></td>
                                    </tr>)}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </main>
        </>
    );
}

function formatDate(value: string | null): string {
    return value ? new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Not recorded';
}