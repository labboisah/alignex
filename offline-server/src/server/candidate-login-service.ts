import { randomUUID } from 'node:crypto';
import type { Database } from 'better-sqlite3';
import { ensureCandidatePaper } from './candidate-exam-service.js';

export type CandidateLoginRequest = {
    exam_code?: string;
    registration_number: string;
    device_fingerprint: string;
    ip_address: string;
};

export type CandidateLoginResponse = {
    attempt_token: string;
    candidate: {
        id: string;
        registration_number: string;
        full_name: string;
        group_name: string | null;
    };
    exam: {
        id: string;
        exam_code: string;
        title: string;
        organization_name: string;
        duration_minutes: number;
        total_questions: number;
        started_at: string | null;
    };
    remaining_time_seconds: number;
};

export class CandidateLoginError extends Error {
    readonly statusCode: number;
    readonly code: string;

    constructor(message: string, code: string, statusCode = 400) {
        super(message);
        this.code = code;
        this.statusCode = statusCode;
    }
}

type ExamRow = {
    id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    duration_minutes: number;
    status: string;
    actual_started_at: string | null;
};

type CandidateRow = {
    id: string;
    candidate_no: string;
    full_name: string;
    group_name: string | null;
    status: string;
};

type AttemptRow = {
    id: string;
    status: string;
    started_at: string | null;
    submitted_at: string | null;
    device_fingerprint: string | null;
    attempt_token: string | null;
};

export function loginCandidate(connection: Database, request: CandidateLoginRequest): CandidateLoginResponse {
    validateRequest(request);

    const exam = request.exam_code?.trim()
        ? connection.prepare(`
            SELECT id, exam_code, title, organization_name, duration_minutes, status, actual_started_at
            FROM imported_exams
            WHERE exam_code = ?
            LIMIT 1
        `).get(request.exam_code.trim()) as ExamRow | undefined
        : findActiveExamForCandidate(connection, request.registration_number);

    if (!exam) {
        throw new CandidateLoginError('No active exam was found for this registration number.', 'active_exam_not_found', 404);
    }

    try {
        return loginCandidateForExam(connection, exam, request);
    } catch (error) {
        logCandidateLoginFailure(connection, exam.id, null, error instanceof Error ? error.message : 'Candidate login failed.');
        throw error;
    }
}

function loginCandidateForExam(connection: Database, exam: ExamRow, request: CandidateLoginRequest): CandidateLoginResponse {
    if (exam.status !== 'active') {
        throw new CandidateLoginError('This exam is not active yet. Please wait for the supervisor to start it.', 'exam_not_active', 409);
    }

    const candidate = connection.prepare(`
        SELECT id, candidate_no, full_name, group_name, status
        FROM candidates
        WHERE exam_id = ? AND candidate_no = ?
        LIMIT 1
    `).get(exam.id, request.registration_number.trim()) as CandidateRow | undefined;

    if (!candidate) {
        throw new CandidateLoginError('Candidate not found for this exam.', 'candidate_not_found', 404);
    }

    if (candidate.status === 'submitted' || candidate.status === 'auto_submitted') {
        throw new CandidateLoginError('This candidate has already submitted the exam.', 'already_submitted', 409);
    }

    if (candidate.status === 'disqualified') {
        throw new CandidateLoginError('This candidate has been disqualified from the exam.', 'disqualified', 409);
    }

    const existingAttempt = connection.prepare(`
        SELECT id, status, started_at, submitted_at, device_fingerprint, attempt_token
        FROM candidate_attempts
        WHERE exam_id = ? AND candidate_id = ?
        LIMIT 1
    `).get(exam.id, candidate.id) as AttemptRow | undefined;

    if (existingAttempt?.status === 'submitted' || existingAttempt?.status === 'auto_submitted') {
        throw new CandidateLoginError('This candidate has already submitted the exam.', 'already_submitted', 409);
    }

    if (existingAttempt?.status === 'disqualified') {
        throw new CandidateLoginError('This candidate has been disqualified from the exam.', 'disqualified', 409);
    }

    if (
        existingAttempt?.status === 'active'
        && existingAttempt.device_fingerprint
        && existingAttempt.device_fingerprint !== request.device_fingerprint
    ) {
        throw new CandidateLoginError('This candidate is already logged in on another device.', 'already_logged_in', 409);
    }

    const attemptToken = existingAttempt?.attempt_token ?? randomUUID();
    const now = new Date().toISOString();

    const runLogin = connection.transaction(() => {
        if (existingAttempt) {
            connection.prepare(`
                UPDATE candidate_attempts
                SET status = 'active',
                    started_at = COALESCE(started_at, ?),
                    ip_address = ?,
                    device_fingerprint = ?,
                    attempt_token = ?,
                    updated_at = ?
                WHERE id = ?
            `).run(now, request.ip_address, request.device_fingerprint, attemptToken, now, existingAttempt.id);
        } else {
            connection.prepare(`
                INSERT INTO candidate_attempts (
                    id, exam_id, candidate_id, started_at, submitted_at, ip_address,
                    device_fingerprint, attempt_token, status, score, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            `).run(
                randomUUID(),
                exam.id,
                candidate.id,
                now,
                null,
                request.ip_address,
                request.device_fingerprint,
                attemptToken,
                'active',
                null,
                now,
                now,
            );
        }

        connection.prepare("UPDATE candidates SET status = 'active', updated_at = ? WHERE id = ?").run(now, candidate.id);
        logCandidateEvent(connection, exam.id, candidate.id, 'login_success', 'info', 'Candidate logged in successfully.', {
            registration_number: candidate.candidate_no,
            ip_address: request.ip_address,
        });
    });

    runLogin();
    ensureCandidatePaper(connection, attemptToken);

    return {
        attempt_token: attemptToken,
        candidate: {
            id: candidate.id,
            registration_number: candidate.candidate_no,
            full_name: candidate.full_name,
            group_name: candidate.group_name,
        },
        exam: {
            id: exam.id,
            exam_code: exam.exam_code,
            title: exam.title,
            organization_name: exam.organization_name,
            duration_minutes: exam.duration_minutes,
            total_questions: countQuestions(connection, exam.id),
            started_at: exam.actual_started_at,
        },
        remaining_time_seconds: calculateRemainingSeconds(exam),
    };
}

function countQuestions(connection: Database, examId: string): number {
    const row = connection.prepare('SELECT COUNT(*) AS count FROM questions WHERE exam_id = ?').get(examId) as { count: number };
    return row.count;
}

function validateRequest(request: CandidateLoginRequest): void {
    if (!request.registration_number?.trim()) {
        throw new CandidateLoginError('Registration number is required.', 'candidate_not_found', 422);
    }

    if (!request.device_fingerprint?.trim()) {
        throw new CandidateLoginError('Device fingerprint is required.', 'device_fingerprint_required', 422);
    }
}

function findActiveExamForCandidate(connection: Database, registrationNumber: string): ExamRow | undefined {
    return connection.prepare(`
        SELECT imported_exams.id,
               imported_exams.exam_code,
               imported_exams.title,
               imported_exams.organization_name,
               imported_exams.duration_minutes,
               imported_exams.status,
               imported_exams.actual_started_at
        FROM imported_exams
        INNER JOIN candidates ON candidates.exam_id = imported_exams.id
        WHERE imported_exams.status = 'active'
          AND candidates.candidate_no = ?
        ORDER BY imported_exams.actual_started_at DESC, imported_exams.created_at DESC
        LIMIT 1
    `).get(registrationNumber.trim()) as ExamRow | undefined;
}

function calculateRemainingSeconds(exam: ExamRow): number {
    const startedAt = exam.actual_started_at ? new Date(exam.actual_started_at).getTime() : Date.now();
    const endsAt = startedAt + exam.duration_minutes * 60 * 1000;

    return Math.max(0, Math.floor((endsAt - Date.now()) / 1000));
}

function logCandidateLoginFailure(connection: Database, examId: string, candidateId: string | null, message: string): void {
    logCandidateEvent(connection, examId, candidateId, 'login_failed', 'warning', message, {});
}

function logCandidateEvent(
    connection: Database,
    examId: string,
    candidateId: string | null,
    eventType: string,
    severity: string,
    message: string,
    metadata: Record<string, unknown>,
): void {
    const now = new Date().toISOString();

    connection.prepare(`
        INSERT INTO exam_events (
            id, exam_id, attempt_id, candidate_id, event_type, severity,
            message, metadata, occurred_at, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(randomUUID(), examId, null, candidateId, eventType, severity, message, JSON.stringify(metadata), now, now, now);
}
