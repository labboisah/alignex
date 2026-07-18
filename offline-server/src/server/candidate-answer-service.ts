import { randomUUID } from 'node:crypto';
import type { Database } from 'better-sqlite3';

export type SaveCandidateAnswerRequest = {
    attemptToken: string;
    question_id: string;
    selected_option_id: string;
    time_spent_seconds: number;
};

export type SaveCandidateAnswerResult = {
    success: true;
    answered_count: number;
    total_questions: number;
    saved_at: string;
    exam_id: string;
    candidate_id: string;
};

export class CandidateAnswerError extends Error {
    readonly statusCode: number;
    readonly code: string;

    constructor(message: string, code: string, statusCode = 400) {
        super(message);
        this.code = code;
        this.statusCode = statusCode;
    }
}

type AnswerAttemptContext = {
    attempt_id: string;
    attempt_status: string;
    started_at: string | null;
    exam_id: string;
    exam_status: string;
    duration_minutes: number;
    candidate_id: string;
};

export function saveCandidateAnswer(connection: Database, request: SaveCandidateAnswerRequest): SaveCandidateAnswerResult {
    validateRequest(request);

    const context = getAttemptContext(connection, request.attemptToken);

    if (context.attempt_status !== 'active') {
        throw new CandidateAnswerError('Only active attempts can save answers.', 'attempt_not_active', 409);
    }

    if (context.exam_status !== 'active') {
        throw new CandidateAnswerError('This exam is not active.', 'exam_not_active', 409);
    }

    if (calculateRemainingSeconds(context) <= 0) {
        throw new CandidateAnswerError('Exam time has expired.', 'exam_time_expired', 409);
    }

    const paperQuestion = connection.prepare(`
        SELECT question_id
        FROM candidate_papers
        WHERE attempt_id = ? AND question_id = ?
        LIMIT 1
    `).get(context.attempt_id, request.question_id) as { question_id: string } | undefined;

    if (!paperQuestion) {
        throw new CandidateAnswerError('Question is not part of this candidate paper.', 'question_not_in_paper', 422);
    }

    const option = connection.prepare(`
        SELECT id
        FROM question_options
        WHERE id = ? AND question_id = ?
        LIMIT 1
    `).get(request.selected_option_id, request.question_id) as { id: string } | undefined;

    if (!option) {
        throw new CandidateAnswerError('Selected option does not belong to this question.', 'option_not_for_question', 422);
    }

    const savedAt = new Date().toISOString();
    const transaction = connection.transaction(() => {
        const existing = connection.prepare(`
            SELECT id
            FROM candidate_answers
            WHERE attempt_id = ? AND question_id = ?
            LIMIT 1
        `).get(context.attempt_id, request.question_id) as { id: string } | undefined;

        if (existing) {
            connection.prepare(`
                UPDATE candidate_answers
                SET option_ids = ?,
                    text_answer = NULL,
                    is_correct = NULL,
                    marks_awarded = 0,
                    saved_at = ?,
                    updated_at = ?
                WHERE id = ?
            `).run(JSON.stringify([request.selected_option_id]), savedAt, savedAt, existing.id);
        } else {
            connection.prepare(`
                INSERT INTO candidate_answers (
                    id, exam_id, candidate_id, attempt_id, question_id,
                    option_ids, text_answer, is_correct, marks_awarded, saved_at, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            `).run(
                randomUUID(),
                context.exam_id,
                context.candidate_id,
                context.attempt_id,
                request.question_id,
                JSON.stringify([request.selected_option_id]),
                null,
                null,
                0,
                savedAt,
                savedAt,
                savedAt,
            );
        }

        connection.prepare(`
            INSERT INTO exam_events (
                id, exam_id, attempt_id, candidate_id, event_type, severity,
                message, metadata, occurred_at, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).run(
            randomUUID(),
            context.exam_id,
            context.attempt_id,
            context.candidate_id,
            'answer_saved',
            'info',
            'Candidate answer saved.',
            JSON.stringify({
                question_id: request.question_id,
                selected_option_id: request.selected_option_id,
                time_spent_seconds: request.time_spent_seconds,
            }),
            savedAt,
            savedAt,
            savedAt,
        );
    });

    transaction();

    return {
        success: true,
        answered_count: countAnsweredQuestions(connection, context.attempt_id),
        total_questions: countPaperQuestions(connection, context.attempt_id),
        saved_at: savedAt,
        exam_id: context.exam_id,
        candidate_id: context.candidate_id,
    };
}

function validateRequest(request: SaveCandidateAnswerRequest): void {
    if (!request.attemptToken.trim()) {
        throw new CandidateAnswerError('Attempt token is required.', 'attempt_token_required', 401);
    }

    if (!request.question_id.trim()) {
        throw new CandidateAnswerError('Question id is required.', 'question_id_required', 422);
    }

    if (!request.selected_option_id.trim()) {
        throw new CandidateAnswerError('Selected option id is required.', 'selected_option_required', 422);
    }

    if (!Number.isFinite(request.time_spent_seconds) || request.time_spent_seconds < 0) {
        throw new CandidateAnswerError('Time spent must be a non-negative number.', 'invalid_time_spent', 422);
    }
}

function getAttemptContext(connection: Database, attemptToken: string): AnswerAttemptContext {
    const context = connection.prepare(`
        SELECT
            candidate_attempts.id AS attempt_id,
            candidate_attempts.status AS attempt_status,
            candidate_attempts.started_at,
            candidate_attempts.candidate_id,
            imported_exams.id AS exam_id,
            imported_exams.status AS exam_status,
            imported_exams.duration_minutes
        FROM candidate_attempts
        INNER JOIN imported_exams ON imported_exams.id = candidate_attempts.exam_id
        WHERE candidate_attempts.attempt_token = ?
        LIMIT 1
    `).get(attemptToken) as AnswerAttemptContext | undefined;

    if (!context) {
        throw new CandidateAnswerError('Invalid attempt token.', 'invalid_attempt_token', 401);
    }

    return context;
}

function calculateRemainingSeconds(context: AnswerAttemptContext): number {
    const startedAt = context.started_at ? new Date(context.started_at).getTime() : Date.now();
    const endsAt = startedAt + context.duration_minutes * 60 * 1000;
    return Math.max(0, Math.floor((endsAt - Date.now()) / 1000));
}

function countAnsweredQuestions(connection: Database, attemptId: string): number {
    const row = connection.prepare(`
        SELECT COUNT(*) AS count
        FROM candidate_answers
        WHERE attempt_id = ? AND option_ids <> '[]'
    `).get(attemptId) as { count: number };

    return row.count;
}

function countPaperQuestions(connection: Database, attemptId: string): number {
    const row = connection.prepare('SELECT COUNT(*) AS count FROM candidate_papers WHERE attempt_id = ?').get(attemptId) as { count: number };
    return row.count;
}
