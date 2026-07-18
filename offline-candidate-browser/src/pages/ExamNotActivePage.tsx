import { Clock3 } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function ExamNotActivePage({ onRetry, onBackToLogin }: { onRetry?: () => void; onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="Wait for the supervisor to start the exam, then try again."
            explanation="This exam is not active yet."
            icon={<Clock3 className="h-9 w-9" />}
            title="Exam Not Active"
            tone="info"
            onBackToLogin={onBackToLogin}
            onRetry={onRetry}
        />
    );
}
