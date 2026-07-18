import { useEffect, useMemo, useRef } from 'react';
import { isMockApiEnabled } from '../services/apiClient';
import { createCandidateSocket, type CandidateSocket, type CandidateSocketPayload, type CandidateSocketStatus, type ServerControlPayload } from '../services/candidateSocket';

export function useCandidateSocket({
    serverUrl,
    attemptToken,
    candidateId,
    examId,
    enabled,
    heartbeatEnabled,
    onStatusChange,
    onExamClosed,
    onForceSubmit,
    onDisqualified,
    onServerMessage,
}: {
    serverUrl: string | null;
    attemptToken: string | null;
    candidateId?: string;
    examId?: string;
    enabled: boolean;
    heartbeatEnabled: boolean;
    onStatusChange: (status: CandidateSocketStatus) => void;
    onExamClosed: (payload?: ServerControlPayload) => void;
    onForceSubmit: (payload?: ServerControlPayload) => void;
    onDisqualified: (payload?: ServerControlPayload) => void;
    onServerMessage: (payload?: ServerControlPayload) => void;
}) {
    const socketRef = useRef<CandidateSocket | null>(null);
    const heartbeatRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const callbacksRef = useRef({
        onStatusChange,
        onExamClosed,
        onForceSubmit,
        onDisqualified,
        onServerMessage,
    });
    const payload = useMemo<CandidateSocketPayload | null>(() => {
        if (!attemptToken) {
            return null;
        }

        return {
            attempt_token: attemptToken,
            candidate_id: candidateId,
            exam_id: examId,
        };
    }, [attemptToken, candidateId, examId]);

    useEffect(() => {
        callbacksRef.current = {
            onStatusChange,
            onExamClosed,
            onForceSubmit,
            onDisqualified,
            onServerMessage,
        };
    }, [onDisqualified, onExamClosed, onForceSubmit, onServerMessage, onStatusChange]);

    useEffect(() => {
        if (!enabled || !serverUrl || !attemptToken || !payload) {
            return;
        }

        if (isMockApiEnabled()) {
            callbacksRef.current.onStatusChange('connected');

            if (import.meta.env.DEV) {
                console.info('[mock-socket] candidate_online', withTimestamp(payload));
            }

            function handleMockSocketEvent(event: Event) {
                const detail = (event as CustomEvent<{ type?: string; payload?: ServerControlPayload }>).detail;

                if (!detail?.type) {
                    return;
                }

                if (detail.type === 'exam_closed') callbacksRef.current.onExamClosed(detail.payload);
                if (detail.type === 'force_submit') callbacksRef.current.onForceSubmit(detail.payload);
                if (detail.type === 'candidate_disqualified') callbacksRef.current.onDisqualified(detail.payload);
                if (detail.type === 'server_message') callbacksRef.current.onServerMessage(detail.payload);
            }

            window.addEventListener('alignex:mock-socket-event', handleMockSocketEvent);

            if (heartbeatEnabled) {
                heartbeatRef.current = setInterval(() => {
                    if (import.meta.env.DEV) {
                        console.info('[mock-socket] heartbeat', withTimestamp(payload));
                    }
                }, 10_000);
            }

            return () => {
                window.removeEventListener('alignex:mock-socket-event', handleMockSocketEvent);

                if (heartbeatRef.current) {
                    clearInterval(heartbeatRef.current);
                    heartbeatRef.current = null;
                }
            };
        }

        const socket = createCandidateSocket(serverUrl, attemptToken);
        socketRef.current = socket;
        callbacksRef.current.onStatusChange('reconnecting');

        socket.on('connect', () => {
            callbacksRef.current.onStatusChange('connected');
            socket.emit('candidate_online', withTimestamp(payload));
        });

        socket.io.on('reconnect_attempt', () => callbacksRef.current.onStatusChange('reconnecting'));
        socket.io.on('reconnect', () => callbacksRef.current.onStatusChange('connected'));
        socket.io.on('reconnect_error', () => callbacksRef.current.onStatusChange('reconnecting'));
        socket.on('disconnect', () => callbacksRef.current.onStatusChange('disconnected'));
        socket.on('connect_error', () => callbacksRef.current.onStatusChange('disconnected'));

        socket.on('exam_closed', (eventPayload) => callbacksRef.current.onExamClosed(eventPayload));
        socket.on('force_submit', (eventPayload) => callbacksRef.current.onForceSubmit(eventPayload));
        socket.on('candidate_disqualified', (eventPayload) => callbacksRef.current.onDisqualified(eventPayload));
        socket.on('server_message', (eventPayload) => callbacksRef.current.onServerMessage(eventPayload));

        if (heartbeatEnabled) {
            heartbeatRef.current = setInterval(() => {
                if (socket.connected) {
                    socket.emit('heartbeat', withTimestamp(payload));
                }
            }, 10_000);
        }

        return () => {
            if (heartbeatRef.current) {
                clearInterval(heartbeatRef.current);
                heartbeatRef.current = null;
            }

            socket.removeAllListeners();
            socket.disconnect();
            socketRef.current = null;
        };
    }, [attemptToken, enabled, heartbeatEnabled, payload, serverUrl]);
}

function withTimestamp(payload: CandidateSocketPayload): CandidateSocketPayload {
    return {
        ...payload,
        timestamp: new Date().toISOString(),
    };
}
