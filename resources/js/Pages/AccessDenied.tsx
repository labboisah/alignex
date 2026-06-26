import { Head, Link } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function AccessDenied() {
    return (
        <PortalAppShell title="Access denied">
            <Head title="Access denied" />
            <section className="mx-auto max-w-3xl">
                <div className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <ShieldAlert className="h-10 w-10 text-danger" />
                    <PageHeader
                        eyebrow="Restricted area"
                        title="Access denied"
                        description="Your current role does not include permission to open this portal area."
                    />
                    <Button asChild type="button">
                        <Link href="/dashboard">Back to dashboard</Link>
                    </Button>
                </div>
            </section>
        </PortalAppShell>
    );
}
