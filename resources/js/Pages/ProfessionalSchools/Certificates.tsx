import { Head } from '@inertiajs/react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';

type Certificate = {
    id: string;
    serial_number: string;
    candidate_name?: string | null;
    registration_number?: string | null;
    exam_title?: string | null;
    exam_code?: string | null;
    status: 'issued' | 'revoked' | string;
    issued_at?: string | null;
    expires_at?: string | null;
};

export default function Certificates({ professionalSchool, certificates }: { professionalSchool: any; certificates: Certificate[] }) {
    return (
        <PortalAppShell title="Certificates">
            <Head title="Certificates" />
            <PageHeader
                eyebrow={professionalSchool.name}
                title="Available Certificates"
                description="Issued certificates generated from professional and certification exams."
            />
            <DataTable
                rows={certificates}
                emptyTitle="No certificates available"
                columns={[
                    { key: 'serial_number', header: 'Serial' },
                    { key: 'candidate_name', header: 'Candidate', render: (row) => row.candidate_name ?? 'N/A' },
                    { key: 'registration_number', header: 'Registration', render: (row) => row.registration_number ?? 'N/A' },
                    { key: 'exam_title', header: 'Exam', render: (row) => row.exam_title ? `${row.exam_title} (${row.exam_code ?? 'N/A'})` : 'N/A' },
                    { key: 'issued_at', header: 'Issued', render: (row) => row.issued_at ? new Date(row.issued_at).toLocaleDateString() : 'N/A' },
                    { key: 'expires_at', header: 'Expires', render: (row) => row.expires_at ? new Date(row.expires_at).toLocaleDateString() : 'No expiry' },
                    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} tone={row.status === 'issued' ? 'success' : 'danger'} /> },
                ]}
            />
        </PortalAppShell>
    );
}
