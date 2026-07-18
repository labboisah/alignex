import { CheckCircle2 } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function CandidateAlreadySubmittedPage({ onBackToLogin }: { onBackToLogin?: () => void }) {
    return (
        <RecoveryPage
            action="If you believe this is wrong, contact the supervisor."
            explanation="This registration number has already submitted this exam."
            icon={<CheckCircle2 className="h-9 w-9" />}
            title="Exam Already Submitted"
            tone="success"
            onBackToLogin={onBackToLogin}
        />
    );
}
