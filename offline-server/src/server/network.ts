import { networkInterfaces } from 'node:os';

export function detectLanIpAddress(): string {
    const interfaces = networkInterfaces();

    for (const entries of Object.values(interfaces)) {
        for (const entry of entries ?? []) {
            if (entry.family === 'IPv4' && !entry.internal) {
                return entry.address;
            }
        }
    }

    return '127.0.0.1';
}
