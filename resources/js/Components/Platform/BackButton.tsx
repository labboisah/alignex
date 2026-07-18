import { router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export function BackButton({ fallbackHref = '/dashboard', label = 'Back', className }: { fallbackHref?: string; label?: string; className?: string }) {
    const { url } = usePage();

    const goBack = () => {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        const current = new URL(window.location.href);
        const canUseHistory = referrer
            && referrer.origin === current.origin
            && `${referrer.pathname}${referrer.search}` !== url;

        if (canUseHistory) {
            window.history.back();
            return;
        }

        router.visit(fallbackHref);
    };

    return (
        <Button type="button" variant="secondary" onClick={goBack} className={className} aria-label={label}>
            <ArrowLeft className="h-4 w-4" />
            <span className="hidden sm:inline">{label}</span>
        </Button>
    );
}
