import { createHash, randomUUID } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import type { Database } from 'better-sqlite3';

export type ResultExportSummary = {
    export_folder_path: string;
    json_filename: string;
    csv_filename: string;
    result_hash: string;
    exported_at: string;
};

type ExportExam = {
    id: string;
    package_id: string;
    exam_id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    exam_type: string;
    center_id: string;
    organization_id: string;
    starts_at: string | null;
    ends_at: string | null;
    actual_started_at: string | null;
    actual_closed_at: string | null;
    duration_minutes: number;
    status: string;
    imported_at: string | null;
    created_at: string;
    updated_at: string;
};

type CandidateRow = {
    id: string;
    exam_id: string;
    candidate_no: string;
    full_name: string;
    group_name: string | null;
    status: string;
    created_at: string;
    updated_at: string;
};

type AttemptRow = {
    id: string;
    exam_id: string;
    candidate_id: string;
    started_at: string | null;
    submitted_at: string | null;
    ip_address: string | null;
    device_fingerprint: string | null;
    status: string;
    score: number | null;
    total_questions: number;
    total_marks: number;
    answered_count: number;
};

type AnswerRow = {
    id: string;
    exam_id: string;
    candidate_id: string;
    attempt_id: string;
    question_id: string;
    option_ids: string;
    text_answer: string | null;
    is_correct: number | null;
    marks_awarded: number;
    saved_at: string;
    created_at: string;
    updated_at: string;
};

type EventRow = {
    id: string;
    exam_id: string;
    attempt_id: string | null;
    candidate_id: string | null;
    event_type: string;
    severity: string;
    message: string;
    metadata: string;
    occurred_at: string;
    created_at: string;
    updated_at: string;
};

export class ResultExportError extends Error {
    readonly statusCode: number;
    readonly code: string;

    constructor(message: string, code: string, statusCode = 400) {
        super(message);
        this.code = code;
        this.statusCode = statusCode;
    }
}

export function exportExamResults(connection: Database, examId: string, storagePath: string): ResultExportSummary {
    if (!examId.trim()) {
        throw new ResultExportError('Exam ID is required.', 'exam_id_required', 422);
    }

    const exam = connection.prepare(`
        SELECT
            id, package_id, exam_id, exam_code, title, organization_name, exam_type,
            center_id, organization_id, starts_at, ends_at, actual_started_at,
            actual_closed_at, duration_minutes, status, imported_at, created_at, updated_at
        FROM imported_exams
        WHERE id = ?
        LIMIT 1
    `).get(examId) as ExportExam | undefined;

    if (!exam) {
        throw new ResultExportError('Exam not found.', 'exam_not_found', 404);
    }

    if (exam.status !== 'closed') {
        throw new ResultExportError('Only closed exams can be exported.', 'exam_not_closed', 409);
    }

    const exportedAt = new Date().toISOString();
    const exportFolderPath = join(dirname(storagePath), 'exports', sanitizePathSegment(exam.exam_code));
    const jsonFilename = `${sanitizePathSegment(exam.exam_code)}-results.json`;
    const csvFilename = `${sanitizePathSegment(exam.exam_code)}-results.csv`;
    const jsonPath = join(exportFolderPath, jsonFilename);
    const csvPath = join(exportFolderPath, csvFilename);

    mkdirSync(exportFolderPath, { recursive: true });

    const candidates = loadCandidates(connection, exam.id);
    const attempts = loadAttempts(connection, exam.id);
    const answers = loadAnswers(connection, exam.id);
    const events = loadEvents(connection, exam.id);
    const payload = {
        exported_at: exportedAt,
        exam,
        candidates,
        attempts,
        answers: answers.map((answer) => ({
            ...answer,
            option_ids: parseStringArray(answer.option_ids),
            is_correct: answer.is_correct === null ? null : answer.is_correct === 1,
        })),
        scores: attempts.map((attempt) => ({
            attempt_id: attempt.id,
            candidate_id: attempt.candidate_id,
            status: attempt.status,
            answered_count: attempt.answered_count,
            score: attempt.score ?? 0,
            total_questions: attempt.total_questions,
            total_marks: attempt.total_marks,
            percentage: calculatePercentage(attempt.score ?? 0, attempt.total_marks),
            submitted_at: attempt.submitted_at,
        })),
        exam_events: events.map((event) => ({
            ...event,
            metadata: parseJsonObject(event.metadata),
        })),
    };
    const jsonContent = `${JSON.stringify(payload, null, 2)}\n`;
    const resultHash = createHash('sha256').update(jsonContent).digest('hex');
    const csvContent = buildCsv(candidates, attempts);

    writeFileSync(jsonPath, jsonContent, 'utf8');
    writeFileSync(csvPath, csvContent, 'utf8');

    const transaction = connection.transaction(() => {
        connection.prepare(`
            INSERT INTO export_logs (
                id, exam_id, exported_at, status, bundle_hash, file_path,
                message, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).run(
            randomUUID(),
            exam.id,
            exportedAt,
            'exported',
            resultHash,
            exportFolderPath,
            JSON.stringify({ json_filename: jsonFilename, csv_filename: csvFilename }),
            exportedAt,
            exportedAt,
        );

        connection.prepare(`
            UPDATE imported_exams
            SET status = 'exported',
                updated_at = ?
            WHERE id = ?
        `).run(exportedAt, exam.id);
    });

    transaction();

    return {
        export_folder_path: exportFolderPath,
        json_filename: jsonFilename,
        csv_filename: csvFilename,
        result_hash: resultHash,
        exported_at: exportedAt,
    };
}

function loadCandidates(connection: Database, examId: string): CandidateRow[] {
    return connection.prepare(`
        SELECT id, exam_id, candidate_no, full_name, group_name, status, created_at, updated_at
        FROM candidates
        WHERE exam_id = ?
        ORDER BY candidate_no ASC
    `).all(examId) as CandidateRow[];
}

function loadAttempts(connection: Database, examId: string): AttemptRow[] {
    return connection.prepare(`
        SELECT
            candidate_attempts.id,
            candidate_attempts.exam_id,
            candidate_attempts.candidate_id,
            candidate_attempts.started_at,
            candidate_attempts.submitted_at,
            candidate_attempts.ip_address,
            candidate_attempts.device_fingerprint,
            candidate_attempts.status,
            candidate_attempts.score,
            candidate_attempts.total_questions,
            candidate_attempts.total_marks,
            COUNT(DISTINCT candidate_answers.question_id) AS answered_count
        FROM candidate_attempts
        LEFT JOIN candidate_answers
            ON candidate_answers.attempt_id = candidate_attempts.id
            AND (
                candidate_answers.option_ids <> '[]'
                OR COALESCE(candidate_answers.text_answer, '') <> ''
            )
        WHERE candidate_attempts.exam_id = ?
        GROUP BY candidate_attempts.id
        ORDER BY candidate_attempts.started_at ASC
    `).all(examId) as AttemptRow[];
}

function loadAnswers(connection: Database, examId: string): AnswerRow[] {
    return connection.prepare(`
        SELECT
            id, exam_id, candidate_id, attempt_id, question_id, option_ids, text_answer,
            is_correct, marks_awarded, saved_at, created_at, updated_at
        FROM candidate_answers
        WHERE exam_id = ?
        ORDER BY candidate_id ASC, saved_at ASC
    `).all(examId) as AnswerRow[];
}

function loadEvents(connection: Database, examId: string): EventRow[] {
    return connection.prepare(`
        SELECT
            id, exam_id, attempt_id, candidate_id, event_type, severity,
            message, metadata, occurred_at, created_at, updated_at
        FROM exam_events
        WHERE exam_id = ?
        ORDER BY occurred_at ASC
    `).all(examId) as EventRow[];
}

function buildCsv(candidates: CandidateRow[], attempts: AttemptRow[]): string {
    const attemptByCandidate = new Map(attempts.map((attempt) => [attempt.candidate_id, attempt]));
    const rows = [
        [
            'candidate name',
            'registration number',
            'status',
            'answered count',
            'score',
            'total marks',
            'percentage',
            'submitted_at',
        ],
    ];

    for (const candidate of candidates) {
        const attempt = attemptByCandidate.get(candidate.id);
        const score = attempt?.score ?? 0;
        const totalMarks = attempt?.total_marks ?? 0;

        rows.push([
            candidate.full_name,
            candidate.candidate_no,
            attempt?.status ?? candidate.status,
            String(attempt?.answered_count ?? 0),
            String(score),
            String(totalMarks),
            String(calculatePercentage(score, totalMarks)),
            attempt?.submitted_at ?? '',
        ]);
    }

    return `${rows.map((row) => row.map(escapeCsvValue).join(',')).join('\n')}\n`;
}

function sanitizePathSegment(value: string): string {
    const sanitized = value.trim().replace(/[^a-zA-Z0-9._-]+/g, '-').replace(/^-+|-+$/g, '');
    return sanitized.length > 0 ? sanitized : 'exam-results';
}

function escapeCsvValue(value: string): string {
    if (/[",\r\n]/.test(value)) {
        return `"${value.replace(/"/g, '""')}"`;
    }

    return value;
}

function parseStringArray(value: string): string[] {
    try {
        const parsed = JSON.parse(value) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

function parseJsonObject(value: string): Record<string, unknown> {
    try {
        const parsed = JSON.parse(value) as unknown;
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed as Record<string, unknown> : {};
    } catch {
        return {};
    }
}

function calculatePercentage(score: number, totalMarks: number): number {
    if (totalMarks <= 0) {
        return 0;
    }

    return Number(((score / totalMarks) * 100).toFixed(2));
}
