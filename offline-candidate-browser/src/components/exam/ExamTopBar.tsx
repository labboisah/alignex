import type { CandidateProfile, ExamDetails } from '../../types/api';
import { ExamTimer } from './ExamTimer';
import { ConnectionStatus } from './ConnectionStatus';
import { LockKeyhole } from 'lucide-react';

export function ExamTopBar({
    candidate,
    exam,
    remainingSeconds,
    connectionStatus,
    lockdownActive = false,
}: {
    candidate: CandidateProfile | null;
    exam: ExamDetails | null;
    remainingSeconds: number | null;
    connectionStatus: 'unknown' | 'connected' | 'reconnecting' | 'disconnected' | 'syncing';
    lockdownActive?: boolean;
}) {
    return (
        <header className="sticky top-0 z-20 border-b border-border bg-white/95 px-5 py-3 shadow-sm backdrop-blur">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
                <div className="min-w-0">
                    <div className="truncate text-lg font-bold text-slateDark">{candidate?.full_name ?? 'Candidate'}</div>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-slate-500">
                        <span>{candidate?.registration_number ?? '-'}</span>
                        <span className="max-w-xl truncate">{exam?.title ?? 'Exam'}</span>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    {lockdownActive && (
                        <div className="inline-flex items-center gap-2 rounded-md border border-accentOrange/40 bg-accentOrange/10 px-3 py-2 text-sm font-bold text-accentOrange">
                            <LockKeyhole className="h-4 w-4" />
                            Lockdown Active
                        </div>
                    )}
                    <ExamTimer seconds={remainingSeconds} />
                    <ConnectionStatus status={connectionStatus} />
                </div>
            </div>
        </header>
    );
}
