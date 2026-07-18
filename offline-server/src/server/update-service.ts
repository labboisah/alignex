import type DatabaseConstructor from 'better-sqlite3';
import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { getLocalSyncConfig } from './activation-service.js';

type SqliteDatabase = InstanceType<typeof DatabaseConstructor>;

export type UpdateArtifact = 'server' | 'client_app';

export type PortalUpdateArtifact = {
    artifact: UpdateArtifact;
    version: string;
    filename: string;
    size_bytes: number;
    sha256: string;
    download_url: string;
    updated_at: string;
};

export type UpdateCheckResult = {
    current_versions: Record<UpdateArtifact, string>;
    updates: Record<UpdateArtifact, (PortalUpdateArtifact & { update_available: boolean }) | null>;
};

export type DownloadUpdateResult = {
    success: true;
    artifact: UpdateArtifact;
    version: string;
    filename: string;
    file_path: string;
    size_bytes: number;
    sha256: string;
    message: string;
};

export class UpdateError extends Error {
    constructor(
        message: string,
        public readonly statusCode = 400,
    ) {
        super(message);
    }
}

const currentVersions: Record<UpdateArtifact, string> = {
    server: readPackageVersion('offline-server'),
    client_app: '0.1.0',
};

export async function checkForUpdates(connection: SqliteDatabase, databasePath: string): Promise<UpdateCheckResult> {
    const config = getLocalSyncConfig(connection, databasePath);
    const portalUrl = normalizePortalUrl(config.portal_url);

    if (!config.admin_email || !config.admin_password) {
        throw new UpdateError('Portal admin credentials are missing. Reactivate the server to enable updates.', 422);
    }

    const response = await fetch(`${portalUrl}/api/offline/updates`, {
        headers: syncHeaders(config.admin_email, config.admin_password),
    });
    const data = (await response.json().catch(() => ({}))) as { updates?: Partial<Record<UpdateArtifact, PortalUpdateArtifact | null>>; message?: string };

    if (!response.ok) {
        throw new UpdateError(data.message ?? 'Unable to check portal updates.', response.status);
    }

    return {
        current_versions: currentVersions,
        updates: {
            server: withAvailability(data.updates?.server ?? null, currentVersions.server),
            client_app: withAvailability(data.updates?.client_app ?? null, currentVersions.client_app),
        },
    };
}

export async function downloadUpdate(connection: SqliteDatabase, databasePath: string, artifact: UpdateArtifact): Promise<DownloadUpdateResult> {
    const check = await checkForUpdates(connection, databasePath);
    const metadata = check.updates[artifact];

    if (!metadata) {
        throw new UpdateError('This update artifact is not available on the portal.', 404);
    }

    const config = getLocalSyncConfig(connection, databasePath);

    if (!config.admin_email || !config.admin_password) {
        throw new UpdateError('Portal admin credentials are missing. Reactivate the server to enable updates.', 422);
    }

    const response = await fetch(metadata.download_url, {
        headers: syncHeaders(config.admin_email, config.admin_password),
    });

    if (!response.ok) {
        throw new UpdateError(`Unable to download update. Portal returned ${response.status}.`, response.status);
    }

    const bytes = Buffer.from(await response.arrayBuffer());
    const actualSha = createHash('sha256').update(bytes).digest('hex');

    if (actualSha.toLowerCase() !== metadata.sha256.toLowerCase()) {
        throw new UpdateError('Update verification failed. The downloaded file checksum did not match the portal metadata.', 422);
    }

    const updateFolder = join(dirname(databasePath), 'updates', artifact, metadata.version);
    mkdirSync(updateFolder, { recursive: true });
    const filePath = join(updateFolder, metadata.filename);
    writeFileSync(filePath, bytes);
    writeFileSync(
        join(updateFolder, 'metadata.json'),
        JSON.stringify(
            {
                ...metadata,
                downloaded_at: new Date().toISOString(),
                local_file_path: filePath,
            },
            null,
            2,
        ),
    );

    return {
        success: true,
        artifact,
        version: metadata.version,
        filename: metadata.filename,
        file_path: filePath,
        size_bytes: bytes.length,
        sha256: actualSha,
        message: artifact === 'server'
            ? 'Server update downloaded and verified. Restart/apply support will be added in the next step.'
            : 'Client App update downloaded and verified. Share this installer with candidate computers.',
    };
}

function syncHeaders(adminEmail: string, adminPassword: string): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-AlignEx-Admin-Email': adminEmail,
        'X-AlignEx-Admin-Password': adminPassword,
    };
}

function withAvailability(metadata: PortalUpdateArtifact | null, currentVersion: string): (PortalUpdateArtifact & { update_available: boolean }) | null {
    if (!metadata) {
        return null;
    }

    return {
        ...metadata,
        update_available: compareVersions(metadata.version, currentVersion) > 0,
    };
}

function compareVersions(left: string, right: string): number {
    const leftParts = left.split('.').map((part) => Number.parseInt(part, 10) || 0);
    const rightParts = right.split('.').map((part) => Number.parseInt(part, 10) || 0);
    const length = Math.max(leftParts.length, rightParts.length);

    for (let index = 0; index < length; index += 1) {
        const difference = (leftParts[index] ?? 0) - (rightParts[index] ?? 0);

        if (difference !== 0) {
            return difference > 0 ? 1 : -1;
        }
    }

    return 0;
}

function normalizePortalUrl(value: string | null): string {
    const trimmed = (value ?? '').trim().replace(/\/+$/, '');

    if (!trimmed) {
        throw new UpdateError('Portal URL is missing. Reactivate the server with the portal URL to enable updates.', 422);
    }

    return trimmed;
}

function readPackageVersion(packageName: 'offline-server'): string {
    try {
        const packageJson = JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'package.json'), 'utf8')) as { version?: unknown };
        return typeof packageJson.version === 'string' ? packageJson.version : '0.1.0';
    } catch {
        return packageName === 'offline-server' ? '0.1.0' : '0.1.0';
    }
}
