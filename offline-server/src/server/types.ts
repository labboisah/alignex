export type CenterServerStatus = {
    serverStatus: 'starting' | 'online' | 'error';
    localIpAddress: string;
    candidateUrl: string;
    port: number;
    connectedCandidates: number;
    importedExams: number;
    activeCandidates: number;
    submittedCandidates: number;
    database: {
        path: string;
        walEnabled: boolean;
    };
    plan: {
        id: number | string | null;
        slug: string | null;
        name: string | null;
    };
    plan_features: Record<string, boolean>;
    startedAt: string;
};

export type ServerInfo = {
    databaseConnected: boolean;
    databaseFilePath: string;
    importedExamsCount: number;
    activeExamCount: number;
    totalCandidatesCount: number;
    plan: {
        id: number | string | null;
        slug: string | null;
        name: string | null;
    };
    plan_features: Record<string, boolean>;
};
