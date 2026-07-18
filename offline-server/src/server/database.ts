import type DatabaseConstructor from 'better-sqlite3';
import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';

const require = createRequire(import.meta.url);
const Database = require('better-sqlite3') as typeof DatabaseConstructor;
type SqliteDatabase = InstanceType<typeof DatabaseConstructor>;

const schema = `
CREATE TABLE IF NOT EXISTS app_settings (
    id TEXT PRIMARY KEY,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS local_users (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    password_salt TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('admin', 'operator')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
    last_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS license_activations (
    id TEXT PRIMARY KEY,
    device_id TEXT NOT NULL,
    license_key TEXT NOT NULL,
    organization_name TEXT NOT NULL,
    center_name TEXT NOT NULL,
    center_id TEXT,
    status TEXT NOT NULL DEFAULT 'activated' CHECK (status IN ('activated', 'expired', 'revoked', 'maintenance')),
    activated_at TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    raw_payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS imported_exams (
    id TEXT PRIMARY KEY,
    package_id TEXT NOT NULL UNIQUE,
    exam_id TEXT NOT NULL,
    exam_code TEXT NOT NULL,
    title TEXT NOT NULL,
    organization_name TEXT NOT NULL,
    exam_type TEXT NOT NULL DEFAULT 'exam',
    center_id TEXT NOT NULL,
    organization_id TEXT NOT NULL,
    imported_at TEXT,
    starts_at TEXT,
    ends_at TEXT,
    actual_started_at TEXT,
    actual_closed_at TEXT,
    duration_minutes INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'ready' CHECK (status IN ('ready', 'active', 'closed', 'exported')),
    raw_payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS exam_sessions (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    session_name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'ready',
    opened_at TEXT,
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id)
);

CREATE TABLE IF NOT EXISTS subjects (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    name TEXT NOT NULL,
    code TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id)
);

CREATE TABLE IF NOT EXISTS questions (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    subject_id TEXT,
    question_type TEXT NOT NULL,
    body TEXT NOT NULL,
    marks REAL NOT NULL DEFAULT 1,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
);

CREATE TABLE IF NOT EXISTS question_options (
    id TEXT PRIMARY KEY,
    question_id TEXT NOT NULL,
    option_label TEXT NOT NULL,
    body TEXT NOT NULL,
    is_correct INTEGER NOT NULL DEFAULT 0,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE IF NOT EXISTS candidates (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    candidate_no TEXT NOT NULL,
    full_name TEXT NOT NULL,
    access_code_hash TEXT NOT NULL,
    group_name TEXT,
    status TEXT NOT NULL DEFAULT 'not_started',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(exam_id, candidate_no),
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id)
);

CREATE TABLE IF NOT EXISTS candidate_attempts (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    candidate_id TEXT NOT NULL,
    started_at TEXT,
    submitted_at TEXT,
    ip_address TEXT,
    device_fingerprint TEXT,
    attempt_token TEXT,
    status TEXT NOT NULL DEFAULT 'not_started' CHECK (status IN ('not_started', 'active', 'submitted', 'auto_submitted', 'disqualified')),
    score REAL,
    total_questions INTEGER NOT NULL DEFAULT 0,
    total_marks REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)
);

CREATE TABLE IF NOT EXISTS candidate_papers (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    candidate_id TEXT NOT NULL,
    attempt_id TEXT NOT NULL,
    question_id TEXT NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    option_order_json TEXT NOT NULL DEFAULT '[]',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(attempt_id, question_id),
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (attempt_id) REFERENCES candidate_attempts(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE IF NOT EXISTS candidate_answers (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    candidate_id TEXT NOT NULL,
    attempt_id TEXT NOT NULL,
    question_id TEXT NOT NULL,
    option_ids TEXT NOT NULL DEFAULT '[]',
    text_answer TEXT,
    is_correct INTEGER,
    marks_awarded REAL NOT NULL DEFAULT 0,
    saved_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(attempt_id, question_id),
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (attempt_id) REFERENCES candidate_attempts(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE IF NOT EXISTS exam_events (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    attempt_id TEXT,
    candidate_id TEXT,
    event_type TEXT NOT NULL,
    severity TEXT NOT NULL,
    message TEXT NOT NULL,
    metadata TEXT NOT NULL DEFAULT '{}',
    occurred_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id),
    FOREIGN KEY (attempt_id) REFERENCES candidate_attempts(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)
);

CREATE TABLE IF NOT EXISTS export_logs (
    id TEXT PRIMARY KEY,
    exam_id TEXT NOT NULL,
    exported_at TEXT NOT NULL,
    status TEXT NOT NULL,
    bundle_hash TEXT,
    file_path TEXT,
    message TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES imported_exams(id)
);

CREATE INDEX IF NOT EXISTS idx_imported_exams_exam_id ON imported_exams(exam_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_imported_exams_package_id ON imported_exams(package_id);
CREATE INDEX IF NOT EXISTS idx_exam_sessions_exam_id ON exam_sessions(exam_id);
CREATE INDEX IF NOT EXISTS idx_subjects_exam_id ON subjects(exam_id);
CREATE INDEX IF NOT EXISTS idx_questions_exam_id ON questions(exam_id);
CREATE INDEX IF NOT EXISTS idx_question_options_question_id ON question_options(question_id);
CREATE INDEX IF NOT EXISTS idx_candidates_exam_id ON candidates(exam_id);
CREATE INDEX IF NOT EXISTS idx_candidate_attempts_exam_id ON candidate_attempts(exam_id);
CREATE INDEX IF NOT EXISTS idx_candidate_attempts_candidate_id ON candidate_attempts(candidate_id);
CREATE INDEX IF NOT EXISTS idx_candidate_papers_exam_id ON candidate_papers(exam_id);
CREATE INDEX IF NOT EXISTS idx_candidate_papers_candidate_id ON candidate_papers(candidate_id);
CREATE INDEX IF NOT EXISTS idx_candidate_papers_attempt_id ON candidate_papers(attempt_id);
CREATE INDEX IF NOT EXISTS idx_candidate_papers_question_id ON candidate_papers(question_id);
CREATE INDEX IF NOT EXISTS idx_candidate_answers_exam_id ON candidate_answers(exam_id);
CREATE INDEX IF NOT EXISTS idx_candidate_answers_candidate_id ON candidate_answers(candidate_id);
CREATE INDEX IF NOT EXISTS idx_candidate_answers_attempt_id ON candidate_answers(attempt_id);
CREATE INDEX IF NOT EXISTS idx_candidate_answers_question_id ON candidate_answers(question_id);
CREATE INDEX IF NOT EXISTS idx_exam_events_exam_id ON exam_events(exam_id);
CREATE INDEX IF NOT EXISTS idx_exam_events_candidate_id ON exam_events(candidate_id);
CREATE INDEX IF NOT EXISTS idx_exam_events_attempt_id ON exam_events(attempt_id);
CREATE INDEX IF NOT EXISTS idx_export_logs_exam_id ON export_logs(exam_id);
CREATE INDEX IF NOT EXISTS idx_local_users_email ON local_users(email);
CREATE INDEX IF NOT EXISTS idx_license_activations_device_id ON license_activations(device_id);
CREATE INDEX IF NOT EXISTS idx_license_activations_status ON license_activations(status);
`;

export type CenterDatabase = {
    connection: SqliteDatabase;
    path: string;
    walEnabled: boolean;
};

export function createDatabase(storagePath: string): CenterDatabase {
    const resolvedPath = resolve(storagePath);
    mkdirSync(dirname(resolvedPath), { recursive: true });

    const connection = new Database(resolvedPath);
    const journalMode = connection.pragma('journal_mode = WAL', { simple: true });
    connection.pragma('foreign_keys = ON');
    connection.exec(schema);
    ensureImportedExamColumns(connection);
    ensureActivationTables(connection);

    return {
        connection,
        path: resolvedPath,
        walEnabled: String(journalMode).toLowerCase() === 'wal',
    };
}

function ensureActivationTables(connection: SqliteDatabase): void {
    connection.exec(`
        CREATE TABLE IF NOT EXISTS local_users (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            password_salt TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('admin', 'operator')),
            status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'disabled')),
            last_login_at TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS license_activations (
            id TEXT PRIMARY KEY,
            device_id TEXT NOT NULL,
            license_key TEXT NOT NULL,
            organization_name TEXT NOT NULL,
            center_name TEXT NOT NULL,
            center_id TEXT,
            status TEXT NOT NULL DEFAULT 'activated' CHECK (status IN ('activated', 'expired', 'revoked', 'maintenance')),
            activated_at TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            raw_payload TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE INDEX IF NOT EXISTS idx_local_users_email ON local_users(email);
        CREATE INDEX IF NOT EXISTS idx_license_activations_device_id ON license_activations(device_id);
        CREATE INDEX IF NOT EXISTS idx_license_activations_status ON license_activations(status);
    `);
}

function ensureImportedExamColumns(connection: SqliteDatabase): void {
    ensureColumns(connection, 'imported_exams', [
        { name: 'package_id', sql: 'ALTER TABLE imported_exams ADD COLUMN package_id TEXT' },
        { name: 'exam_code', sql: "ALTER TABLE imported_exams ADD COLUMN exam_code TEXT NOT NULL DEFAULT ''" },
        { name: 'organization_name', sql: "ALTER TABLE imported_exams ADD COLUMN organization_name TEXT NOT NULL DEFAULT ''" },
        { name: 'actual_started_at', sql: 'ALTER TABLE imported_exams ADD COLUMN actual_started_at TEXT' },
        { name: 'actual_closed_at', sql: 'ALTER TABLE imported_exams ADD COLUMN actual_closed_at TEXT' },
    ]);

    connection.exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_imported_exams_package_id ON imported_exams(package_id)');

    ensureColumns(connection, 'candidate_attempts', [
        { name: 'ip_address', sql: 'ALTER TABLE candidate_attempts ADD COLUMN ip_address TEXT' },
        { name: 'device_fingerprint', sql: 'ALTER TABLE candidate_attempts ADD COLUMN device_fingerprint TEXT' },
        { name: 'attempt_token', sql: 'ALTER TABLE candidate_attempts ADD COLUMN attempt_token TEXT' },
        { name: 'total_questions', sql: 'ALTER TABLE candidate_attempts ADD COLUMN total_questions INTEGER NOT NULL DEFAULT 0' },
        { name: 'total_marks', sql: 'ALTER TABLE candidate_attempts ADD COLUMN total_marks REAL NOT NULL DEFAULT 0' },
    ]);

    connection.exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_candidate_attempts_attempt_token ON candidate_attempts(attempt_token)');

    ensureColumns(connection, 'candidate_papers', [
        { name: 'option_order_json', sql: "ALTER TABLE candidate_papers ADD COLUMN option_order_json TEXT NOT NULL DEFAULT '[]'" },
    ]);

    ensureColumns(connection, 'question_options', [
        { name: 'is_correct', sql: 'ALTER TABLE question_options ADD COLUMN is_correct INTEGER NOT NULL DEFAULT 0' },
    ]);

    ensureColumns(connection, 'candidate_answers', [
        { name: 'is_correct', sql: 'ALTER TABLE candidate_answers ADD COLUMN is_correct INTEGER' },
        { name: 'marks_awarded', sql: 'ALTER TABLE candidate_answers ADD COLUMN marks_awarded REAL NOT NULL DEFAULT 0' },
    ]);
}

function ensureColumns(connection: SqliteDatabase, table: string, columns: Array<{ name: string; sql: string }>): void {
    const existingColumns = connection.prepare(`PRAGMA table_info(${table})`).all() as Array<{ name: string }>;
    const names = new Set(existingColumns.map((column) => column.name));

    for (const column of columns) {
        if (!names.has(column.name)) {
            connection.exec(column.sql);
        }
    }
}
