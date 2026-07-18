import type DatabaseConstructor from 'better-sqlite3';
import { createHash, pbkdf2Sync, randomBytes, randomUUID, timingSafeEqual } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { normalizePlanFeatures, type PlanFeatures, type PlanSummary } from './plan-features.js';

type SqliteDatabase = InstanceType<typeof DatabaseConstructor>;

export type ApplicationState = 'not_configured' | 'activated' | 'expired' | 'revoked' | 'maintenance' | 'invalid';

export type ActivationStatus = {
    state: ApplicationState;
    configured: boolean;
    device_id: string;
    organization_name: string | null;
    center_name: string | null;
    portal_url: string | null;
    admin_email: string | null;
    activated_at: string | null;
    expires_at: string | null;
    days_remaining: number | null;
    plan: PlanSummary;
    plan_features: PlanFeatures;
    message: string;
};

export type LocalSyncConfig = {
    portal_url: string | null;
    admin_email: string | null;
    admin_password: string | null;
    sync_token: string | null;
    center_id: string | null;
};

export class ActivationError extends Error {
    constructor(
        message: string,
        public readonly statusCode = 400,
    ) {
        super(message);
    }
}

export function getActivationStatus(connection: SqliteDatabase, databasePath: string): ActivationStatus {
    const deviceId = ensureDeviceId(connection);
    const config = readLocalConfig(databasePath);
    const license = getLatestLicense(connection);

    if (readSetting(connection, 'maintenance_mode') === 'true') {
        return status('maintenance', deviceId, license, config, 'This center server is currently in maintenance mode.');
    }

    if (!config && !license) {
        return status('not_configured', deviceId, license, config, 'First launch setup is required.');
    }

    if (!config) {
        return status('invalid', deviceId, license, config, 'Local configuration file is missing or corrupt.');
    }

    if (!license) {
        return status('not_configured', deviceId, license, config, 'Activation is required.');
    }

    if (license.status === 'revoked') {
        return status('revoked', deviceId, license, config, 'This license has been revoked.');
    }

    if (license.status === 'maintenance') {
        return status('maintenance', deviceId, license, config, 'This center server is in maintenance mode.');
    }

    if (new Date(license.expires_at).getTime() <= Date.now()) {
        updateLicenseStatus(connection, license.id, 'expired');
        return status('expired', deviceId, { ...license, status: 'expired' }, config, 'This license has expired.');
    }

    return status('activated', deviceId, license, config, 'License is active.');
}

export async function activateOnline(
    connection: SqliteDatabase,
    databasePath: string,
    input: {
        portal_url?: string;
        activation_code: string;
        admin_email: string;
        admin_password: string;
        center_name?: string;
        organization_name?: string;
    },
): Promise<ActivationStatus> {
    validateSetupInput(input);
    const deviceId = ensureDeviceId(connection);
    const portalUrl = normalizeUrl(input.portal_url ?? '');
    let remotePayload: Partial<LicensePayload>;

    try {
        const response = await fetch(`${portalUrl}/api/offline/activate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                activation_code: input.activation_code.trim(),
                device_id: deviceId,
                admin_email: input.admin_email.trim(),
                admin_password: input.admin_password,
                center_name: input.center_name?.trim() || null,
            }),
        });

        const data = (await response.json().catch(() => ({}))) as Partial<LicensePayload> & { message?: string };

        if (!response.ok) {
            throw new ActivationError(data.message ?? 'Activation code could not be validated by the portal.', response.status);
        }

        remotePayload = data;
    } catch (caught) {
        if (caught instanceof ActivationError) {
            throw caught;
        }

        throw new ActivationError('Unable to reach the portal activation service.', 503);
    }

    const activatedAt = new Date().toISOString();
    const expiresAt = oneYearFromNow();
    const payload: LicensePayload = {
        license_key: String(remotePayload.license_key ?? input.activation_code.trim()),
        organization_name: String(remotePayload.organization_name ?? input.organization_name ?? 'Activated Organization'),
        center_name: String(remotePayload.center_name ?? input.center_name ?? 'Offline Center'),
        center_id: String(remotePayload.center_id ?? ''),
        sync_token: remotePayload.sync_token ? String(remotePayload.sync_token) : null,
        activated_at: activatedAt,
        expires_at: String(remotePayload.expires_at ?? expiresAt),
        status: 'activated',
    };

    const adminEmail = input.admin_email.trim();
    saveActivation(connection, databasePath, deviceId, portalUrl, adminEmail, input.admin_password, payload);
    createOrUpdateLocalAdmin(connection, String(remotePayload.admin_name ?? adminEmail), adminEmail, input.admin_password);

    return getActivationStatus(connection, databasePath);
}

export function activateOffline(
    connection: SqliteDatabase,
    databasePath: string,
    input: {
        signed_config: string;
        admin_name: string;
        admin_email: string;
        admin_password: string;
    },
): ActivationStatus {
    if (!input.signed_config.trim()) {
        throw new ActivationError('Signed config file content is required.');
    }

    validateAdminInput(input);
    const parsed = JSON.parse(input.signed_config) as { payload?: LicensePayload; signature?: string };

    if (!parsed.payload || !parsed.signature) {
        throw new ActivationError('Signed config must include payload and signature.');
    }

    const expected = signPayload(parsed.payload);
    if (parsed.signature !== expected) {
        throw new ActivationError('Signed config validation failed.', 422);
    }

    const deviceId = ensureDeviceId(connection);
    saveActivation(connection, databasePath, deviceId, parsed.payload.portal_url ?? null, input.admin_email, input.admin_password, {
        ...parsed.payload,
        status: parsed.payload.status ?? 'activated',
        activated_at: parsed.payload.activated_at ?? new Date().toISOString(),
        expires_at: parsed.payload.expires_at ?? oneYearFromNow(),
    });
    createOrUpdateLocalAdmin(connection, input.admin_name, input.admin_email, input.admin_password);

    return getActivationStatus(connection, databasePath);
}

export function loginLocalAdmin(connection: SqliteDatabase, email: string, password: string): { success: true; admin: { name: string; email: string } } {
    const admin = connection.prepare('SELECT name, email, password_hash, password_salt FROM local_users WHERE email = ? AND role = ? LIMIT 1').get(email.trim(), 'admin') as
        | { name: string; email: string; password_hash: string; password_salt: string }
        | undefined;

    if (!admin || !verifyPassword(password, admin.password_salt, admin.password_hash)) {
        throw new ActivationError('Invalid local admin email or password.', 401);
    }

    connection.prepare('UPDATE local_users SET last_login_at = ?, updated_at = ? WHERE email = ?').run(new Date().toISOString(), new Date().toISOString(), admin.email);

    return { success: true, admin: { name: admin.name, email: admin.email } };
}

export function getLocalSyncConfig(connection: SqliteDatabase, databasePath: string): LocalSyncConfig {
    const config = readLocalConfig(databasePath);
    const license = getLatestLicense(connection);

    return {
        portal_url: config?.portal_url ?? null,
        admin_email: config?.admin_email ?? null,
        admin_password: config?.admin_password ?? null,
        sync_token: config?.sync_token ?? null,
        center_id: license?.center_id ?? config?.center_id ?? null,
    };
}

type LicensePayload = {
    license_key: string;
    organization_name: string;
    center_name: string;
    admin_name?: string | null;
    center_id?: string | null;
    portal_url?: string | null;
    sync_token?: string | null;
    activated_at?: string;
    expires_at?: string;
    status?: 'activated' | 'expired' | 'revoked' | 'maintenance';
    plan?: PlanSummary;
    plan_features?: Partial<PlanFeatures>;
};

type LicenseRow = {
    id: string;
    license_key: string;
    organization_name: string;
    center_name: string;
    center_id: string | null;
    status: 'activated' | 'expired' | 'revoked' | 'maintenance';
    activated_at: string;
    expires_at: string;
    raw_payload: string | null;
};

type LocalConfigFile = {
    version: 1;
    device_id: string;
    portal_url: string | null;
    admin_email: string | null;
    admin_password: string | null;
    sync_token: string | null;
    center_id: string | null;
    written_at: string;
};

function saveActivation(connection: SqliteDatabase, databasePath: string, deviceId: string, portalUrl: string | null, adminEmail: string, adminPassword: string, payload: LicensePayload): void {
    const now = new Date().toISOString();
    const licenseId = randomUUID();

    connection
        .prepare(
            `INSERT INTO license_activations (
                id, device_id, license_key, organization_name, center_name, center_id, status, activated_at, expires_at, raw_payload, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        )
        .run(
            licenseId,
            deviceId,
            payload.license_key,
            payload.organization_name,
            payload.center_name,
            payload.center_id ?? null,
            payload.status ?? 'activated',
            payload.activated_at ?? now,
            payload.expires_at ?? oneYearFromNow(),
            JSON.stringify(payload),
            now,
            now,
        );

    writeLocalConfig(databasePath, {
        version: 1,
        device_id: deviceId,
        portal_url: portalUrl ?? payload.portal_url ?? null,
        admin_email: adminEmail.trim(),
        admin_password: adminPassword,
        sync_token: payload.sync_token ?? null,
        center_id: payload.center_id ?? null,
        written_at: now,
    });

    writeSetting(connection, 'application_state', payload.status ?? 'activated');
}

function createOrUpdateLocalAdmin(connection: SqliteDatabase, name: string, email: string, password: string): void {
    validateAdminInput({ admin_name: name, admin_email: email, admin_password: password });
    const now = new Date().toISOString();
    const salt = randomBytes(16).toString('hex');
    const passwordHash = hashPassword(password, salt);
    const existing = connection.prepare('SELECT id FROM local_users WHERE email = ? LIMIT 1').get(email.trim()) as { id: string } | undefined;

    if (existing) {
        connection
            .prepare('UPDATE local_users SET name = ?, password_hash = ?, password_salt = ?, role = ?, status = ?, updated_at = ? WHERE id = ?')
            .run(name.trim(), passwordHash, salt, 'admin', 'active', now, existing.id);
        return;
    }

    connection
        .prepare('INSERT INTO local_users (id, name, email, password_hash, password_salt, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        .run(randomUUID(), name.trim(), email.trim(), passwordHash, salt, 'admin', 'active', now, now);
}

function ensureDeviceId(connection: SqliteDatabase): string {
    const existing = readSetting(connection, 'device_id');

    if (existing) {
        return existing;
    }

    const deviceId = `alignex-${randomUUID()}`;
    writeSetting(connection, 'device_id', deviceId);
    return deviceId;
}

function getLatestLicense(connection: SqliteDatabase): LicenseRow | null {
    return (
        (connection
            .prepare(
                'SELECT id, license_key, organization_name, center_name, center_id, status, activated_at, expires_at, raw_payload FROM license_activations ORDER BY created_at DESC LIMIT 1',
            )
            .get() as LicenseRow | undefined) ?? null
    );
}

function updateLicenseStatus(connection: SqliteDatabase, licenseId: string, nextStatus: LicenseRow['status']): void {
    connection.prepare('UPDATE license_activations SET status = ?, updated_at = ? WHERE id = ?').run(nextStatus, new Date().toISOString(), licenseId);
}

function readSetting(connection: SqliteDatabase, key: string): string | null {
    const row = connection.prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1').get(key) as { setting_value: string | null } | undefined;
    return row?.setting_value ?? null;
}

function writeSetting(connection: SqliteDatabase, key: string, value: string): void {
    const now = new Date().toISOString();
    connection
        .prepare(
            `INSERT INTO app_settings (id, setting_key, setting_value, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at`,
        )
        .run(randomUUID(), key, value, now, now);
}

function status(state: ApplicationState, deviceId: string, license: LicenseRow | null, config: LocalConfigFile | null, message: string): ActivationStatus {
    const expiresAt = license?.expires_at ?? null;
    const daysRemaining = expiresAt ? Math.max(Math.ceil((new Date(expiresAt).getTime() - Date.now()) / 86_400_000), 0) : null;
    const payload = parseLicensePayload(license?.raw_payload ?? null);
    const plan = normalizePlanSummary(payload?.plan);

    return {
        state,
        configured: state !== 'not_configured' && state !== 'invalid',
        device_id: deviceId,
        organization_name: license?.organization_name ?? null,
        center_name: license?.center_name ?? null,
        portal_url: config?.portal_url ?? null,
        admin_email: config?.admin_email ?? null,
        activated_at: license?.activated_at ?? null,
        expires_at: expiresAt,
        days_remaining: daysRemaining,
        plan,
        plan_features: normalizePlanFeatures(payload?.plan_features, true),
        message,
    };
}

function parseLicensePayload(value: string | null): Record<string, unknown> | null {
    if (!value) {
        return null;
    }

    try {
        const parsed = JSON.parse(value) as unknown;
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed as Record<string, unknown> : null;
    } catch {
        return null;
    }
}

function normalizePlanSummary(value: unknown): PlanSummary {
    if (!value || typeof value !== 'object' || Array.isArray(value)) {
        return { id: null, slug: 'legacy', name: 'Legacy Offline License' };
    }

    const record = value as Record<string, unknown>;

    return {
        id: typeof record.id === 'number' || typeof record.id === 'string' ? record.id : null,
        slug: typeof record.slug === 'string' ? record.slug : null,
        name: typeof record.name === 'string' ? record.name : null,
    };
}

function readLocalConfig(databasePath: string): LocalConfigFile | null {
    const path = localConfigPath(databasePath);

    if (!existsSync(path)) {
        return null;
    }

    try {
        const parsed = JSON.parse(readFileSync(path, 'utf8')) as LocalConfigFile;
        return parsed.version === 1 ? parsed : null;
    } catch {
        return null;
    }
}

function writeLocalConfig(databasePath: string, config: LocalConfigFile): void {
    writeFileSync(localConfigPath(databasePath), JSON.stringify(config, null, 2));
}

function localConfigPath(databasePath: string): string {
    return join(dirname(databasePath), 'center-config.json');
}

function normalizeUrl(value: string): string {
    const trimmed = value.trim().replace(/\/+$/, '');

    if (!trimmed) {
        throw new ActivationError('Portal URL is required. Use the same http://IP:PORT address that opens the AlignEx portal in your browser.');
    }

    if (!/^https?:\/\//i.test(trimmed)) {
        throw new ActivationError('Portal URL must start with http:// or https://.');
    }

    return trimmed;
}

function validateSetupInput(input: { portal_url?: string; activation_code: string; admin_email: string; admin_password: string }): void {
    normalizeUrl(input.portal_url ?? '');

    if (!input.activation_code.trim()) {
        throw new ActivationError('Activation code is required.');
    }

    validateAdminCredentials(input);
}

function validateAdminInput(input: { admin_name: string; admin_email: string; admin_password: string }): void {
    if (!input.admin_name.trim()) {
        throw new ActivationError('Admin name is required.');
    }

    if (!input.admin_email.trim()) {
        throw new ActivationError('Admin email is required.');
    }

    if (input.admin_password.length < 1) {
        throw new ActivationError('Admin password is required.');
    }
}

function validateAdminCredentials(input: { admin_email: string; admin_password: string }): void {
    if (!input.admin_email.trim()) {
        throw new ActivationError('Admin email is required.');
    }

    if (input.admin_password.length < 1) {
        throw new ActivationError('Admin password is required.');
    }
}


function hashPassword(password: string, salt: string): string {
    return pbkdf2Sync(password, salt, 120_000, 32, 'sha256').toString('hex');
}

function verifyPassword(password: string, salt: string, expectedHash: string): boolean {
    const actual = Buffer.from(hashPassword(password, salt), 'hex');
    const expected = Buffer.from(expectedHash, 'hex');

    return actual.length === expected.length && timingSafeEqual(actual, expected);
}

function signPayload(payload: LicensePayload): string {
    const secret = process.env.ALIGNEX_OFFLINE_LICENSE_SIGNING_SECRET ?? 'alignex-offline-license-development-secret';
    return createHash('sha256').update(`${JSON.stringify(payload)}:${secret}`).digest('hex');
}

function oneYearFromNow(): string {
    const expiresAt = new Date();
    expiresAt.setFullYear(expiresAt.getFullYear() + 1);
    return expiresAt.toISOString();
}
