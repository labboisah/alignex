export type CbtCenter = Record<string, unknown> & {
    id: number;
    organization_id?: number | null;
    organization_name?: string | null;
    name: string;
    code: string;
    location: string;
    capacity: number;
    contact_person: string;
    email: string;
    phone?: string | null;
    status: 'active' | 'inactive';
    status_label: string;
    candidates_count?: number | null;
    question_banks_count?: number | null;
    exams_count?: number | null;
};

export type CandidateRow = {
    id: string;
    registration_number: string;
    full_name: string;
    email?: string | null;
    phone?: string | null;
    nin?: string | null;
    status: string;
};

export type QuestionBankRow = {
    id: string;
    name: string;
    code: string;
    description?: string | null;
    status: string;
    questions_count?: number;
};

export type ExamRow = {
    id: string;
    title: string;
    code: string;
    category?: string | null;
    mode?: string | null;
    status: string;
};

export type OptionRow = {
    id: number | string;
    name: string;
    code?: string | null;
};
