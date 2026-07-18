import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    Database,
    HardDrive,
    Link,
    Loader2,
    MonitorCheck,
    Network,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useServerStatus } from '../hooks/useServerStatus';
import type { CenterServerStatus, ServerInfo } from '../types/status';
import { Button } from '../components/ui/button';

export function Dashboard() {
    const { status, serverInfo, loading, error, refresh } = useServerStatus();

    return (
        <div className="space-y-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slateDark">Dashboard</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Local center server readiness, network address, candidate access, and current exam activity.
                    </p>
                </div>

                <Button onClick={() => void refresh()} variant="outline">
                    Refresh Status
                </Button>
            </div>

            {loading && (
                <StatePanel
                    icon={<Loader2 className="h-5 w-5 animate-spin text-info" />}
                    title="Fetching server status"
                    body="Checking the local Express API and SQLite status."
                />
            )}

            {error && !loading && (
                <StatePanel
                    icon={<AlertTriangle className="h-5 w-5 text-danger" />}
                    title="Database or server API is not reachable"
                    body={`The dashboard could not reach the local server/database status endpoints. ${error}`}
                    danger
                />
            )}

            {status && serverInfo && !loading && !error && <StatusGrid status={status} serverInfo={serverInfo} />}

            <section className="rounded-md border border-border bg-white p-5">
                <div className="flex items-center gap-2">
                    <MonitorCheck className="h-5 w-5 text-primary" />
                    <h2 className="font-semibold text-slateDark">Center Server Readiness</h2>
                </div>
                <div className="mt-4 grid gap-3 md:grid-cols-3">
                    <ReadinessItem ready={Boolean(status)} label="Express local server" />
                    <ReadinessItem ready={Boolean(serverInfo?.databaseConnected)} label="Database connected" />
                    <ReadinessItem ready={Boolean(status?.database.walEnabled)} label="SQLite WAL mode" />
                    <ReadinessItem ready={Boolean(status?.candidateUrl)} label="Candidate URL available" />
                </div>
            </section>
        </div>
    );
}

function StatusGrid({ status, serverInfo }: { status: CenterServerStatus; serverInfo: ServerInfo }) {
    const cards = [
        {
            label: 'Server Status',
            value: status.serverStatus === 'online' ? 'Online' : 'Offline',
            icon: CheckCircle2,
            tone: 'success',
        },
        {
            label: 'Database Status',
            value: serverInfo.databaseConnected ? 'Connected' : 'Disconnected',
            icon: HardDrive,
            tone: serverInfo.databaseConnected ? 'success' : 'danger',
        },
        {
            label: 'Local IP',
            value: status.localIpAddress,
            icon: Network,
            tone: 'info',
        },
        {
            label: 'Candidate URL',
            value: status.candidateUrl,
            icon: Link,
            tone: 'primary',
            wideText: true,
        },
        {
            label: 'Imported Exams',
            value: String(serverInfo.importedExamsCount),
            icon: ClipboardList,
            tone: 'accent',
        },
        {
            label: 'Active Exams',
            value: String(serverInfo.activeExamCount),
            icon: MonitorCheck,
            tone: 'primary',
        },
        {
            label: 'Total Candidates',
            value: String(serverInfo.totalCandidatesCount),
            icon: Users,
            tone: 'info',
        },
        {
            label: 'Active Candidates',
            value: String(status.activeCandidates),
            icon: Users,
            tone: 'info',
        },
        {
            label: 'Submitted Candidates',
            value: String(status.submittedCandidates),
            icon: Database,
            tone: 'success',
        },
    ];

    return (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {cards.map((card) => (
                <article key={card.label} className="rounded-md border border-border bg-white p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0">
                            <div className="text-sm font-semibold uppercase text-slate-500">{card.label}</div>
                            <div className={`mt-3 font-bold text-slateDark ${card.wideText ? 'truncate text-xl' : 'text-4xl'}`}>
                                {card.value}
                            </div>
                        </div>
                        <div className={`rounded-md p-3 ${iconToneClass(card.tone)}`}>
                            <card.icon className="h-6 w-6" />
                        </div>
                    </div>
                </article>
            ))}
        </section>
    );
}

function StatePanel({ icon, title, body, danger = false }: { icon: ReactNode; title: string; body: string; danger?: boolean }) {
    return (
        <div className={`rounded-md border bg-white p-5 ${danger ? 'border-danger/30' : 'border-border'}`}>
            <div className="flex items-start gap-3">
                {icon}
                <div>
                    <div className="font-semibold text-slateDark">{title}</div>
                    <div className="mt-1 text-sm text-slate-500">{body}</div>
                </div>
            </div>
        </div>
    );
}

function ReadinessItem({ ready, label }: { ready: boolean; label: string }) {
    return (
        <div className="flex items-center gap-3 rounded-md border border-border bg-lightBackground px-3 py-3">
            {ready ? <CheckCircle2 className="h-5 w-5 text-success" /> : <Activity className="h-5 w-5 text-slate-400" />}
            <span className="text-sm font-medium text-slateDark">{label}</span>
        </div>
    );
}

function iconToneClass(tone: string): string {
    switch (tone) {
        case 'success':
            return 'bg-success/10 text-success';
        case 'info':
            return 'bg-info/10 text-info';
        case 'accent':
            return 'bg-accentOrange/10 text-accentOrange';
        case 'danger':
            return 'bg-danger/10 text-danger';
        default:
            return 'bg-primary/10 text-primary';
    }
}
