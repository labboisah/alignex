import { useEffect, useState } from 'react';
import type { CenterServerStatus, ServerInfo } from '../types/status';

type ServerStatusState = {
    status: CenterServerStatus | null;
    serverInfo: ServerInfo | null;
    loading: boolean;
    error: string | null;
    refresh: () => Promise<void>;
};

const apiBaseUrl = 'http://127.0.0.1:4080';

export function useServerStatus(): ServerStatusState {
    const [status, setStatus] = useState<CenterServerStatus | null>(null);
    const [serverInfo, setServerInfo] = useState<ServerInfo | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    async function refresh(): Promise<void> {
        try {
            setError(null);
            const [healthResponse, serverInfoResponse] = await Promise.all([
                fetch(`${apiBaseUrl}/api/health`),
                fetch(`${apiBaseUrl}/api/server-info`),
            ]);

            if (!healthResponse.ok) {
                throw new Error(`Health endpoint returned ${healthResponse.status}`);
            }

            if (!serverInfoResponse.ok) {
                throw new Error(`Server info endpoint returned ${serverInfoResponse.status}`);
            }

            const healthData = await healthResponse.json() as CenterServerStatus;
            const serverInfoData = await serverInfoResponse.json() as ServerInfo;

            setStatus(healthData);
            setServerInfo(serverInfoData);
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : 'Database initialization failed or server API is not reachable.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        void refresh();
        const timer = window.setInterval(() => void refresh(), 5000);

        return () => window.clearInterval(timer);
    }, []);

    return { status, serverInfo, loading, error, refresh };
}
