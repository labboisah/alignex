import { useMemo } from 'react';

export function useConnectionStatus() {
    return useMemo(
        () => ({
            status: 'idle' as const,
            label: 'Not connected to center server',
        }),
        [],
    );
}
