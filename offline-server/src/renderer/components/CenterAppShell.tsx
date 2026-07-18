import {
    Activity,
    Database,
    FileInput,
    LayoutDashboard,
    LogOut,
    MonitorUp,
    Settings,
    Upload,
    Wifi,
    WifiOff,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useServerStatus } from '../hooks/useServerStatus';
import { Button } from './ui/button';
import { cn } from '@/lib/utils';
import type { AppPage } from '../App';

const navItems = [
    { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'import-exam', label: 'Import Exam', icon: FileInput, feature: 'exam_package_import' },
    { id: 'exams', label: 'Exams', icon: Database, feature: 'offline_delivery' },
    { id: 'active-monitor', label: 'Active Monitor', icon: MonitorUp, feature: 'offline_delivery' },
    { id: 'results-export', label: 'Results Export', icon: Upload, feature: 'result_package_export' },
    { id: 'settings', label: 'Settings', icon: Settings },
] satisfies Array<{ id: AppPage; label: string; icon: LucideIcon; feature?: string }>;

type CenterAppShellProps = {
    children: ReactNode;
    currentPage: AppPage;
    onNavigate: (page: AppPage) => void;
    onLogout: () => void;
};

export function CenterAppShell({ children, currentPage, onNavigate, onLogout }: CenterAppShellProps) {
    const { status, loading, error, refresh } = useServerStatus();
    const online = Boolean(status && !error);
    const visibleNavItems = navItems.filter((item) => !item.feature || status?.plan_features?.[item.feature]);

    return (
        <div className="flex min-h-screen bg-lightBackground">
            <aside className="flex w-72 shrink-0 flex-col border-r border-border bg-white">
                <div className="border-b border-border px-6 py-5">
                    <div className="flex items-center gap-3">
                        <img src="./images/logo.png" alt="AlignEx" className="h-11 w-11 object-contain" />
                        <div>
                            <div className="text-lg font-semibold text-slateDark">AlignEx Center</div>
                            <div className="mt-1 text-sm text-slate-500">Offline CBT Server</div>
                        </div>
                    </div>
                </div>

                <nav className="flex-1 space-y-1 p-4">
                    {visibleNavItems.map((item) => (
                        <button
                            key={item.label}
                            className={cn(
                                'flex h-11 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-medium transition-colors',
                                currentPage === item.id
                                    ? 'bg-primary text-white'
                                    : 'text-slate-600 hover:bg-lightBackground hover:text-slateDark',
                            )}
                            onClick={() => onNavigate(item.id)}
                            aria-current={currentPage === item.id ? 'page' : undefined}
                            type="button"
                        >
                            <item.icon className="h-4 w-4" />
                            <span>{item.label}</span>
                            {item.id === 'exams' && (
                                <span
                                    className={cn(
                                        'ml-auto rounded-md px-2 py-0.5 text-xs font-semibold',
                                        currentPage === item.id ? 'bg-white/20 text-white' : 'bg-lightBackground text-slate-600',
                                    )}
                                >
                                    {status?.importedExams ?? 0}
                                </span>
                            )}
                        </button>
                    ))}
                </nav>

                <div className="border-t border-border p-4 text-xs text-slate-500">
                    <div>Local server data is stored on this center machine.</div>
                    {status?.plan?.name && <div className="mt-2 font-medium text-slate-600">Plan: {status.plan.name}</div>}
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex min-h-20 items-center justify-between gap-4 border-b border-border bg-white px-6">
                    <div className="flex min-w-0 flex-1 items-center gap-5">
                        <StatusItem
                            icon={online ? <Wifi className="h-4 w-4 text-success" /> : <WifiOff className="h-4 w-4 text-danger" />}
                            label="Server Status"
                            value={loading ? 'Checking...' : online ? 'Online' : 'Offline'}
                        />
                        <StatusItem label="Local IP Address" value={status?.localIpAddress ?? 'Unavailable'} />
                        <StatusItem label="Candidate URL" value={status?.candidateUrl ?? 'Unavailable'} wide />
                        <StatusItem
                            icon={<Activity className="h-4 w-4 text-info" />}
                            label="Connected Candidates"
                            value={String(status?.connectedCandidates ?? 0)}
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Button onClick={() => void refresh()} size="sm" variant="outline">
                            Refresh
                        </Button>
                        <Button onClick={onLogout} size="sm" variant="secondary">
                            <LogOut className="h-4 w-4" />
                            Logout
                        </Button>
                    </div>
                </header>

                <main className="min-w-0 flex-1 overflow-auto p-6">{children}</main>
            </div>
        </div>
    );
}

function StatusItem({ label, value, icon, wide = false }: { label: string; value: string; icon?: ReactNode; wide?: boolean }) {
    return (
        <div className={cn('min-w-0', wide ? 'max-w-md flex-1' : 'w-40')}>
            <div className="text-xs font-medium uppercase text-slate-500">{label}</div>
            <div className="mt-1 flex min-w-0 items-center gap-2 text-sm font-semibold text-slateDark">
                {icon}
                <span className="truncate">{value}</span>
            </div>
        </div>
    );
}
