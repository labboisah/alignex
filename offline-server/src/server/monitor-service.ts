import type { Database } from 'better-sqlite3';
import { randomUUID } from 'node:crypto';

export type MonitorSummary = {
    total_candidates: number;
    not_started: number;
    active: number;
    submitted: number;
    auto_submitted: number;
    disqualified: number;
    disconnected: number;
};

export type MonitorCandidateRow = {
    id: string;
    full_name: string;
    registration_number: string;
    status: string;
    answered_count: number;
    total_questions: number;
    unanswered_count: number;
    progress_percentage: number;
    ip_address: string | null;
    device_fingerprint: string | null;
    started_at: string | null;
    last_saved_at: string | null;
    submitted_at: string | null;
};

export type CandidateSocketIdentity = {
    attempt_id: string;
    candidate_id: string;
    exam_id: string;
};

type CandidateMonitorQueryRow = {
    id: string;
    full_name: string;
    registration_number: string;
    candidate_status: string;
    attempt_status: string | null;
    ip_address: string | null;
    device_fingerprint: string | null;
    started_at: string | null;
    last_saved_at: string | null;
    submitted_at: string | null;
    answered_count: number;
    total_questions: number;
};

export function getMonitorSummary(connection: Database, examId: string, connectedCandidateIds: Set<string>): MonitorSummary {
    const rows = connection.prepare(`
        SELECT
            candidates.id,
            candidates.status AS candidate_status,
            candidate_attempts.status AS attempt_status
        FROM candidates
        LEFT JOIN candidate_attempts
            ON candidate_attempts.exam_id = candidates.exam_id
            AND candidate_attempts.candidate_id = candidates.id
        WHERE candidates.exam_id = ?
    `).all(examId) as Array<{ id: string; candidate_status: string; attempt_status: string | null }>;

    const statusFor = (row: { candidate_status: string; attempt_status: string | null }) => row.attempt_status ?? row.candidate_status;

    return {
        total_candidates: rows.length,
        not_started: rows.filter((row) => statusFor(row) === 'not_started').length,
        active: rows.filter((row) => statusFor(row) === 'active').length,
        submitted: rows.filter((row) => statusFor(row) === 'submitted').length,
        auto_submitted: rows.filter((row) => statusFor(row) === 'auto_submitted').length,
        disqualified: rows.filter((row) => statusFor(row) === 'disqualified').length,
        disconnected: rows.filter((row) => statusFor(row) === 'active' && !connectedCandidateIds.has(row.id)).length,
    };
}

export function listMonitorCandidates(connection: Database, examId: string): MonitorCandidateRow[] {
    const rows = connection.prepare(`
        SELECT
            candidates.id,
            candidates.full_name,
            candidates.candidate_no AS registration_number,
            candidates.status AS candidate_status,
            candidate_attempts.status AS attempt_status,
            candidate_attempts.ip_address,
            candidate_attempts.device_fingerprint,
            candidate_attempts.started_at,
            candidate_attempts.submitted_at,
            MAX(candidate_answers.saved_at) AS last_saved_at,
            COUNT(DISTINCT candidate_answers.question_id) AS answered_count,
            COUNT(DISTINCT candidate_papers.question_id) AS total_questions
        FROM candidates
        LEFT JOIN candidate_attempts
            ON candidate_attempts.exam_id = candidates.exam_id
            AND candidate_attempts.candidate_id = candidates.id
        LEFT JOIN candidate_papers
            ON candidate_papers.attempt_id = candidate_attempts.id
        LEFT JOIN candidate_answers
            ON candidate_answers.attempt_id = candidate_attempts.id
            AND candidate_answers.option_ids <> '[]'
        WHERE candidates.exam_id = ?
        GROUP BY candidates.id, candidate_attempts.id
        ORDER BY candidates.full_name ASC
    `).all(examId) as CandidateMonitorQueryRow[];

    return rows.map((row) => {
        const totalQuestions = Number(row.total_questions) || 0;
        const answeredCount = Number(row.answered_count) || 0;

        return {
            id: row.id,
            full_name: row.full_name,
            registration_number: row.registration_number,
            status: row.attempt_status ?? row.candidate_status,
            answered_count: answeredCount,
            total_questions: totalQuestions,
            unanswered_count: Math.max(totalQuestions - answeredCount, 0),
            progress_percentage: totalQuestions > 0 ? Math.round((answeredCount / totalQuestions) * 100) : 0,
            ip_address: row.ip_address,
            device_fingerprint: row.device_fingerprint,
            started_at: row.started_at,
            last_saved_at: row.last_saved_at,
            submitted_at: row.submitted_at,
        };
    });
}

export function resetCandidateDevice(connection: Database, examId: string, candidateId: string): MonitorCandidateRow | null {
    const attempt = connection.prepare(`
        SELECT id, status
        FROM candidate_attempts
        WHERE exam_id = ? AND candidate_id = ?
        LIMIT 1
    `).get(examId, candidateId) as { id: string; status: string } | undefined;

    if (!attempt || attempt.status !== 'active') {
        return null;
    }

    const now = new Date().toISOString();

    connection.prepare(`
        UPDATE candidate_attempts
        SET device_fingerprint = NULL,
            attempt_token = NULL,
            ip_address = NULL,
            updated_at = ?
        WHERE id = ?
    `).run(now, attempt.id);

    connection.prepare(`
        INSERT INTO exam_events (
            id, exam_id, attempt_id, candidate_id, event_type, severity,
            message, metadata, occurred_at, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
        randomUUID(),
        examId,
        attempt.id,
        candidateId,
        'candidate_device_reset',
        'warning',
        'Candidate device binding was reset by supervisor.',
        '{}',
        now,
        now,
        now,
    );

    return listMonitorCandidates(connection, examId).find((candidate) => candidate.id === candidateId) ?? null;
}

export function getCandidateSocketIdentity(connection: Database, attemptToken: string): CandidateSocketIdentity | null {
    if (!attemptToken.trim()) {
        return null;
    }

    const row = connection.prepare(`
        SELECT id AS attempt_id, candidate_id, exam_id
        FROM candidate_attempts
        WHERE attempt_token = ?
        LIMIT 1
    `).get(attemptToken) as CandidateSocketIdentity | undefined;

    return row ?? null;
}
