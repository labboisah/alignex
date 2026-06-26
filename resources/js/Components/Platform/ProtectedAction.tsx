import { LockKeyhole } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { usePage } from '@inertiajs/react';

type SharedProps = {
    auth?: {
        permissions?: Record<string, boolean>;
    };
};

export function ProtectedAction({
    allowed,
    permission,
    children,
    fallbackLabel = 'Restricted',
    onDenied,
    hideWhenDenied = true,
}: {
    allowed?: boolean;
    permission?: string;
    children: ReactNode;
    fallbackLabel?: string;
    onDenied?: () => void;
    hideWhenDenied?: boolean;
}) {
    const permissions = (usePage().props as SharedProps).auth?.permissions ?? {};
    const canProceed = allowed ?? (permission ? Boolean(permissions[permission]) : false);

    if (canProceed) {
        return <>{children}</>;
    }

    if (hideWhenDenied && !onDenied) {
        return null;
    }

    return (
        <Button variant="secondary" type="button" disabled={!onDenied} onClick={onDenied}>
            <LockKeyhole className="h-4 w-4" />
            {fallbackLabel}
        </Button>
    );
}
