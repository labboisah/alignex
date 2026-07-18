import { WifiOff } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function ServerConnectionErrorPage({ onRetry, onBackToLogin }: { onRetry?: () => void; onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="Confirm the computer is on the same network as the Center Server, then try again."
            explanation="This computer cannot reach the Center Server right now."
            icon={<WifiOff className="h-9 w-9" />}
            title="Server Connection Problem"
            tone="error"
            onBackToLogin={onBackToLogin}
            onRetry={onRetry}
        />
    );
}
