export type StatusOption = {
    value: string;
    label: string;
};

export type ScopeOption = {
    id: number;
    name: string;
    code: string;
};

export type SubjectOption = {
    id: string;
    name: string;
    code: string;
};

export type QuestionBank = Record<string, unknown> & {
    id: string;
    organization_id?: number | null;
    organization_name?: string | null;
    school_id?: number | null;
    school_name?: string | null;
    center_id?: number | null;
    center_name?: string | null;
    subject_id: string;
    subject_name?: string | null;
    subject_code?: string | null;
    name: string;
    code: string;
    description?: string | null;
    status: 'draft' | 'active' | 'archived';
    status_label: string;
    questions_count?: number;
};
