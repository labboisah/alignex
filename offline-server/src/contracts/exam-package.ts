export type ExamPackage = {
    manifest: ExamManifest;
    subjects: Subject[];
    questions: Question[];
    options: QuestionOption[];
    candidates: Candidate[];
    papers: CandidatePaper[];
};

export type ExamManifest = {
    package_id: string;
    exam_id: string;
    exam_code: string;
    title: string;
    organization_name: string;
    center_id: string;
    start_at: string;
    end_at: string;
    duration_minutes: number;
    total_questions: number;
    candidate_count: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
};

export type Subject = {
    id: string;
    exam_id: string;
    name: string;
    code: string | null;
};

export type Question = {
    id: string;
    exam_id: string;
    subject_id: string;
    question_type: 'single_choice' | 'multiple_choice' | 'short_answer' | 'essay';
    body: string;
    marks: number;
    display_order: number;
};

export type QuestionOption = {
    id: string;
    question_id: string;
    option_label: string;
    body: string;
    display_order: number;
};

export type Candidate = {
    id: string;
    exam_id: string;
    candidate_no: string;
    full_name: string;
    access_code_hash: string;
    group_name: string | null;
};

export type CandidatePaper = {
    candidate_id: string;
    questions: CandidatePaperQuestion[];
};

export type CandidatePaperQuestion = {
    question_id: string;
    display_order: number;
    option_order: string[];
};
