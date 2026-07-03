import { Head } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ExamWizard } from './Wizard';
import { Exam, SelectOption, SubjectOption, TenantOption } from './types';

export default function EditExam({ exam, ...props }: { exam: { data: Exam }; subjects: { data: SubjectOption[] }; organizations: TenantOption[]; schools: TenantOption[]; centers: TenantOption[]; secondarySchools?: TenantOption[]; professionalSchools?: TenantOption[]; cbtCenters?: TenantOption[]; academicSessions?: TenantOption[]; academicTerms?: TenantOption[]; schoolClasses?: TenantOption[]; studentGroups?: TenantOption[]; programmes?: TenantOption[]; courses?: TenantOption[]; modules?: TenantOption[]; trainingBatches?: TenantOption[]; participantCandidates?: TenantOption[]; students?: TenantOption[]; candidateGroups?: TenantOption[]; questionBanks?: TenantOption[]; examTypes: SelectOption[]; examCategories?: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[] }) {
    return (
        <PortalAppShell title="Edit Exam">
            <Head title="Edit Exam" />
            <section className="mx-auto max-w-6xl">
                <PageHeader eyebrow="Exams" title="Edit Exam Wizard" description="Update exam setup, subjects, settings, and review changes." />
                <ExamWizard exam={exam.data} {...props} submitLabel="Save Changes" />
            </section>
        </PortalAppShell>
    );
}
