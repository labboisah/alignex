import type { Database } from 'better-sqlite3';

export type ExamSummary = {
    id: string;
    title: string;
    exam_code: string;
    organization_name: string;
    status: 'ready' | 'active' | 'closed' | 'exported';
    start_at: string | null;
    end_at: string | null;
    actual_started_at: string | null;
    actual_closed_at: string | null;
    duration_minutes: number;
    candidate_count: number;
    question_count: number;
    active_candidates: number;
    submitted_candidates: number;
};

export type ExamCandidateRow = {
    id: string;
    candidate_no: string;
    full_name: string;
    group_name: string | null;
    status: string;
    attempt_status: string | null;
    started_at: string | null;
    submitted_at: string | null;
    ip_address: string | null;
    device_fingerprint: string | null;
    answered_count: number;
    total_questions: number;
};

export type ExamPaperVerification = {
    status: 'passed' | 'failed';
    candidate_count: number;
    attempt_count: number;
    candidates_with_papers: number;
    candidates_without_attempts: number;
    candidates_without_papers: number;
    question_count: number;
    min_questions_per_paper: number;
    max_questions_per_paper: number;
    duplicate_question_assignments: number;
    invalid_question_assignments: number;
    invalid_option_orders: number;
    issues: string[];
};

const examSummarySelect = `
    SELECT
        imported_exams.id,
        imported_exams.title,
        imported_exams.exam_code,
        imported_exams.organization_name,
        imported_exams.status,
        imported_exams.starts_at AS start_at,
        imported_exams.ends_at AS end_at,
        imported_exams.actual_started_at,
        imported_exams.actual_closed_at,
        imported_exams.duration_minutes,
        COUNT(DISTINCT candidates.id) AS candidate_count,
        COUNT(DISTINCT questions.id) AS question_count,
        COUNT(DISTINCT active_attempts.id) AS active_candidates,
        COUNT(DISTINCT submitted_attempts.id) AS submitted_candidates
    FROM imported_exams
    LEFT JOIN candidates ON candidates.exam_id = imported_exams.id
    LEFT JOIN questions ON questions.exam_id = imported_exams.id
    LEFT JOIN candidate_attempts AS active_attempts
        ON active_attempts.exam_id = imported_exams.id
        AND active_attempts.status = 'active'
    LEFT JOIN candidate_attempts AS submitted_attempts
        ON submitted_attempts.exam_id = imported_exams.id
        AND submitted_attempts.status IN ('submitted', 'auto_submitted')
`;

export function listExamSummaries(connection: Database): ExamSummary[] {
    return connection.prepare(`
        ${examSummarySelect}
        GROUP BY imported_exams.id
        ORDER BY imported_exams.imported_at DESC, imported_exams.created_at DESC
    `).all() as ExamSummary[];
}

export function getExamSummary(connection: Database, examId: string): ExamSummary | null {
    const exam = connection.prepare(`
        ${examSummarySelect}
        WHERE imported_exams.id = ?
        GROUP BY imported_exams.id
    `).get(examId) as ExamSummary | undefined;

    return exam ?? null;
}

export function listExamCandidates(connection: Database, examId: string): ExamCandidateRow[] {
    return connection.prepare(`
        SELECT
            candidates.id,
            candidates.candidate_no,
            candidates.full_name,
            candidates.group_name,
            candidates.status,
            candidate_attempts.status AS attempt_status,
            candidate_attempts.started_at,
            candidate_attempts.submitted_at,
            candidate_attempts.ip_address,
            candidate_attempts.device_fingerprint,
            COUNT(DISTINCT CASE
                WHEN candidate_answers.option_ids <> '[]'
                    OR COALESCE(candidate_answers.text_answer, '') <> ''
                THEN candidate_answers.question_id
            END) AS answered_count,
            COUNT(DISTINCT candidate_papers.question_id) AS total_questions
        FROM candidates
        LEFT JOIN candidate_attempts
            ON candidate_attempts.exam_id = candidates.exam_id
            AND candidate_attempts.candidate_id = candidates.id
        LEFT JOIN candidate_papers
            ON candidate_papers.attempt_id = candidate_attempts.id
        LEFT JOIN candidate_answers
            ON candidate_answers.attempt_id = candidate_attempts.id
        WHERE candidates.exam_id = ?
        GROUP BY candidates.id, candidate_attempts.id
        ORDER BY candidates.full_name ASC, candidates.candidate_no ASC
    `).all(examId) as ExamCandidateRow[];
}

export function verifyExamPapers(connection: Database, examId: string): ExamPaperVerification {
    const candidateCount = count(connection, 'SELECT COUNT(*) AS count FROM candidates WHERE exam_id = ?', examId);
    const attemptCount = count(connection, 'SELECT COUNT(*) AS count FROM candidate_attempts WHERE exam_id = ?', examId);
    const questionCount = count(connection, 'SELECT COUNT(*) AS count FROM questions WHERE exam_id = ?', examId);
    const candidatesWithPapers = count(connection, `
        SELECT COUNT(DISTINCT candidates.id) AS count
        FROM candidates
        INNER JOIN candidate_papers
            ON candidate_papers.exam_id = candidates.exam_id
            AND candidate_papers.candidate_id = candidates.id
        WHERE candidates.exam_id = ?
    `, examId);
    const candidatesWithoutAttempts = count(connection, `
        SELECT COUNT(*) AS count
        FROM candidates
        LEFT JOIN candidate_attempts
            ON candidate_attempts.exam_id = candidates.exam_id
            AND candidate_attempts.candidate_id = candidates.id
        WHERE candidates.exam_id = ?
          AND candidate_attempts.id IS NULL
    `, examId);
    const candidatesWithoutPapers = count(connection, `
        SELECT COUNT(*) AS count
        FROM candidates
        LEFT JOIN candidate_papers
            ON candidate_papers.exam_id = candidates.exam_id
            AND candidate_papers.candidate_id = candidates.id
        WHERE candidates.exam_id = ?
          AND candidate_papers.id IS NULL
    `, examId);
    const duplicateQuestionAssignments = count(connection, `
        SELECT COUNT(*) AS count
        FROM (
            SELECT attempt_id, question_id, COUNT(*) AS total
            FROM candidate_papers
            WHERE exam_id = ?
            GROUP BY attempt_id, question_id
            HAVING total > 1
        ) duplicates
    `, examId);
    const invalidQuestionAssignments = count(connection, `
        SELECT COUNT(*) AS count
        FROM candidate_papers
        LEFT JOIN questions ON questions.id = candidate_papers.question_id
        WHERE candidate_papers.exam_id = ?
          AND questions.id IS NULL
    `, examId);
    const paperStats = connection.prepare(`
        SELECT
            COALESCE(MIN(total), 0) AS min_questions_per_paper,
            COALESCE(MAX(total), 0) AS max_questions_per_paper
        FROM (
            SELECT candidate_attempts.id, COUNT(candidate_papers.id) AS total
            FROM candidate_attempts
            LEFT JOIN candidate_papers ON candidate_papers.attempt_id = candidate_attempts.id
            WHERE candidate_attempts.exam_id = ?
            GROUP BY candidate_attempts.id
        ) papers
    `).get(examId) as { min_questions_per_paper: number; max_questions_per_paper: number } | undefined;
    const invalidOptionOrders = countInvalidOptionOrders(connection, examId);
    const issues: string[] = [];

    if (candidateCount === 0) issues.push('No candidates were imported.');
    if (questionCount === 0) issues.push('No questions were imported.');
    if (attemptCount !== candidateCount) issues.push('Each candidate must have exactly one attempt record.');
    if (candidatesWithoutAttempts > 0) issues.push(`${candidatesWithoutAttempts} candidate(s) do not have an attempt record.`);
    if (candidatesWithoutPapers > 0) issues.push(`${candidatesWithoutPapers} candidate(s) do not have a paper.`);
    if ((paperStats?.min_questions_per_paper ?? 0) === 0) issues.push('One or more candidate papers has no questions.');
    if ((paperStats?.min_questions_per_paper ?? 0) !== (paperStats?.max_questions_per_paper ?? 0)) {
        issues.push('Candidate papers do not all have the same question count.');
    }
    if (duplicateQuestionAssignments > 0) issues.push(`${duplicateQuestionAssignments} duplicate question assignment(s) were found.`);
    if (invalidQuestionAssignments > 0) issues.push(`${invalidQuestionAssignments} paper question assignment(s) reference missing questions.`);
    if (invalidOptionOrders > 0) issues.push(`${invalidOptionOrders} option order value(s) reference missing options.`);

    return {
        status: issues.length === 0 ? 'passed' : 'failed',
        candidate_count: candidateCount,
        attempt_count: attemptCount,
        candidates_with_papers: candidatesWithPapers,
        candidates_without_attempts: candidatesWithoutAttempts,
        candidates_without_papers: candidatesWithoutPapers,
        question_count: questionCount,
        min_questions_per_paper: paperStats?.min_questions_per_paper ?? 0,
        max_questions_per_paper: paperStats?.max_questions_per_paper ?? 0,
        duplicate_question_assignments: duplicateQuestionAssignments,
        invalid_question_assignments: invalidQuestionAssignments,
        invalid_option_orders: invalidOptionOrders,
        issues,
    };
}

function count(connection: Database, sql: string, ...bindings: unknown[]): number {
    const row = connection.prepare(sql).get(...bindings) as { count: number } | undefined;
    return row?.count ?? 0;
}

function countInvalidOptionOrders(connection: Database, examId: string): number {
    const rows = connection.prepare(`
        SELECT candidate_papers.option_order_json, candidate_papers.question_id
        FROM candidate_papers
        WHERE candidate_papers.exam_id = ?
    `).all(examId) as Array<{ option_order_json: string; question_id: string }>;
    let invalidCount = 0;

    for (const row of rows) {
        const optionIds = parseJsonStringArray(row.option_order_json);
        const validOptionIds = new Set(
            (connection.prepare('SELECT id FROM question_options WHERE question_id = ?').all(row.question_id) as Array<{ id: string }>)
                .map((option) => option.id),
        );

        invalidCount += optionIds.filter((optionId) => !validOptionIds.has(optionId)).length;
    }

    return invalidCount;
}

function parseJsonStringArray(value: string): string[] {
    try {
        const parsed = JSON.parse(value) as unknown;
        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
    } catch {
        return [];
    }
}
