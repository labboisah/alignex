import { MonitorX } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function CandidateAlreadyLoggedInPage({ onRetry, onBackToLogin }: { onRetry?: () => void; onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="Ask the supervisor to reset your device login if you need to use this computer."
            explanation="This candidate is already logged in on another device."
            icon={<MonitorX className="h-9 w-9" />}
            title="Already Logged In"
            tone="warning"
            onBackToLogin={onBackToLogin}
            onRetry={onRetry}
        />
    );
}
