import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ExamWizard } from './Wizard';
import { SelectOption, SubjectOption, TenantOption } from './types';

export default function CreateExam(props: { subjects: { data: SubjectOption[] }; organizations: TenantOption[]; schools: TenantOption[]; centers: TenantOption[]; secondarySchools?: TenantOption[]; professionalSchools?: TenantOption[]; cbtCenters?: TenantOption[]; academicSessions?: TenantOption[]; academicTerms?: TenantOption[]; schoolClasses?: TenantOption[]; studentGroups?: TenantOption[]; programmes?: TenantOption[]; courses?: TenantOption[]; modules?: TenantOption[]; trainingBatches?: TenantOption[]; participantCandidates?: TenantOption[]; students?: TenantOption[]; candidateGroups?: TenantOption[]; questionBanks?: TenantOption[]; examTypes: SelectOption[]; examCategories?: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[] }) {
    return (
        <PortalAppShell title="Create Exam">
            <Head title="Create Exam" />
            <section className="mx-auto max-w-6xl">
                <PageHeader eyebrow="Exams" title="Create Exam Wizard" description="Configure participants, question selection, timing, and settings for the selected context." />
                <ExamWizard {...props} submitLabel="Save Exam" />
            </section>
        </PortalAppShell>
    );
}
