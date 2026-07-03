export type ContextTerminology = {
    learnerSingular: string;
    learnerPlural: string;
    questionStructure: string;
    examLabel: string;
    resultDocument: string;
};

const terminology: Record<string, ContextTerminology> = {
    organization: {
        learnerSingular: 'Candidate',
        learnerPlural: 'Candidates',
        questionStructure: 'Question Bank',
        examLabel: 'Organization Exam',
        resultDocument: 'Result / Certificate',
    },
    secondary_school: {
        learnerSingular: 'Student',
        learnerPlural: 'Students',
        questionStructure: 'Subject / Topic / Question Bank',
        examLabel: 'Terminal Exam',
        resultDocument: 'Report Card',
    },
    professional_school: {
        learnerSingular: 'Candidate',
        learnerPlural: 'Candidates / Trainees',
        questionStructure: 'Programme / Course / Module / Question Bank',
        examLabel: 'Professional Exam',
        resultDocument: 'Certificate / Result Statement',
    },
    cbt_center: {
        learnerSingular: 'Candidate',
        learnerPlural: 'Candidates',
        questionStructure: 'Question Bank',
        examLabel: 'CBT Exam',
        resultDocument: 'Result',
    },
};

export function getContextTerminology(contextType = 'organization'): ContextTerminology {
    return terminology[contextType] ?? terminology.organization;
}
