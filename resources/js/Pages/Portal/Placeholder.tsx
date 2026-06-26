import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';

export default function Placeholder({ title }: { title: string }) {
    return (
        <PortalAppShell title={title}>
            <Head title={title} />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="Portal module"
                    title={title}
                    description="This module is protected by AlignEx role-based access control and is ready for its feature workflow."
                />
            </section>
        </PortalAppShell>
    );
}
