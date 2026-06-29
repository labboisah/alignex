export type StatusOption = {
    value: string;
    label: string;
};

export type ScopeOption = {
    id: number;
    name: string;
    code: string;
};

export type Subject = Record<string, unknown> & {
    id: string;
    organization_id?: number | null;
    organization_name?: string | null;
    school_id?: number | null;
    school_name?: string | null;
    center_id?: number | null;
    center_name?: string | null;
    name: string;
    code: string;
    description?: string | null;
    status: 'active' | 'inactive';
    status_label: string;
    topics_count?: number;
    question_banks_count?: number;
};
