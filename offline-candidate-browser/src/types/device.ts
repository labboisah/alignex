export type SafeDeviceInfo = {
    platform: string;
    hostname: string | null;
    installationId: string;
};

export type DeviceFingerprintResult = {
    fingerprint: string;
    device: SafeDeviceInfo;
};
