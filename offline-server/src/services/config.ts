import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const currentDir = dirname(fileURLToPath(import.meta.url));

export type OfflineServerConfig = {
    name: string;
    port: number;
    centerId: string | null;
    storagePath: string;
    packagePublicKeyPath: string;
    syncBaseUrl: string | null;
    syncToken: string | null;
    syncAdminEmail: string | null;
    syncAdminPassword: string | null;
};

export function loadConfig(env: NodeJS.ProcessEnv = process.env): OfflineServerConfig {
    const configEnv = env === process.env ? { ...readDotEnvFile(), ...env } : env;

    return {
        name: configEnv.OFFLINE_SERVER_NAME ?? 'AlignEx Offline Center',
        port: Number(configEnv.OFFLINE_SERVER_PORT ?? 4080),
        centerId: valueOrNull(configEnv.OFFLINE_CENTER_ID),
        storagePath: configEnv.OFFLINE_STORAGE_PATH ?? './data/offline.sqlite',
        packagePublicKeyPath: configEnv.OFFLINE_PACKAGE_PUBLIC_KEY_PATH ?? './keys/alignex-package-public.pem',
        syncBaseUrl: valueOrNull(configEnv.ALIGNEX_SYNC_BASE_URL),
        syncToken: valueOrNull(configEnv.ALIGNEX_SYNC_TOKEN),
        syncAdminEmail: valueOrNull(configEnv.ALIGNEX_SYNC_ADMIN_EMAIL),
        syncAdminPassword: valueOrNull(configEnv.ALIGNEX_SYNC_ADMIN_PASSWORD),
    };
}

function valueOrNull(value: string | undefined): string | null {
    return value && value.trim().length > 0 ? value : null;
}

function readDotEnvFile(): NodeJS.ProcessEnv {
    const path = findDotEnvPath();

    if (!path) {
        return {};
    }

    const values: NodeJS.ProcessEnv = {};

    for (const line of readFileSync(path, 'utf8').split(/\r?\n/)) {
        const trimmed = line.trim();

        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }

        const separator = trimmed.indexOf('=');

        if (separator === -1) {
            continue;
        }

        const key = trimmed.slice(0, separator).trim();
        const value = trimmed.slice(separator + 1).trim();
        values[key] = stripQuotes(value);
    }

    return values;
}

function findDotEnvPath(): string | null {
    const candidates = [
        resolve(currentDir, '..', '..', '.env'),
        resolve(currentDir, '..', '..', '..', '.env'),
        resolve(process.cwd(), '.env'),
    ];

    return candidates.find((candidate) => existsSync(candidate)) ?? null;
}

function stripQuotes(value: string): string {
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
        return value.slice(1, -1);
    }

    return value;
}
