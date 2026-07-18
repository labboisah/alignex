import { Loader2, RefreshCw } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { AlertBanner } from '../components/AlertBanner';
import { AppLogo } from '../components/AppLogo';
import { AppShell } from '../components/AppShell';
import { PageCard } from '../components/PageCard';
import { Button } from '../components/ui/button';
import { useExamSession } from '../context/ExamSessionContext';
import { ApiClientError, apiClient, getSavedServerUrl } from '../services/apiClient';
import { getDeviceFingerprint } from '../services/deviceFingerprint';
import type { DeviceFingerprintResult } from '../types/device';
import { logDevelopmentError } from '../utils/logDevelopmentError';

export type LoginRecoveryScreen = 'server-connection-error' | 'candidate-already-submitted' | 'candidate-already-logged-in' | 'exam-not-active' | 'exam-closed';

type LoginErrors = {
    registration_number?: string;
};

export function CandidateLoginPage({
    onLoginSuccess,
    onRecovery,
}: {
    onChangeServer: () => void;
    onLoginSuccess: () => void;
    onRecovery: (screen: LoginRecoveryScreen) => void;
}) {
    const examSession = useExamSession();
    const serverUrl = examSession.server_url ?? getSavedServerUrl();
    const [deviceStatus, setDeviceStatus] = useState<'checking' | 'verified' | 'failed'>('checking');
    const [deviceFingerprint, setDeviceFingerprint] = useState<DeviceFingerprintResult | null>(null);
    const [registrationNumber, setRegistrationNumber] = useState('');
    const [errors, setErrors] = useState<LoginErrors>({});
    const [loginError, setLoginError] = useState<string | null>(null);
    const [loggingIn, setLoggingIn] = useState(false);
    const [lastPayload, setLastPayload] = useState<{ registrationNumber: string } | null>(null);

    const verifyDevice = async () => {
        setDeviceStatus('checking');

        try {
            const result = await getDeviceFingerprint();
            setDeviceFingerprint(result);
            setDeviceStatus('verified');
        } catch {
            setDeviceFingerprint(null);
            setDeviceStatus('failed');
        }
    };

    useEffect(() => {
        void verifyDevice();
    }, []);

    async function handleSubmit(event?: FormEvent<HTMLFormElement>, retryPayload = lastPayload) {
        event?.preventDefault();
        const payload = retryPayload ?? { registrationNumber };
        const nextErrors = validateLogin(payload.registrationNumber);
        setErrors(nextErrors);
        setLoginError(null);

        if (Object.keys(nextErrors).length > 0) {
            return;
        }

        if (!serverUrl) {
            setLoginError('Connect to the Center Server first.');
            return;
        }

        if (!deviceFingerprint) {
            setLoginError('Device verification failed. Contact the supervisor.');
            setDeviceStatus('failed');
            return;
        }

        try {
            setLoggingIn(true);
            setLastPayload(payload);
            const response = await apiClient.candidateLogin({
                registration_number: payload.registrationNumber.trim(),
                device_fingerprint: deviceFingerprint.fingerprint,
                device_info: deviceFingerprint.device,
            });
            examSession.setLoginSession(response);
            onLoginSuccess();
        } catch (caught) {
            logDevelopmentError('Candidate login failed', caught);
            examSession.setConnectionStatus(caught instanceof ApiClientError && (caught.code === 'network_error' || caught.code === 'timeout') ? 'disconnected' : examSession.connection_status);
            const recoveryScreen = toLoginRecoveryScreen(caught);

            if (recoveryScreen) {
                onRecovery(recoveryScreen);
                return;
            }

            setLoginError(toFriendlyLoginMessage(caught));
        } finally {
            setLoggingIn(false);
        }
    }

    return (
        <AppShell>
            <PageCard className="max-w-md">
                <div className="flex justify-center">
                    <AppLogo />
                </div>
                {!serverUrl && (
                    <div className="mt-5">
                        <AlertBanner tone="danger" title="No server" message="Connect to the Center Server first." />
                    </div>
                )}
                {deviceStatus === 'failed' && (
                    <div className="mt-5 rounded-md border border-danger/30 bg-danger/5 p-4 text-sm font-semibold text-danger">
                        Device verification failed. Contact the supervisor.
                    </div>
                )}
                {deviceStatus === 'checking' && (
                    <div className="mt-5 flex items-center gap-2 rounded-md border border-border bg-slate-50 p-4 text-sm font-semibold text-slate-600">
                        <Loader2 className="h-4 w-4 animate-spin text-primary" />
                        Verifying this device...
                    </div>
                )}

                <form className="mt-6 space-y-5" onSubmit={(event) => void handleSubmit(event)}>
                    <Field
                        error={errors.registration_number}
                        label="Registration Number"
                        onChange={(value) => {
                            setRegistrationNumber(value);
                            setErrors((current) => ({ ...current, registration_number: undefined }));
                        }}
                        placeholder="Registration number"
                        value={registrationNumber}
                    />

                    {loginError && (
                        <AlertBanner tone="danger" title="Login failed" message={loginError} />
                    )}

                    {loginError && lastPayload && (
                        <Button className="w-full" type="button" variant="secondary" onClick={() => void handleSubmit(undefined, lastPayload)} disabled={loggingIn}>
                            <RefreshCw className="h-4 w-4" />
                            Retry
                        </Button>
                    )}
                    {deviceStatus === 'failed' && (
                        <Button className="w-full" type="button" variant="secondary" onClick={() => void verifyDevice()} disabled={loggingIn}>
                            <RefreshCw className="h-4 w-4" />
                            Retry Device Verification
                        </Button>
                    )}
                    <Button className="w-full" type="submit" size="lg" disabled={loggingIn || deviceStatus === 'checking' || !serverUrl}>
                        {loggingIn && <Loader2 className="h-5 w-5 animate-spin" />}
                        Login
                    </Button>
                </form>
            </PageCard>
        </AppShell>
    );
}

function Field({ label, value, placeholder, error, onChange }: { label: string; value: string; placeholder: string; error?: string; onChange: (value: string) => void }) {
    return (
        <label className="block space-y-2">
            <span className="text-base font-semibold text-slateDark">{label}</span>
            <input
                className="h-14 w-full rounded-md border border-border bg-white px-4 text-lg font-semibold text-slateDark outline-none transition-colors placeholder:text-base placeholder:font-medium placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                value={value}
            />
            {error && <span className="text-sm font-medium text-danger">{error}</span>}
        </label>
    );
}

function validateLogin(registrationNumber: string): LoginErrors {
    const errors: LoginErrors = {};

    if (!registrationNumber.trim()) {
        errors.registration_number = 'Registration number is required.';
    }

    return errors;
}

function toLoginRecoveryScreen(caught: unknown): LoginRecoveryScreen | null {
    if (!(caught instanceof ApiClientError)) {
        return null;
    }

    const message = caught.message.toLowerCase();

    if (caught.code === 'network_error' || caught.code === 'timeout') return 'server-connection-error';
    if (message.includes('already submitted')) return 'candidate-already-submitted';
    if (message.includes('another device') || message.includes('already logged in')) return 'candidate-already-logged-in';
    if (message.includes('not active')) return 'exam-not-active';
    if (message.includes('closed')) return 'exam-closed';

    return null;
}

function toFriendlyLoginMessage(caught: unknown): string {
    const fallback = 'Unable to login. Please confirm your details and try again.';

    if (!(caught instanceof ApiClientError)) {
        return fallback;
    }

    const message = caught.message.toLowerCase();

    if (caught.code === 'network_error' || caught.code === 'timeout') {
        return 'Unable to connect to Center Server. Confirm the IP address and network connection.';
    }

    if (message.includes('invalid exam code') || message.includes('active exam')) return 'No active exam was found for this registration number.';
    if (message.includes('candidate not found')) return 'Candidate not found for this exam. Please confirm your registration number.';
    if (message.includes('not active')) return 'This exam is not active yet. Please wait for the supervisor to start it.';
    if (message.includes('already submitted')) return 'This candidate has already submitted the exam.';
    if (message.includes('another device') || message.includes('already logged in')) return 'This candidate is already logged in on another device. Please contact the supervisor.';

    return fallback;
}
