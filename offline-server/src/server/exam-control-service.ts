import { randomUUID } from 'node:crypto';
import type { Database } from 'better-sqlite3';
import { autoSubmitActiveAttemptsForExam, type AutoSubmitResult } from './candidate-submit-service.js';
import { getExamSummary, type ExamSummary } from './exam-queries.js';

export class ExamControlError extends Error {
    readonly statusCode: number;

    constructor(message: string, statusCode: number) {
        super(message);
        this.statusCode = statusCode;
    }
}

export function startExam(connection: Database, examId: string): ExamSummary {
    const transaction = connection.transaction(() => {
        const exam = connection.prepare('SELECT id, title, status FROM imported_exams WHERE id = ?').get(examId) as
            | { id: string; title: string; status: string }
            | undefined;

        if (!exam) {
            throw new ExamControlError('Exam not found.', 404);
        }

        if (exam.status !== 'ready') {
            throw new ExamControlError('Only exams with ready status can be started.', 409);
        }

        const activeExam = connection.prepare('SELECT id, title FROM imported_exams WHERE status = ? AND id <> ? LIMIT 1').get('active', examId) as
            | { id: string; title: string }
            | undefined;

        if (activeExam) {
            throw new ExamControlError(`Another exam is already active: ${activeExam.title}.`, 409);
        }

        const now = new Date().toISOString();

        connection.prepare(`
            UPDATE imported_exams
            SET status = 'active',
                actual_started_at = ?,
                updated_at = ?
            WHERE id = ?
        `).run(now, now, examId);

        connection.prepare(`
            INSERT INTO exam_events (
                id, exam_id, attempt_id, candidate_id, event_type, severity,
                message, metadata, occurred_at, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).run(
            randomUUID(),
            examId,
            null,
            null,
            'exam_started',
            'info',
            `Exam ${exam.title} started.`,
            JSON.stringify({ exam_id: examId, previous_status: exam.status }),
            now,
            now,
            now,
        );

        return getExamSummary(connection, examId);
    });

    const summary = transaction();

    if (!summary) {
        throw new ExamControlError('Exam not found after start.', 404);
    }

    return summary;
}

export type CloseExamResult = {
    exam: ExamSummary;
    auto_submitted_attempts: AutoSubmitResult[];
};

export function closeExam(connection: Database, examId: string): CloseExamResult {
    const exam = connection.prepare('SELECT id, title, status FROM imported_exams WHERE id = ?').get(examId) as
        | { id: string; title: string; status: string }
        | undefined;

    if (!exam) {
        throw new ExamControlError('Exam not found.', 404);
    }

    if (exam.status !== 'active') {
        throw new ExamControlError('Only active exams can be closed.', 409);
    }

    const autoSubmittedAttempts = autoSubmitActiveAttemptsForExam(connection, examId);
    const now = new Date().toISOString();

    connection.prepare(`
        UPDATE imported_exams
        SET status = 'closed',
            actual_closed_at = ?,
            updated_at = ?
        WHERE id = ?
    `).run(now, now, examId);

    connection.prepare(`
        INSERT INTO exam_events (
            id, exam_id, attempt_id, candidate_id, event_type, severity,
            message, metadata, occurred_at, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
        randomUUID(),
        examId,
        null,
        null,
        'exam_closed',
        'warning',
        `Exam ${exam.title} closed.`,
        JSON.stringify({ exam_id: examId, auto_submitted_attempts: autoSubmittedAttempts.length }),
        now,
        now,
        now,
    );

    const summary = getExamSummary(connection, examId);

    if (!summary) {
        throw new ExamControlError('Exam not found after close.', 404);
    }

    return {
        exam: summary,
        auto_submitted_attempts: autoSubmittedAttempts,
    };
}
