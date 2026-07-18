import { useMemo } from 'react';
export function useConnectionStatus() {
    return useMemo(() => ({
        status: 'idle',
        label: 'Not connected to center server',
    }), []);
}
