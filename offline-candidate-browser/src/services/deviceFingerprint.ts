import type { DeviceFingerprintResult, SafeDeviceInfo } from '../types/device';

const fallbackInstallationKey = 'alignex_candidate_client_installation_id';

export async function getSafeDeviceInfo(): Promise<SafeDeviceInfo> {
    if (window.alignexCandidate?.getSafeDeviceInfo) {
        return window.alignexCandidate.getSafeDeviceInfo();
    }

    return getFallbackSafeDeviceInfo();
}

export async function getDeviceFingerprint(): Promise<DeviceFingerprintResult> {
    if (window.alignexCandidate?.getDeviceFingerprint) {
        try {
            const result = await window.alignexCandidate.getDeviceFingerprint();

            if (result.fingerprint.trim()) {
                return result;
            }
        } catch {
            // Fall back below so a renderer/dev bridge issue does not permanently block login.
        }
    }

    const device = await getFallbackSafeDeviceInfo();
    const source = JSON.stringify({
        platform: device.platform,
        hostname: device.hostname,
        installationId: device.installationId,
        userAgent: navigator.userAgent,
    });

    return {
        fingerprint: await sha256(source),
        device,
    };
}

async function getFallbackSafeDeviceInfo(): Promise<SafeDeviceInfo> {
    return {
        platform: navigator.platform || 'browser',
        hostname: null,
        installationId: getOrCreateFallbackInstallationId(),
    };
}

function getOrCreateFallbackInstallationId(): string {
    const existing = readLocalStorage(fallbackInstallationKey);

    if (existing) {
        return existing;
    }

    const next = crypto.randomUUID?.() ?? `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
    writeLocalStorage(fallbackInstallationKey, next);

    return next;
}

async function sha256(value: string): Promise<string> {
    if (crypto.subtle) {
        const encoded = new TextEncoder().encode(value);
        const digest = await crypto.subtle.digest('SHA-256', encoded);

        return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
    }

    return btoa(value).replace(/[^a-zA-Z0-9]/g, '').slice(0, 64);
}

function readLocalStorage(key: string): string | null {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
}

function writeLocalStorage(key: string, value: string): void {
    try {
        localStorage.setItem(key, value);
    } catch {
        // In locked-down storage contexts, keep the in-memory value for this runtime.
    }
}
