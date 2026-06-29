export type SelectOption = {
    value: string;
    label: string;
};

export type TenantOption = {
    id: number;
    name: string;
    code: string;
};

export type SubjectOption = {
    id: string;
    name: string;
    code: string;
};

export type ExamSubject = {
    subject_id: string;
    subject_name?: string | null;
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
};

export type Exam = Record<string, unknown> & {
    id: string;
    organization_id?: number | null;
    organization_name?: string | null;
    center_id?: number | null;
    center_name?: string | null;
    school_id?: number | null;
    school_name?: string | null;
    title: string;
    exam_code: string;
    exam_type: string;
    exam_type_label?: string | null;
    mode: string;
    delivery_mode: string;
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
