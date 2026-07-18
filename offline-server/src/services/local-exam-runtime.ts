import type { OfflineAnswer, OfflineAuditEvent, OfflineAttempt } from '../contracts/sync-bundle.js';
import { randomUUID } from 'node:crypto';

export type CandidateSession = {
    attempt: OfflineAttempt;
    remainingSeconds: number;
};

export class LocalExamRuntime {
    startAttempt(candidateId: string, candidateNo: string): OfflineAttempt {
        const now = new Date().toISOString();

        return {
            id: randomUUID(),
            candidateId,
            candidateNo,
            startedAt: now,
            submittedAt: null,
            status: 'in_progress',
            answers: [],
        };
    }

    saveAnswer(attempt: OfflineAttempt, answer: OfflineAnswer): OfflineAttempt {
        const answers = attempt.answers.filter((item) => item.questionId !== answer.questionId);

        return {
            ...attempt,
            answers: [...answers, answer],
        };
    }

    submitAttempt(attempt: OfflineAttempt, status: OfflineAttempt['status'] = 'submitted'): OfflineAttempt {
        return {
            ...attempt,
            submittedAt: new Date().toISOString(),
            status,
        };
    }

    createEvent(event: Omit<OfflineAuditEvent, 'id' | 'occurredAt'>): OfflineAuditEvent {
        return {
            ...event,
            id: randomUUID(),
            occurredAt: new Date().toISOString(),
        };
    }
}
