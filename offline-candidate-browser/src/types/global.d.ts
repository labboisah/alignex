export {};

import type { DeviceFingerprintResult, SafeDeviceInfo } from './device';

declare global {
    interface Window {
        alignexCandidate?: {
            appName: string;
            enterExamMode?: () => Promise<{ success: boolean }>;
            exitExamMode?: () => Promise<{ success: boolean }>;
            getSafeDeviceInfo: () => Promise<SafeDeviceInfo>;
            getDeviceFingerprint: () => Promise<DeviceFingerprintResult>;
            onSecurityEvent?: (callback: (payload: { event_type: string }) => void) => () => void;
        };
    }
}
