export type ResultRow = {
    attempt_id: string;
    exam_id: string;
    exam_title?: string;
    exam_code?: string;
    candidate_name: string;
    registration_number: string;
    score: number;
    total_marks: number;
    percentage: number;
    grade: string;
    passed: boolean;
    status: string;
    submitted_at?: string | null;
    duration_used: string;
    suspicious_event_count: number;
    result_hash: string;
};

export type ResultsDashboard = {
    summary: {
        total: number;
        passed: number;
        failed: number;
        average_percentage: number;
    };
    pass_fail: { name: string; value: number }[];
    score_distribution: { range: string; count: number }[];
    average_by_subject: { subject: string; average: number }[];
};
