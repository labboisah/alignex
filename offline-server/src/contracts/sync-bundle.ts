export type OfflineSyncBundle = {
    schemaVersion: '2026-07-10';
    packageId: string;
    centerId: string;
    examId: string;
    exportedAt: string;
    attempts: OfflineAttempt[];
    events: OfflineAuditEvent[];
    signature: string;
};

export type OfflineAttempt = {
    id: string;
    candidateId: string;
    candidateNo: string;
    startedAt: string;
    submittedAt: string | null;
    status: 'not_started' | 'in_progress' | 'submitted' | 'auto_submitted' | 'disqualified';
    answers: OfflineAnswer[];
};

export type OfflineAnswer = {
    questionId: string;
    optionIds: string[];
    textAnswer: string | null;
    savedAt: string;
};

export type OfflineAuditEvent = {
    id: string;
    candidateId: string | null;
    eventType: string;
    severity: 'info' | 'warning' | 'critical';
    message: string;
    occurredAt: string;
    metadata: Record<string, unknown>;
};
