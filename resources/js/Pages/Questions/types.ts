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
    question_bank_course_name?: string | null;
    question_bank_module_name?: string | null;
    professional_school_id?: number | string | null;
    subject_id: string | null;
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
    subject_id: string | null;
    subject_name?: string | null;
    course_name?: string | null;
    module_name?: string | null;
    professional_school_id?: number | string | null;
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
