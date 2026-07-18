import type { OfflinePackage } from '../contracts/offline-package.js';

export type PackageVerificationResult = {
    valid: boolean;
    errors: string[];
};

export function verifyOfflinePackage(pkg: OfflinePackage, now = new Date()): PackageVerificationResult {
    const errors: string[] = [];

    if (!pkg.packageId) {
        errors.push('Package id is required.');
    }

    if (!pkg.signature) {
        errors.push('Package signature is required.');
    }

    if (new Date(pkg.expiresAt).getTime() <= now.getTime()) {
        errors.push('Package has expired.');
    }

    if (pkg.candidates.length === 0) {
        errors.push('Package must include at least one candidate.');
    }

    if (pkg.papers.length === 0) {
        errors.push('Package must include candidate papers.');
    }

    return { valid: errors.length === 0, errors };
}
