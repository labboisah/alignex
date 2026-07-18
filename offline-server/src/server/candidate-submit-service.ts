import { randomUUID } from 'node:crypto';
import type { Database } from 'better-sqlite3';

export type AutoSubmitResult = {
    success: true;
    attempt_id: string;
    exam_id: string;
    candidate_id: string;
    status: 'submitted' | 'auto_submitted';
    score: number;
    answered_count: number;
    total_questions: number;
    total_marks: number;
    submitted_at: string;
};

export class CandidateSubmitError extends Error {
    readonly statusCode: number;
    readonly code: string;

    constructor(message: string, code: string, statusCode = 400) {
        super(message);
        this.code = code;
        this.statusCode = statusCode;
    }
}

type SubmitContext = {
    attempt_id: string;
    attempt_status: string;
    started_at: string | null;
    candidate_id: string;
    exam_id: string;
    exam_status: string;
    duration_minutes: number;
};

type AnswerRow = {
    id: string;
    question_id: string;
    option_ids: string;
};

type QuestionScore = {
    questionId: string;
    marks: number;
    correctOptionIds: string[];
};

export function submitCandidateAttempt(connection: Database, attemptToken: string): AutoSubmitResult {
    return finalizeCandidateAttempt(connection, attemptToken, 'submitted');
}

export function autoSubmitCandidateAttempt(connection: Database, attemptToken: string): AutoSubmitResult {
    return finalizeCandidateAttempt(connection, attemptToken, 'auto_submitted');
}

export function autoSubmitActiveAttemptsForExam(connection: Database, examId: string): AutoSubmitResult[] {
    const attempts = connection.prepare(`
        SELECT id
        FROM candidate_attempts
        WHERE exam_id = ?
            AND status = 'active'
        ORDER BY started_at ASC
    `).all(examId) as Array<{ id: string }>;

    return attempts.map((attempt) => finalizeSubmitContext(
        connection,
        getSubmitContextByAttemptId(connection, attempt.id),
        'auto_submitted',
        'Candidate attempt auto-submitted because the exam was closed.',
    ));
}

function finalizeCandidateAttempt(connection: Database, attemptToken: string, status: 'submitted' | 'auto_submitted'): AutoSubmitResult {
    if (!attemptToken.trim()) {
        throw new CandidateSubmitError('Attempt token is required.', 'attempt_token_required', 401);
    }

    const context = getSubmitContext(connection, attemptToken);
    const message = status === 'auto_submitted' ? 'Candidate attempt auto-submitted after timer expiry.' : 'Candidate submitted exam.';

    return finalizeSubmitContext(connection, context, status, message);
}

function finalizeSubmitContext(
    connection: Database,
    context: SubmitContext,
    status: 'submitted' | 'auto_submitted',
    eventMessage: string,
): AutoSubmitResult {
    if (context.attempt_status === 'submitted' || context.attempt_status === 'auto_submitted') {
        throw new CandidateSubmitError('This exam has already been submitted.', 'already_submitted', 409);
    }

    if (context.attempt_status === 'disqualified') {
        throw new CandidateSubmitError('This candidate has been disqualified.', 'disqualified', 409);
    }

    if (context.attempt_status !== 'active') {
        throw new CandidateSubmitError('Only active attempts can be submitted.', 'attempt_not_active', 409);
    }

    if (context.exam_status !== 'active') {
        throw new CandidateSubmitError('This exam is not active.', 'exam_not_active', 409);
    }

    const submittedAt = new Date().toISOString();
    const scoring = calculateScore(connection, context);

    const transaction = connection.transaction(() => {
        for (const answer of scoring.answers) {
            connection.prepare(`
                UPDATE candidate_answers
                SET is_correct = ?,
                    marks_awarded = ?,
                    updated_at = ?
                WHERE id = ?
            `).run(answer.isCorrect ? 1 : 0, answer.marksAwarded, submittedAt, answer.id);
        }

        connection.prepare(`
            UPDATE candidate_attempts
            SET status = ?,
                submitted_at = ?,
                score = ?,
                total_questions = ?,
                total_marks = ?,
                updated_at = ?
            WHERE id = ?
        `).run(status, submittedAt, scoring.score, scoring.totalQuestions, scoring.totalMarks, submittedAt, context.attempt_id);

        connection.prepare('UPDATE candidates SET status = ?, updated_at = ? WHERE id = ?').run(status, submittedAt, context.candidate_id);

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
            status,
            status === 'auto_submitted' ? 'warning' : 'info',
            eventMessage,
            JSON.stringify({
                score: scoring.score,
                answered_count: scoring.answeredCount,
                total_questions: scoring.totalQuestions,
                total_marks: scoring.totalMarks,
            }),
            submittedAt,
            submittedAt,
            submittedAt,
        );
    });

    transaction();

    return {
        success: true,
        attempt_id: context.attempt_id,
        exam_id: context.exam_id,
        candidate_id: context.candidate_id,
        status,
        score: scoring.score,
        answered_count: scoring.answeredCount,
        total_questions: scoring.totalQuestions,
        total_marks: scoring.totalMarks,
        submitted_at: submittedAt,
    };
}

function getSubmitContext(connection: Database, attemptToken: string): SubmitContext {
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
    `).get(attemptToken) as SubmitContext | undefined;

    if (!context) {
        throw new CandidateSubmitError('Invalid attempt token.', 'invalid_attempt_token', 401);
    }

    return context;
}

function getSubmitContextByAttemptId(connection: Database, attemptId: string): SubmitContext {
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
        WHERE candidate_attempts.id = ?
        LIMIT 1
    `).get(attemptId) as SubmitContext | undefined;

    if (!context) {
        throw new CandidateSubmitError('Attempt not found.', 'attempt_not_found', 404);
    }

    return context;
}

function calculateScore(connection: Database, context: SubmitContext): {
    score: number;
    answeredCount: number;
    totalQuestions: number;
    totalMarks: number;
    answers: Array<{ id: string; isCorrect: boolean; marksAwarded: number }>;
} {
    const scoreMap = loadQuestionScoreMap(connection, context.attempt_id);
    const answers = connection.prepare(`
        SELECT id, question_id, option_ids
        FROM candidate_answers
        WHERE attempt_id = ?
    `).all(context.attempt_id) as AnswerRow[];
    const scoredAnswers: Array<{ id: string; isCorrect: boolean; marksAwarded: number }> = [];
    let score = 0;

    for (const answer of answers) {
        const questionScore = scoreMap.get(answer.question_id);

        if (!questionScore || questionScore.correctOptionIds.length === 0) {
            scoredAnswers.push({ id: answer.id, isCorrect: false, marksAwarded: 0 });
            continue;
        }

        const selectedOptionIds = parseStringArray(answer.option_ids);
        const isCorrect = sameSet(selectedOptionIds, questionScore.correctOptionIds);
        const marksAwarded = isCorrect ? questionScore.marks : 0;

        score += marksAwarded;
        scoredAnswers.push({ id: answer.id, isCorrect, marksAwarded });
    }

    const totals = calculateTotals(scoreMap);

    return {
        score,
        answeredCount: answers.filter((answer) => parseStringArray(answer.option_ids).length > 0).length,
        totalQuestions: totals.totalQuestions,
        totalMarks: totals.totalMarks,
        answers: scoredAnswers,
    };
}

function loadQuestionScoreMap(connection: Database, attemptId: string): Map<string, QuestionScore> {
    const rows = connection.prepare(`
        SELECT
            questions.id AS question_id,
            questions.marks,
            question_options.id AS option_id,
            question_options.is_correct
        FROM candidate_papers
        INNER JOIN questions ON questions.id = candidate_papers.question_id
        LEFT JOIN question_options ON question_options.question_id = questions.id
        WHERE candidate_papers.attempt_id = ?
    `).all(attemptId) as Array<{ question_id: string; marks: number; option_id: string | null; is_correct: number | null }>;
    const map = new Map<string, QuestionScore>();

    for (const row of rows) {
        const current = map.get(row.question_id) ?? { questionId: row.question_id, marks: row.marks, correctOptionIds: [] };

        if (row.option_id && row.is_correct === 1) {
            current.correctOptionIds.push(row.option_id);
        }

        map.set(row.question_id, current);
    }

    return map;
}

function calculateTotals(scoreMap: Map<string, QuestionScore>): { totalQuestions: number; totalMarks: number } {
    const questions = [...scoreMap.values()];

    return {
        totalQuestions: questions.length,
        totalMarks: questions.reduce((total, question) => total + question.marks, 0),
    };
}

function parseStringArray(value: string): string[] {
    try {
        const parsed = JSON.parse(value) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

function sameSet(left: string[], right: string[]): boolean {
    if (left.length !== right.length) {
        return false;
    }

    const rightSet = new Set(right);
    return left.every((item) => rightSet.has(item));
}
