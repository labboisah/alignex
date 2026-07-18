import { Loader2, Wifi } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';
import { AlertBanner } from '../components/AlertBanner';
import { AppLogo } from '../components/AppLogo';
import { AppShell } from '../components/AppShell';
import { PageCard } from '../components/PageCard';
import { Button } from '../components/ui/button';
import { useExamSession } from '../context/ExamSessionContext';
import { ApiClientError, apiClient, getSavedServerUrl, isMockApiEnabled, normalizeServerUrl, saveServerUrl, validateServerUrl } from '../services/apiClient';
import { logDevelopmentError } from '../utils/logDevelopmentError';

type ConnectionState = 'idle' | 'testing' | 'connected' | 'failed';

export function ServerConfigurationPage({ onContinue }: { onContinue: () => void }) {
    const examSession = useExamSession();
    const savedUrl = useMemo(() => getSavedServerUrl() ?? '', []);
    const [serverUrl, setServerUrl] = useState(savedUrl);
    const [connectionState, setConnectionState] = useState<ConnectionState>(savedUrl ? 'connected' : 'idle');
    const [message, setMessage] = useState<string | null>(savedUrl ? 'Connected.' : null);
    const [validationError, setValidationError] = useState<string | null>(null);

    const isTesting = connectionState === 'testing';

    async function handleTestConnection(event?: FormEvent<HTMLFormElement>) {
        event?.preventDefault();

        const candidateServerUrl = isMockApiEnabled() && !serverUrl.trim() ? 'http://mock-center.local' : serverUrl;
        const error = validateServerUrl(candidateServerUrl);
        setValidationError(error);

        if (error) {
            setConnectionState('failed');
            setMessage(error);
            return;
        }

        setConnectionState('testing');
        setMessage(null);

        try {
            await apiClient.healthCheck(candidateServerUrl);
        } catch (caught) {
            logDevelopmentError('Center Server health check failed', caught);
            setConnectionState('failed');
            setMessage(
                caught instanceof ApiClientError
                    ? 'Unable to connect to Center Server. Confirm the IP address and network connection.'
                    : 'Unable to connect to Center Server. Confirm the IP address and network connection.',
            );
            return;
        }

        const normalizedUrl = normalizeServerUrl(candidateServerUrl);
        saveServerUrl(normalizedUrl);
        examSession.setServerUrl(normalizedUrl);
        setServerUrl(normalizedUrl);
        setConnectionState('connected');
        setValidationError(null);
        setMessage('Connected.');
        onContinue();
    }

    return (
        <AppShell>
            <PageCard className="max-w-md">
                <div className="flex justify-center">
                    <AppLogo />
                </div>

                <form className="mt-6 space-y-5" onSubmit={(event) => void handleTestConnection(event)}>
                    <label className="block space-y-2">
                        <input
                            className="h-14 w-full rounded-md border border-border bg-white px-4 text-lg font-semibold text-slateDark outline-none transition-colors placeholder:text-base placeholder:font-medium placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/20"
                            disabled={isTesting}
                            onChange={(event) => {
                                setServerUrl(event.target.value);
                                setValidationError(null);
                                if (connectionState !== 'idle') {
                                    setConnectionState('idle');
                                    setMessage(null);
                                }
                            }}
                            placeholder="Center Server URL"
                            value={serverUrl}
                        />
                        {validationError && <span className="text-sm font-medium text-danger">{validationError}</span>}
                    </label>

                    {connectionState === 'connected' && (
                        <AlertBanner tone="success" title="Connected" message={message ?? 'Ready.'} />
                    )}

                    {connectionState === 'failed' && (
                        <AlertBanner
                            tone={validationError ? 'danger' : 'warning'}
                            title={validationError ? 'Invalid URL' : 'Cannot connect'}
                            message={message ?? 'Unable to connect to Center Server. Confirm the IP address and network connection.'}
                        />
                    )}

                    <Button className="w-full" type="submit" size="lg" disabled={isTesting}>
                        {isTesting ? <Loader2 className="h-5 w-5 animate-spin" /> : <Wifi className="h-5 w-5" />}
                        Connect
                    </Button>
                </form>
            </PageCard>
        </AppShell>
    );
}
