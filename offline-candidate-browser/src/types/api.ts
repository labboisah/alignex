export type HealthResponse = {
    serverStatus?: string;
    status?: string;
    timestamp?: string;
    [key: string]: unknown;
};

export type CandidateLoginRequest = {
    exam_code?: string;
    registration_number: string;
    device_fingerprint?: string;
    device_info?: {
        platform: string;
        hostname: string | null;
        installationId: string;
    };
};

export type CandidateProfile = {
    id: string;
    full_name: string;
    registration_number: string;
    group_name: string | null;
};

export type CandidateLoginResponse = {
    attempt_token: string;
    candidate: CandidateProfile;
    exam: ExamDetails;
    remaining_time_seconds: number;
};

export type ExamDetails = {
    id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    duration_minutes: number;
    total_questions?: number;
    remaining_seconds?: number;
    started_at?: string | null;
    questions?: ExamQuestion[];
};

export type CandidateExamResponse = {
    candidate: CandidateProfile;
    exam: ExamDetails;
    questions: Array<Omit<ExamQuestion, 'options'>>;
    options: ExamOption[];
    saved_answers: CandidateSavedAnswer[];
    remaining_time_seconds: number;
};

export type ExamQuestion = {
    id: string;
    subject_id?: string | null;
    question_type: 'single_choice' | 'multiple_choice' | 'short_answer' | 'essay';
    body: string;
    marks: number;
    display_order: number;
    options: ExamOption[];
};

export type ExamOption = {
    id: string;
    question_id: string;
    option_label: string;
    body: string;
    display_order: number;
};

export type SavedAnswer = {
    question_id: string;
    selected_option_id: string | null;
    saved_at: string;
};

export type CandidateSavedAnswer = {
    question_id: string;
    option_ids: string[];
    text_answer: string | null;
    saved_at: string;
};

export type SaveAnswerRequest = {
    question_id: string;
    selected_option_id: string | null;
    time_spent_seconds: number;
};

export type SaveAnswerResponse = {
    success: boolean;
    answered_count: number;
    total_questions: number;
    saved_at: string;
};

export type SubmitExamResponse = {
    success: boolean;
    candidate?: CandidateProfile;
    exam?: Pick<ExamDetails, 'id' | 'exam_code' | 'title'>;
    answered_count: number;
    total_questions: number;
    submitted_at: string;
    status: 'submitted' | 'auto_submitted';
    score?: number;
    total_marks?: number;
};

export type SubmissionSummary = {
    candidate: CandidateProfile;
    exam: Pick<ExamDetails, 'id' | 'exam_code' | 'title'>;
    answered_count: number;
    total_questions: number;
    submitted_at: string;
    status: 'submitted' | 'auto_submitted';
    score?: number;
    total_marks?: number;
};

export type CandidateEventRequest = {
    event_type: string;
    severity?: 'info' | 'warning' | 'danger';
    message?: string;
    metadata?: Record<string, unknown>;
};
