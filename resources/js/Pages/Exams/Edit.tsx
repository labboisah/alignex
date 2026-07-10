import { Head, usePage } from '@inertiajs/react';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { ExamWizard } from './Wizard';
import { Exam, SelectOption, SubjectOption, TenantOption } from './types';

export default function EditExam({ exam, ...props }: { exam: { data: Exam }; subjects: { data: SubjectOption[] }; organizations: TenantOption[]; schools: TenantOption[]; centers: TenantOption[]; secondarySchools?: TenantOption[]; professionalSchools?: TenantOption[]; cbtCenters?: TenantOption[]; academicSessions?: TenantOption[]; academicTerms?: TenantOption[]; schoolClasses?: TenantOption[]; studentGroups?: TenantOption[]; programmes?: TenantOption[]; courses?: TenantOption[]; modules?: TenantOption[]; trainingBatches?: TenantOption[]; participantCandidates?: TenantOption[]; students?: TenantOption[]; candidateGroups?: TenantOption[]; questionBanks?: TenantOption[]; examTypes: SelectOption[]; examCategories?: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[] }) {
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isTeacher = auth.user?.role === 'teacher';

    return (
        <PortalAppShell title={isTeacher ? 'Edit Assessment' : 'Edit Exam'}>
            <Head title={isTeacher ? 'Edit Assessment' : 'Edit Exam'} />
            <section className="mx-auto max-w-6xl">
                <PageHeader eyebrow={isTeacher ? 'Assessments' : 'Exams'} title={isTeacher ? 'Edit Assessment' : 'Edit Exam Wizard'} description="Update setup, subjects, settings, and review changes." />
                <ExamWizard exam={exam.data} {...props} submitLabel={isTeacher ? 'Save Assessment' : 'Save Changes'} />
            </section>
        </PortalAppShell>
    );
}
