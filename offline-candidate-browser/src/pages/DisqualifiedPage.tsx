import { ShieldAlert } from 'lucide-react';
import { RecoveryPage } from '../components/RecoveryPage';

export function DisqualifiedPage({ reason, onDone }: { reason?: string; onDone: () => void }) {
    return (
        <RecoveryPage
            action={reason || 'Please remain seated and contact the supervisor.'}
            backLabel="Return to Login"
            explanation="The supervisor has stopped this exam session."
            icon={<ShieldAlert className="h-9 w-9" />}
            title="Candidate Disqualified"
            tone="error"
            onBackToLogin={onDone}
        />
    );
}
