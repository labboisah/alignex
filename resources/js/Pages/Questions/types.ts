export type OptionChoice = {
    id?: string;
    label: 'A' | 'B' | 'C' | 'D' | 'E';
    option_text: string;
    display_order?: number;
    is_correct: boolean;
};

export type Question = Record<string, unknown> & {
    id: string;
    question_bank_id: string;
    question_bank_name?: string | null;
    subject_id: string;
    subject_name?: string | null;
    topic_id?: string | null;
    topic_name?: string | null;
    question_type: string;
    stem: string;
    image_url?: string | null;
    explanation?: string | null;
    difficulty: 'easy' | 'medium' | 'hard';
    marks: string;
    status: 'draft' | 'review' | 'approved' | 'rejected' | 'archived';
    status_label: string;
    options?: OptionChoice[];
};

export type SelectOption = {
    value: string;
    label: string;
};

export type QuestionBankOption = {
    id: string;
    name: string;
    code: string;
    subject_id: string;
    subject_name?: string | null;
};

export type SubjectOption = {
    id: string;
    name: string;
    code: string;
};

export type TopicOption = {
    id: string;
    subject_id: string;
    name: string;
    code: string;
};
