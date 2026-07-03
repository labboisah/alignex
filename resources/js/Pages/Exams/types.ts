export type SelectOption = {
    value: string;
    label: string;
};

export type TenantOption = {
    id: number | string;
    name: string;
    code?: string;
    subject_id?: string | number | null;
    programme_id?: string | number | null;
    course_id?: string | number | null;
    module_id?: string | number | null;
    school_class_id?: string | number;
    is_active?: boolean;
    level?: string | null;
    school_class?: { id: string | number; name: string } | null;
};

export type CurrentContext = {
    type: 'organization' | 'secondary_school' | 'professional_school' | 'cbt_center';
    id: number | string;
    name: string;
    source?: 'legacy_school' | 'legacy_center' | string;
};

export type SubjectOption = {
    id: string;
    name: string;
    code: string;
};

export type ExamSubject = {
    subject_id: string;
    subject_name?: string | null;
    course_id?: string | number | null;
    module_id?: string | number | null;
    question_bank_id?: string | number | null;
    question_bank_ids?: Array<string | number>;
    question_bank_name?: string | null;
    number_of_questions: number | string;
    marks_per_question: number | string;
    duration_minutes?: number | string | null;
    difficulty_distribution?: Record<string, number> | null;
    total_marks?: string | number;
};

export type ExamSettings = {
    shuffle_questions: boolean;
    shuffle_options: boolean;
    show_result_immediately: boolean;
    allow_back_navigation: boolean;
    require_webcam: boolean;
    require_fullscreen: boolean;
    max_tab_switches: number | string;
    negative_marking: boolean;
    negative_mark_value: number | string;
    bind_device: boolean;
    allow_retake: boolean;
    certificate_auto_generate?: boolean;
    attempt_limit?: number | string;
    adaptive_start_difficulty?: string;
    adaptive_step_policy?: string;
};

export type Exam = Record<string, unknown> & {
    id: string;
    organization_id?: number | null;
    organization_name?: string | null;
    center_id?: number | null;
    center_name?: string | null;
    school_id?: number | null;
    school_name?: string | null;
    secondary_school_id?: number | null;
    professional_school_id?: number | null;
    cbt_center_id?: number | null;
    owner_context?: string | null;
    owner_context_label?: string | null;
    academic_session_id?: number | null;
    academic_term_id?: number | null;
    school_class_id?: string | number | null;
    student_group_id?: string | number | null;
    subject_id?: string | number | null;
    programme_id?: string | number | null;
    course_id?: string | number | null;
    module_id?: string | number | null;
    training_batch_id?: string | number | null;
    title: string;
    exam_code: string;
    exam_type: string;
    exam_category?: string | null;
    exam_type_label?: string | null;
    mode: string;
    exam_mode?: string | null;
    delivery_mode: string;
    question_bank_id?: string | null;
    question_bank_name?: string | null;
    candidate_group_id?: string | null;
    candidate_group_ids?: string[];
    candidate_ids?: string[];
    student_ids?: string[];
    participants_count?: number;
    attempts_count?: number;
    paper_generation_status?: string;
    submission_status?: string;
    results_summary?: { submitted: number; passed: number; failed: number };
    start_at: string;
    end_at: string;
    duration_minutes: number;
    total_marks: string;
    pass_mark: string;
    status: string;
    status_label: string;
    settings: ExamSettings;
    subjects?: ExamSubject[];
    subjects_count?: number;
};
