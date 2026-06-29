export type Candidate = {
    id: string;
    organization_id?: number | null;
    organization_name?: string | null;
    school_id?: number | null;
    school_name?: string | null;
    center_id?: number | null;
    center_name?: string | null;
    first_name: string;
    last_name: string;
    full_name: string;
    registration_number: string;
    email?: string | null;
    phone?: string | null;
    date_of_birth?: string | null;
    photo_url?: string | null;
    status: string;
    status_label: string;
    assigned_exams_count?: number;
    assigned_exams?: AssignedExam[];
};

export type AssignedExam = {
    id: string;
    title: string;
    exam_code: string;
    status?: string;
};

export type ExamOption = {
    id: string;
    title: string;
    exam_code: string;
    status: string;
    status_label: string;
};

export type ScopeOption = {
    id: number;
    name: string;
    code?: string;
};

export type StatusOption = {
    value: string;
    label: string;
};

export type ImportReport = {
    successful: { row: number; registration_number: string; name: string }[];
    failed: { row: number; registration_number: string; reason: string }[];
    duplicates: { row: number; registration_number: string; reason: string }[];
    error_report_url?: string | null;
};
