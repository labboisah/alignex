import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ExamWizard } from './Wizard';
import { SelectOption, SubjectOption, TenantOption } from './types';

export default function CreateExam(props: { subjects: { data: SubjectOption[] }; organizations: TenantOption[]; schools: TenantOption[]; centers: TenantOption[]; examTypes: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[] }) {
    return (
        <PortalAppShell title="Create Exam">
            <Head title="Create Exam" />
            <section className="mx-auto max-w-6xl">
                <PageHeader eyebrow="Exams" title="Create Exam Wizard" description="Build exam basics, subject rules, settings, and review before saving." />
                <ExamWizard {...props} submitLabel="Save Exam" />
            </section>
        </PortalAppShell>
    );
}
