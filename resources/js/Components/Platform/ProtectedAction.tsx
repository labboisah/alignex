import { LockKeyhole } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { usePage } from '@inertiajs/react';

type SharedProps = {
    auth?: {
        permissions?: Record<string, boolean>;
        plan_features?: Record<string, boolean>;
    };
};

export function ProtectedAction({
    allowed,
    permission,
    feature,
    children,
    fallbackLabel = 'Restricted',
    onDenied,
    hideWhenDenied = true,
}: {
    allowed?: boolean;
    permission?: string;
    feature?: string;
    children: ReactNode;
    fallbackLabel?: string;
    onDenied?: () => void;
    hideWhenDenied?: boolean;
}) {
    const permissions = (usePage().props as SharedProps).auth?.permissions ?? {};
    const planFeatures = (usePage().props as SharedProps).auth?.plan_features ?? {};
    const hasPermission = permission ? Boolean(permissions[permission]) : true;
    const hasFeature = feature ? Boolean(planFeatures[feature]) : true;
    const canProceed = allowed ?? (hasPermission && hasFeature);

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
