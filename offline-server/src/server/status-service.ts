import type { Server as SocketServer } from 'socket.io';
import type { CenterDatabase } from './database.js';
import { readLatestPlanFeatures, readLatestPlanSummary } from './plan-features.js';
import { detectLanIpAddress } from './network.js';
import type { CenterServerStatus, ServerInfo } from './types.js';

export type StatusServiceOptions = {
    database: CenterDatabase;
    port: number;
    startedAt: string;
};

export class StatusService {
    private readonly database: CenterDatabase;
    private readonly port: number;
    private readonly startedAt: string;
    private socketServer: SocketServer | null = null;

    constructor(options: StatusServiceOptions) {
        this.database = options.database;
        this.port = options.port;
        this.startedAt = options.startedAt;
    }

    attachSocketServer(socketServer: SocketServer): void {
        this.socketServer = socketServer;
    }

    getStatus(): CenterServerStatus {
        const localIpAddress = detectLanIpAddress();

        return {
            serverStatus: 'online',
            localIpAddress,
            candidateUrl: `http://${localIpAddress}:${this.port}/`,
            port: this.port,
            connectedCandidates: this.socketServer?.engine.clientsCount ?? 0,
            importedExams: this.countImportedExams(),
            activeCandidates: this.countAttemptsByStatus(['active']),
            submittedCandidates: this.countAttemptsByStatus(['submitted', 'auto_submitted']),
            database: {
                path: this.database.path,
                walEnabled: this.database.walEnabled,
            },
            plan: readLatestPlanSummary(this.database.connection),
            plan_features: readLatestPlanFeatures(this.database.connection),
            startedAt: this.startedAt,
        };
    }

    getServerInfo(): ServerInfo {
        return {
            databaseConnected: this.database.connection.open,
            databaseFilePath: this.database.path,
            importedExamsCount: this.countImportedExams(),
            activeExamCount: this.countImportedExamsByStatus(['active']),
            totalCandidatesCount: this.countCandidates(),
            plan: readLatestPlanSummary(this.database.connection),
            plan_features: readLatestPlanFeatures(this.database.connection),
        };
    }

    private countImportedExams(): number {
        const row = this.database.connection.prepare('SELECT COUNT(*) as count FROM imported_exams').get() as { count: number };
        return row.count;
    }

    private countImportedExamsByStatus(statuses: string[]): number {
        const placeholders = statuses.map(() => '?').join(', ');
        const row = this.database.connection
            .prepare(`SELECT COUNT(*) as count FROM imported_exams WHERE status IN (${placeholders})`)
            .get(...statuses) as { count: number };

        return row.count;
    }

    private countCandidates(): number {
        const row = this.database.connection.prepare('SELECT COUNT(*) as count FROM candidates').get() as { count: number };
        return row.count;
    }

    private countAttemptsByStatus(statuses: string[]): number {
        const placeholders = statuses.map(() => '?').join(', ');
        const row = this.database.connection
            .prepare(`SELECT COUNT(*) as count FROM candidate_attempts WHERE status IN (${placeholders})`)
            .get(...statuses) as { count: number };

        return row.count;
    }
}
