export type OfflinePackage = {
    schemaVersion: '2026-07-10';
    packageId: string;
    generatedAt: string;
    expiresAt: string;
    organization: OfflineOrganization;
    center: OfflineCenter;
    exam: OfflineExam;
    candidates: OfflineCandidate[];
    papers: OfflineCandidatePaper[];
    signature: string;
};

export type OfflineOrganization = {
    id: string;
    name: string;
    type: 'secondary_school' | 'professional_school' | 'cbt_center' | 'organization';
};

export type OfflineCenter = {
    id: string;
    name: string;
};

export type OfflineExam = {
    id: string;
    title: string;
    mode: 'exam' | 'assessment';
    durationMinutes: number;
    startsAt: string | null;
    endsAt: string | null;
    instructions: string | null;
    security: {
        requireFullscreen: boolean;
        trackFocusLoss: boolean;
        allowCalculator: boolean;
        shuffleQuestions: boolean;
        shuffleOptions: boolean;
    };
};

export type OfflineCandidate = {
    id: string;
    candidateNo: string;
    fullName: string;
    accessCodeHash: string;
    groupName: string | null;
};

export type OfflineCandidatePaper = {
    candidateId: string;
    questions: OfflineQuestion[];
};

export type OfflineQuestion = {
    id: string;
    subjectId: string | null;
    courseId: string | null;
    moduleId: string | null;
    topic: string | null;
    type: 'single_choice' | 'multiple_choice' | 'short_answer' | 'essay';
    body: string;
    options: OfflineQuestionOption[];
    marks: number;
    order: number;
};

export type OfflineQuestionOption = {
    id: string;
    label: string;
    body: string;
    order: number;
};
