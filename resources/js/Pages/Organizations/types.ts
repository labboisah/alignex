export type Organization = Record<string, unknown> & {
    id: number;
    name: string;
    code: string;
    organization_type?: string | null;
    organization_type_label?: string | null;
    description?: string | null;
    logo?: string | null;
    website?: string | null;
    contact_person: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    status: 'active' | 'inactive';
    status_label: string;
    exams_count?: number;
    candidates_count?: number;
    question_banks_count?: number;
    secondary_schools_count?: number;
    professional_schools_count?: number;
    cbt_centers_count?: number;
    recent_exams?: Array<{ id: string; title: string; code: string; category?: string | null; mode?: string | null; status: string }>;
    recent_results?: Array<{ id: string; candidate_name: string; exam_title?: string | null; score?: number | string | null; submitted_at?: string | null }>;
};

export type StatusOption = {
    value: string;
    label: string;
};
