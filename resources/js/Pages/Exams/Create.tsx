import { Head, usePage } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ExamWizard } from './Wizard';
import { SelectOption, SubjectOption, TenantOption } from './types';

export default function CreateExam(props: { subjects: { data: SubjectOption[] }; organizations: TenantOption[]; schools: TenantOption[]; centers: TenantOption[]; secondarySchools?: TenantOption[]; professionalSchools?: TenantOption[]; cbtCenters?: TenantOption[]; academicSessions?: TenantOption[]; academicTerms?: TenantOption[]; schoolClasses?: TenantOption[]; studentGroups?: TenantOption[]; programmes?: TenantOption[]; courses?: TenantOption[]; modules?: TenantOption[]; trainingBatches?: TenantOption[]; participantCandidates?: TenantOption[]; students?: TenantOption[]; candidateGroups?: TenantOption[]; questionBanks?: TenantOption[]; examTypes: SelectOption[]; examCategories?: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[] }) {
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isAssessmentRole = auth.user?.role === 'teacher' || auth.user?.role === 'facilitator';

    return (
        <PortalAppShell title={isAssessmentRole ? 'Create Assessment' : 'Create Exam'}>
            <Head title={isAssessmentRole ? 'Create Assessment' : 'Create Exam'} />
            <section className="mx-auto max-w-6xl">
                <PageHeader eyebrow={isAssessmentRole ? 'Assessments' : 'Exams'} title={isAssessmentRole ? 'Create Assessment' : 'Create Exam Wizard'} description="Configure participants, question selection, timing, and settings for the selected context." />
                <ExamWizard {...props} submitLabel={isAssessmentRole ? 'Save Assessment' : 'Save Exam'} />
            </section>
        </PortalAppShell>
    );
}
