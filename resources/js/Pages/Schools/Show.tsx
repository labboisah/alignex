import { Head, Link, router } from '@inertiajs/react';
import { Building2, LucideIcon, Mail, MapPin, Pencil, Phone, Power } from 'lucide-react';
import { PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { School } from './types';

type Props = {
    school: {
        data: School;
    };
    can: {
        update: boolean;
        deactivate: boolean;
    };
};

export default function ShowSchool({ school, can }: Props) {
    const record = school.data;

    return (
        <PortalAppShell title={record.name}>
            <Head title={record.name} />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="School"
                    title={record.name}
                    description="School profile and examination administration scope."
                    actions={
                        <>
                            <ProtectedAction allowed={can.update}>
                                <Button asChild type="button" variant="secondary">
                                    <Link href={`/schools/${record.id}/edit`}>
                                        <Pencil className="h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>
                            </ProtectedAction>
                            <ProtectedAction allowed={can.deactivate && record.status !== 'inactive'}>
                                <Button type="button" variant="danger" onClick={() => router.patch(`/schools/${record.id}/deactivate`, {}, { preserveScroll: true })}>
                                    <Power className="h-4 w-4" />
                                    Deactivate
                                </Button>
                            </ProtectedAction>
                        </>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <Metric label="Code" value={record.code} />
                    <Metric label="Capacity" value={String(record.capacity)} />
                    <div className="rounded-md border border-border bg-white p-4 shadow-sm">
                        <div className="text-sm font-semibold text-slate-500">Status</div>
                        <div className="mt-3">
                            <StatusBadge label={record.status_label} tone={record.status === 'active' ? 'success' : 'neutral'} />
                        </div>
                    </div>
                </div>

                <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-slateDark">School Details</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <Detail icon={Building2} label="Contact Person" value={record.contact_person} />
                        <Detail icon={Mail} label="Email" value={record.email} />
                        <Detail icon={Phone} label="Phone" value={record.phone || 'N/A'} />
                        <Detail icon={MapPin} label="Location" value={record.location} />
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="text-sm font-semibold text-slate-500">{label}</div>
            <div className="mt-2 text-lg font-bold text-slateDark">{value}</div>
        </div>
    );
}

function Detail({ label, value, icon: Icon }: { label: string; value: string; icon: LucideIcon }) {
    return (
        <div className="flex gap-3 rounded-md border border-border p-4">
            <Icon className="mt-0.5 h-4 w-4 text-primary" />
            <div>
                <div className="text-sm font-semibold text-slate-500">{label}</div>
                <div className="mt-1 text-sm text-slateDark">{value}</div>
            </div>
        </div>
    );
}
