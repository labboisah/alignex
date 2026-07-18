import { io, type Socket } from 'socket.io-client';

export type CandidateSocket = Socket<ServerToCandidateEvents, CandidateToServerEvents>;

export type CandidateSocketStatus = 'connected' | 'reconnecting' | 'disconnected';

export type CandidateSocketPayload = {
    candidate_id?: string;
    exam_id?: string;
    attempt_token: string;
    timestamp?: string;
};

export type ServerControlPayload = {
    message?: string;
    reason?: string;
    action?: 'submit' | 'stop';
    [key: string]: unknown;
};

type ServerToCandidateEvents = {
    exam_closed: (payload?: ServerControlPayload) => void;
    force_submit: (payload?: ServerControlPayload) => void;
    candidate_disqualified: (payload?: ServerControlPayload) => void;
    server_message: (payload?: ServerControlPayload) => void;
};

type CandidateToServerEvents = {
    candidate_online: (payload: CandidateSocketPayload) => void;
    heartbeat: (payload: CandidateSocketPayload) => void;
};

export function createCandidateSocket(serverUrl: string, attemptToken: string): CandidateSocket {
    return io(serverUrl, {
        auth: {
            attempt_token: attemptToken,
            token: attemptToken,
        },
        reconnection: true,
        reconnectionAttempts: Infinity,
        reconnectionDelay: 1_000,
        reconnectionDelayMax: 5_000,
        transports: ['websocket', 'polling'],
    });
}
