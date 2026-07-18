import { Loader2 } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function TimeExpiredSubmittingPage() {
    return (
        <RecoveryPage
            action="Keep this app open until submission is complete."
            explanation="Time is up. Your exam is being submitted automatically."
            icon={<Loader2 className="h-9 w-9 animate-spin" />}
            title="Submitting Exam"
            tone="warning"
        />
    );
}
