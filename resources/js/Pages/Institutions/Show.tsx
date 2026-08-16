import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Building2, BookOpen, GraduationCap, MapPin } from 'lucide-react';
import { PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function InstitutionShow({ institution, can }: { institution: any; can: { update: boolean } }) {
    return (
        <PortalAppShell title={institution.name}>
            <Head title={institution.name} />
            <PageHeader
                eyebrow="Institution"
                title={institution.name}
                description={institution.address || 'Higher education institution profile.'}
                actions={
                    <div className="flex gap-2">
                        <Button asChild variant="secondary">
                            <Link href="/institutions"><ArrowLeft className="h-4 w-4" />Back</Link>
                        </Button>
                        {can.update && (
                            <Button asChild>
                                <Link href={`/institutions/${institution.id}/edit`}>Edit</Link>
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Faculties" value={institution.faculties_count ?? 0} icon={Building2} />
                <StatCard label="Departments" value={institution.departments_count ?? 0} icon={GraduationCap} />
                <StatCard label="Programmes" value={institution.programmes_count ?? 0} icon={BookOpen} />
                <StatCard label="Courses" value={institution.courses_count ?? 0} icon={MapPin} />
            </div>

            <div className="mt-6 rounded-md border border-border bg-white p-5 shadow-sm">
                <h2 className="font-semibold text-slateDark">Institution details</h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <Detail label="Code" value={institution.code} />
                    <Detail label="Type" value={institution.institution_type} />
                    <Detail label="Email" value={institution.email} />
                    <Detail label="Phone" value={institution.phone || 'N/A'} />
                    <Detail label="Status" value={<StatusBadge label={institution.status} tone={institution.status === 'active' ? 'success' : 'neutral'} />} />
                    <Detail label="Location" value={institution.address || 'N/A'} />
                </div>
                {institution.description && (
                    <div className="mt-4">
                        <p className="text-sm font-semibold text-slateDark">Description</p>
                        <p className="mt-1 text-sm text-slate-600">{institution.description}</p>
                    </div>
                )}
            </div>
        </PortalAppShell>
    );
}

function StatCard({ label, value, icon: Icon }: { label: string; value: number; icon: any }) {
    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-slate-500">{label}</p>
                    <p className="mt-2 text-2xl font-bold text-slateDark">{value}</p>
                </div>
                <div className="rounded-md bg-primary/10 p-2 text-primary"><Icon className="h-5 w-5" /></div>
            </div>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string | React.ReactNode }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
            <div className="mt-1 text-sm text-slateDark">{value}</div>
        </div>
    );
}
