import { LockKeyhole } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function ExamClosedPage({ onBackToLogin }: { onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="Contact the supervisor if you think you should still have access."
            explanation="This exam has been closed by the supervisor."
            icon={<LockKeyhole className="h-9 w-9" />}
            title="Exam Closed"
            tone="warning"
            onBackToLogin={onBackToLogin}
        />
    );
}
