import { createHash, randomUUID } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { hostname, platform } from 'node:os';
import { dirname, join } from 'node:path';

export type SafeDeviceInfo = {
    platform: string;
    hostname: string | null;
    installationId: string;
};

export type DeviceFingerprintResult = {
    fingerprint: string;
    device: SafeDeviceInfo;
};

export function getSafeDeviceInfo(userDataPath: string): SafeDeviceInfo {
    return {
        platform: platform(),
        hostname: safeHostname(),
        installationId: getOrCreateInstallationId(userDataPath),
    };
}

export function getDeviceFingerprint(userDataPath: string): DeviceFingerprintResult {
    const device = getSafeDeviceInfo(userDataPath);
    const source = JSON.stringify({
        platform: device.platform,
        hostname: device.hostname,
        installationId: device.installationId,
    });

    return {
        fingerprint: createHash('sha256').update(source).digest('hex'),
        device,
    };
}

function getOrCreateInstallationId(userDataPath: string): string {
    const filePath = join(userDataPath, 'installation-id');

    if (existsSync(filePath)) {
        const existing = readFileSync(filePath, 'utf8').trim();

        if (existing) {
            return existing;
        }
    }

    const installationId = randomUUID();
    mkdirSync(dirname(filePath), { recursive: true });
    writeFileSync(filePath, installationId, { encoding: 'utf8', flag: 'w' });

    return installationId;
}

function safeHostname(): string | null {
    try {
        return hostname() || null;
    } catch {
        return null;
    }
}
