import { LockKeyhole } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';

export function ProtectedAction({ allowed, children, fallbackLabel = 'Restricted', onDenied }: { allowed: boolean; children: ReactNode; fallbackLabel?: string; onDenied?: () => void }) {
    if (allowed) {
        return <>{children}</>;
    }

    return (
        <Button variant="secondary" type="button" disabled={!onDenied} onClick={onDenied}>
            <LockKeyhole className="h-4 w-4" />
            {fallbackLabel}
        </Button>
    );
}
