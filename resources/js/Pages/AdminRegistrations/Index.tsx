import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Edit, Eye, Power } from 'lucide-react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { AdminRegistration } from './types';

type Props = {
    registrations: {
        data: AdminRegistration[];
    };
};

export default function AdminRegistrationsIndex({ registrations }: Props) {
    return (
        <PortalAppShell title="Applications">
            <Head title="Applications" />
            <PageHeader
                eyebrow="Review"
                title="Applications"
                description="Review organization, school, and CBT center applications before login access is created."
            />
            <DataTable<AdminRegistration>
                rows={registrations.data}
                emptyTitle="No applications found"
                columns={[
                    { key: 'entity_name', header: 'Name', render: (registration) => <span className="font-semibold text-slateDark">{registration.entity_name}</span> },
                    { key: 'entity_type_label', header: 'Type' },
                    { key: 'entity_code', header: 'Code' },
                    { key: 'contact_person', header: 'Contact Person' },
                    { key: 'entity_email', header: 'Email' },
                    { key: 'phone', header: 'Phone', render: (registration) => registration.phone || 'N/A' },
                    { key: 'capacity', header: 'Capacity', render: (registration) => registration.capacity ? String(registration.capacity) : 'N/A' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (registration) => (
                            <StatusBadge
                                label={registration.status_label}
                                tone={registration.status === 'approved' ? 'success' : registration.status === 'rejected' ? 'danger' : registration.status === 'deactivated' ? 'neutral' : 'warning'}
                            />
                        ),
                    },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (registration) => (
                            <div className="flex flex-wrap gap-2">
                                <Button asChild type="button" variant="secondary" className="h-9 px-3">
                                    <Link href={`/admin-registrations/${registration.id}`}>
                                        <Eye className="h-4 w-4" />
                                        View
                                    </Link>
                                </Button>
                                {registration.status === 'pending' ? (
                                    <Button asChild type="button" variant="secondary" className="h-9 px-3">
                                        <Link href={`/admin-registrations/${registration.id}/edit`}>
                                            <Edit className="h-4 w-4" />
                                            Edit
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button type="button" variant="secondary" className="h-9 px-3" disabled>
                                        <Edit className="h-4 w-4" />
                                        Edit
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    className="h-9 px-3"
                                    disabled={registration.status !== 'pending'}
                                    onClick={() => router.patch(`/admin-registrations/${registration.id}/approve`, { review_notes: '' })}
                                >
                                    <CheckCircle2 className="h-4 w-4" />
                                    Approve
                                </Button>
                                <Button
                                    type="button"
                                    variant="danger"
                                    className="h-9 px-3"
                                    disabled={registration.status !== 'approved'}
                                    onClick={() => router.patch(`/admin-registrations/${registration.id}/deactivate`, { review_notes: 'Deactivated from applications list.' })}
                                >
                                    <Power className="h-4 w-4" />
                                    Deactivate
                                </Button>
                            </div>
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}
