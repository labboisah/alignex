import { TriangleAlert } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function SubmitFailedRetryPage({ onRetry, onBackToLogin }: { onRetry?: () => void; onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="Check the Center Server connection and retry submission."
            explanation="Your exam could not be submitted yet."
            icon={<TriangleAlert className="h-9 w-9" />}
            retryLabel="Retry Submit"
            title="Submission Failed"
            tone="error"
            onBackToLogin={onBackToLogin}
            onRetry={onRetry}
        />
    );
}
