export type StatusOption = {
    value: string;
    label: string;
};

export type SubjectOption = {
    id: string;
    name: string;
    code: string;
};

export type Topic = Record<string, unknown> & {
    id: string;
    subject_id: string;
    subject_name?: string | null;
    subject_code?: string | null;
    parent_id?: string | null;
    parent_name?: string | null;
    name: string;
    code: string;
    description?: string | null;
    status: 'active' | 'inactive';
    status_label: string;
};
