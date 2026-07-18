import { randomUUID } from 'node:crypto';
import type { Database } from 'better-sqlite3';

export class CandidateExamError extends Error {
    readonly statusCode: number;
    readonly code: string;

    constructor(message: string, code: string, statusCode = 400) {
        super(message);
        this.code = code;
        this.statusCode = statusCode;
    }
}

type AttemptContext = {
    attempt_id: string;
    attempt_status: string;
    attempt_token: string;
    started_at: string | null;
    candidate_id: string;
    candidate_no: string;
    full_name: string;
    group_name: string | null;
    exam_id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    duration_minutes: number;
    actual_started_at: string | null;
    raw_payload: string | null;
};

type QuestionRow = {
    id: string;
    subject_id: string | null;
    question_type: string;
    body: string;
    marks: number;
    display_order: number;
};

type OptionRow = {
    id: string;
    question_id: string;
    option_label: string;
    body: string;
    display_order: number;
};

type PaperRow = {
    question_id: string;
    display_order: number;
    option_order_json: string;
};

export type CandidateExamPayload = {
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
    };
    questions: Array<{
        id: string;
        subject_id: string | null;
        question_type: string;
        body: string;
        marks: number;
        display_order: number;
    }>;
    options: Array<{
        id: string;
        question_id: string;
        option_label: string;
        body: string;
        display_order: number;
    }>;
    saved_answers: Array<{
        question_id: string;
        option_ids: string[];
        text_answer: string | null;
        saved_at: string;
    }>;
    remaining_time_seconds: number;
};

export function ensureCandidatePaper(connection: Database, attemptToken: string): void {
    const context = getAttemptContext(connection, attemptToken);
    ensurePaperForAttempt(connection, context);
}

export function getCandidateExam(connection: Database, attemptToken: string): CandidateExamPayload {
    const context = getAttemptContext(connection, attemptToken);

    if (context.attempt_status === 'submitted' || context.attempt_status === 'auto_submitted') {
        throw new CandidateExamError('This exam has already been submitted.', 'already_submitted', 409);
    }

    if (context.attempt_status === 'disqualified') {
        throw new CandidateExamError('This candidate has been disqualified.', 'disqualified', 409);
    }

    ensurePaperForAttempt(connection, context);

    const paperRows = connection.prepare(`
        SELECT question_id, display_order, option_order_json
        FROM candidate_papers
        WHERE attempt_id = ?
        ORDER BY display_order ASC
    `).all(context.attempt_id) as PaperRow[];
    const questionIds = paperRows.map((row) => row.question_id);
    const questionMap = loadQuestionMap(connection, questionIds);
    const optionMap = loadOptionMap(connection, questionIds);

    const questions = paperRows
        .map((paperRow) => {
            const question = questionMap.get(paperRow.question_id);

            if (!question) {
                return null;
            }

            return stripQuestionAnswerInformation({
                id: question.id,
                subject_id: question.subject_id,
                question_type: question.question_type,
                body: question.body,
                marks: question.marks,
                display_order: paperRow.display_order,
            });
        })
        .filter((question): question is NonNullable<typeof question> => question !== null);

    const options = paperRows.flatMap((paperRow) => {
        const orderedOptionIds = parseOptionOrder(paperRow.option_order_json);
        const optionsForQuestion = optionMap.get(paperRow.question_id) ?? [];
        const byId = new Map(optionsForQuestion.map((option) => [option.id, option]));
        const sortedOptions = orderedOptionIds.length > 0
            ? orderedOptionIds.map((id) => byId.get(id)).filter((option): option is OptionRow => Boolean(option))
            : optionsForQuestion;

        return sortedOptions.map((option, index) => stripOptionAnswerInformation({
            id: option.id,
            question_id: option.question_id,
            option_label: option.option_label,
            body: option.body,
            display_order: index + 1,
        }));
    });

    return {
        candidate: {
            id: context.candidate_id,
            registration_number: context.candidate_no,
            full_name: context.full_name,
            group_name: context.group_name,
        },
        exam: {
            id: context.exam_id,
            exam_code: context.exam_code,
            title: context.title,
            organization_name: context.organization_name,
            duration_minutes: context.duration_minutes,
            total_questions: questions.length,
        },
        questions,
        options,
        saved_answers: loadSavedAnswers(connection, context.attempt_id),
        remaining_time_seconds: calculateRemainingSeconds(context),
    };
}

export function getAttemptTokenFromHeader(headerValue: string | undefined): string {
    if (!headerValue) {
        throw new CandidateExamError('Attempt token is required.', 'attempt_token_required', 401);
    }

    if (headerValue.toLowerCase().startsWith('bearer ')) {
        return headerValue.slice(7).trim();
    }

    return headerValue.trim();
}

function ensurePaperForAttempt(connection: Database, context: AttemptContext): void {
    const existing = connection.prepare('SELECT COUNT(*) AS count FROM candidate_papers WHERE attempt_id = ?').get(context.attempt_id) as { count: number };

    if (existing.count > 0) {
        return;
    }

    const { shuffle_questions, shuffle_options } = getShuffleSettings(context.raw_payload);
    const questions = connection.prepare(`
        SELECT id, subject_id, question_type, body, marks, display_order
        FROM questions
        WHERE exam_id = ?
        ORDER BY display_order ASC, id ASC
    `).all(context.exam_id) as QuestionRow[];
    const orderedQuestions = shuffle_questions ? shuffle(questions) : questions;
    const optionMap = loadOptionMap(connection, questions.map((question) => question.id));
    const now = new Date().toISOString();

    const insertPaper = connection.prepare(`
        INSERT INTO candidate_papers (
            id, exam_id, candidate_id, attempt_id, question_id,
            display_order, option_order_json, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    `);

    const transaction = connection.transaction(() => {
        orderedQuestions.forEach((question, questionIndex) => {
            const options = optionMap.get(question.id) ?? [];
            const orderedOptions = shuffle_options ? shuffle(options) : options;

            insertPaper.run(
                randomUUID(),
                context.exam_id,
                context.candidate_id,
                context.attempt_id,
                question.id,
                questionIndex + 1,
                JSON.stringify(orderedOptions.map((option) => option.id)),
                now,
                now,
            );
        });
    });

    transaction();
}

function getAttemptContext(connection: Database, attemptToken: string): AttemptContext {
    const context = connection.prepare(`
        SELECT
            candidate_attempts.id AS attempt_id,
            candidate_attempts.status AS attempt_status,
            candidate_attempts.attempt_token,
            candidate_attempts.started_at,
            candidates.id AS candidate_id,
            candidates.candidate_no,
            candidates.full_name,
            candidates.group_name,
            imported_exams.id AS exam_id,
            imported_exams.exam_code,
            imported_exams.title,
            imported_exams.organization_name,
            imported_exams.duration_minutes,
            imported_exams.actual_started_at,
            imported_exams.raw_payload
        FROM candidate_attempts
        INNER JOIN candidates ON candidates.id = candidate_attempts.candidate_id
        INNER JOIN imported_exams ON imported_exams.id = candidate_attempts.exam_id
        WHERE candidate_attempts.attempt_token = ?
        LIMIT 1
    `).get(attemptToken) as AttemptContext | undefined;

    if (!context) {
        throw new CandidateExamError('Invalid attempt token.', 'invalid_attempt_token', 401);
    }

    return context;
}

function loadQuestionMap(connection: Database, questionIds: string[]): Map<string, QuestionRow> {
    if (questionIds.length === 0) {
        return new Map();
    }

    const placeholders = questionIds.map(() => '?').join(', ');
    const rows = connection.prepare(`
        SELECT id, subject_id, question_type, body, marks, display_order
        FROM questions
        WHERE id IN (${placeholders})
    `).all(...questionIds) as QuestionRow[];

    return new Map(rows.map((row) => [row.id, row]));
}

function loadOptionMap(connection: Database, questionIds: string[]): Map<string, OptionRow[]> {
    if (questionIds.length === 0) {
        return new Map();
    }

    const placeholders = questionIds.map(() => '?').join(', ');
    const rows = connection.prepare(`
        SELECT id, question_id, option_label, body, display_order
        FROM question_options
        WHERE question_id IN (${placeholders})
        ORDER BY display_order ASC, id ASC
    `).all(...questionIds) as OptionRow[];
    const map = new Map<string, OptionRow[]>();

    for (const row of rows) {
        const current = map.get(row.question_id) ?? [];
        current.push(row);
        map.set(row.question_id, current);
    }

    return map;
}

function loadSavedAnswers(connection: Database, attemptId: string): CandidateExamPayload['saved_answers'] {
    const rows = connection.prepare(`
        SELECT question_id, option_ids, text_answer, saved_at
        FROM candidate_answers
        WHERE attempt_id = ?
        ORDER BY saved_at ASC
    `).all(attemptId) as Array<{ question_id: string; option_ids: string; text_answer: string | null; saved_at: string }>;

    return rows.map((row) => ({
        question_id: row.question_id,
        option_ids: parseOptionOrder(row.option_ids),
        text_answer: row.text_answer,
        saved_at: row.saved_at,
    }));
}

function getShuffleSettings(rawPayload: string | null): { shuffle_questions: boolean; shuffle_options: boolean } {
    if (!rawPayload) {
        return { shuffle_questions: false, shuffle_options: false };
    }

    try {
        const parsed = JSON.parse(rawPayload) as { manifest?: { shuffle_questions?: unknown; shuffle_options?: unknown } };

        return {
            shuffle_questions: parsed.manifest?.shuffle_questions === true,
            shuffle_options: parsed.manifest?.shuffle_options === true,
        };
    } catch {
        return { shuffle_questions: false, shuffle_options: false };
    }
}

function calculateRemainingSeconds(context: AttemptContext): number {
    const startedTime = context.started_at ? new Date(context.started_at).getTime() : Date.now();
    const endsAt = startedTime + context.duration_minutes * 60 * 1000;

    return Math.max(0, Math.floor((endsAt - Date.now()) / 1000));
}

function stripQuestionAnswerInformation<T extends Record<string, unknown>>(question: T): T {
    const clone = { ...question };
    delete clone.is_correct;
    delete clone.correct_answer;
    delete clone.answer_key;
    delete clone.scoring_rubric;
    return clone;
}

function stripOptionAnswerInformation<T extends Record<string, unknown>>(option: T): T {
    const clone = { ...option };
    delete clone.is_correct;
    delete clone.correct;
    delete clone.score;
    return clone;
}

function parseOptionOrder(value: string): string[] {
    try {
        const parsed = JSON.parse(value) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

function shuffle<T>(items: T[]): T[] {
    const copy = [...items];

    for (let index = copy.length - 1; index > 0; index -= 1) {
        const swapIndex = Math.floor(Math.random() * (index + 1));
        [copy[index], copy[swapIndex]] = [copy[swapIndex], copy[index]];
    }

    return copy;
}
