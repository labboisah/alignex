import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus, Save, Trash2, TriangleAlert } from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { FormSection } from '@/Components/Platform';
import { CurrentContext, Exam, ExamSettings, ExamSubject, SelectOption, SubjectOption, TenantOption } from './types';

type ExamFormData = {
    exam_owner_type: string;
    organization_id: string;
    center_id: string;
    school_id: string;
    secondary_school_id: string;
    professional_school_id: string;
    cbt_center_id: string;
    academic_session_id: string;
    term_id: string;
    school_class_id: string;
    student_group_id: string;
    subject_id: string;
    programme_id: string;
    course_id: string;
    module_id: string;
    training_batch_id: string;
    title: string;
    exam_code: string;
    exam_type: string;
    exam_category: string;
    mode: string;
    delivery_mode: string;
    start_at: string;
    end_at: string;
    duration_minutes: string;
    pass_mark: string;
    status: string;
    question_bank_id: string;
    candidate_group_id: string;
    candidate_group_ids: string[];
    candidate_ids: string[];
    subjects: ExamSubject[];
    settings: ExamSettings;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

const defaultSettings: ExamSettings = {
    shuffle_questions: true,
    shuffle_options: true,
    show_result_immediately: false,
    allow_back_navigation: true,
    require_webcam: false,
    require_fullscreen: true,
    max_tab_switches: 3,
    negative_marking: false,
    negative_mark_value: 0,
    bind_device: false,
    allow_retake: false,
    certificate_auto_generate: false,
    attempt_limit: 1,
    adaptive_start_difficulty: 'medium',
    adaptive_step_policy: 'simple',
};

export function ExamWizard({ exam, subjects, organizations = [], schools = [], centers = [], secondarySchools = [], professionalSchools = [], cbtCenters = [], academicSessions = [], academicTerms = [], studentGroups = [], programmes = [], courses = [], modules = [], trainingBatches = [], participantCandidates = [], cbtCandidates = [], questionGroups = [], candidateGroups = [], questionBanks = [], examTypes, examCategories = [], modes, deliveryModes, statuses, submitLabel }: { exam?: Exam; subjects: { data: SubjectOption[] }; organizations?: TenantOption[]; schools?: TenantOption[]; centers?: TenantOption[]; secondarySchools?: TenantOption[]; professionalSchools?: TenantOption[]; cbtCenters?: TenantOption[]; academicSessions?: TenantOption[]; academicTerms?: TenantOption[]; studentGroups?: TenantOption[]; programmes?: TenantOption[]; courses?: TenantOption[]; modules?: TenantOption[]; trainingBatches?: TenantOption[]; participantCandidates?: TenantOption[]; cbtCandidates?: TenantOption[]; questionGroups?: TenantOption[]; candidateGroups?: TenantOption[]; questionBanks?: TenantOption[]; examTypes: SelectOption[]; examCategories?: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[]; submitLabel: string }) {
    const [step, setStep] = useState(1);
    const auth = usePage().props.auth as { user?: { role?: string } };
    const isAssessmentRole = auth.user?.role === 'teacher' || auth.user?.role === 'facilitator';
    const currentContext = (usePage().props.current_context ?? null) as CurrentContext | null;
    const inferredContext = contextFrom(exam, currentContext, { organizations, secondarySchools, professionalSchools, cbtCenters, academicSessions, programmes, cbtCandidates, questionBanks });
    const { data, setData, post, patch, processing, errors } = useForm<ExamFormData>({
        exam_owner_type: inferredContext.type,
        organization_id: exam?.organization_id ? String(exam.organization_id) : inferredContext.organization_id,
        center_id: exam?.center_id ? String(exam.center_id) : '',
        school_id: exam?.school_id ? String(exam.school_id) : inferredContext.school_id,
        secondary_school_id: exam?.secondary_school_id ? String(exam.secondary_school_id) : inferredContext.secondary_school_id,
        professional_school_id: exam?.professional_school_id ? String(exam.professional_school_id) : inferredContext.professional_school_id,
        cbt_center_id: exam?.cbt_center_id ? String(exam.cbt_center_id) : inferredContext.cbt_center_id,
        academic_session_id: exam?.academic_session_id ? String(exam.academic_session_id) : String(academicSessions.find((row) => row.is_active)?.id ?? academicSessions[0]?.id ?? ''),
        term_id: exam?.academic_term_id ? String(exam.academic_term_id) : String(academicTerms.find((row) => row.is_active)?.id ?? academicTerms[0]?.id ?? ''),
        school_class_id: exam?.school_class_id ? String(exam.school_class_id) : '',
        student_group_id: exam?.student_group_id ? String(exam.student_group_id) : '',
        subject_id: exam?.subject_id ? String(exam.subject_id) : '',
        programme_id: exam?.programme_id ? String(exam.programme_id) : String(programmes[0]?.id ?? ''),
        course_id: exam?.course_id ? String(exam.course_id) : '',
        module_id: exam?.module_id ? String(exam.module_id) : '',
        training_batch_id: exam?.training_batch_id ? String(exam.training_batch_id) : String(trainingBatches[0]?.id ?? ''),
        title: exam?.title ?? '',
        exam_code: exam?.exam_code ?? '',
        exam_type: exam?.exam_type ?? (isAssessmentRole ? 'assessment' : defaultExamType(inferredContext.type)),
        exam_category: exam?.exam_category ?? (isAssessmentRole ? 'assessment' : defaultExamCategory(inferredContext.type)),
        mode: exam?.mode ?? (inferredContext.type === 'secondary_school' ? 'traditional' : 'traditional'),
        delivery_mode: exam?.delivery_mode ?? 'online',
        start_at: exam?.start_at ?? '',
        end_at: exam?.end_at ?? '',
        duration_minutes: String(exam?.duration_minutes ?? ''),
        pass_mark: String(exam?.pass_mark ?? ''),
        status: exam?.status ?? 'draft',
        question_bank_id: exam?.question_bank_id ? String(exam.question_bank_id) : '',
        candidate_group_id: exam?.candidate_group_id ? String(exam.candidate_group_id) : '',
        candidate_group_ids: exam?.candidate_group_ids?.length ? exam.candidate_group_ids.map((id) => String(id)) : (exam?.candidate_group_id ? [String(exam.candidate_group_id)] : []),
        candidate_ids: exam?.candidate_ids ?? [],
        subjects: exam?.subjects?.length ? exam.subjects : [emptyPaperRow()],
        settings: { ...defaultSettings, ...(exam?.settings ?? {}) },
    });

    const totals = useMemo(() => {
        const questions = data.subjects.reduce((sum, subject) => sum + Number(subject.number_of_questions || 0), 0);
        const marks = data.subjects.reduce((sum, subject) => sum + Number(subject.number_of_questions || 0) * Number(subject.marks_per_question || 0), 0);
        return { questions, marks };
    }, [data.subjects]);

    const errorKeys = Object.keys(errors);
    const hasErrors = errorKeys.length > 0;
    const errorSummary = errorKeys.map((key) => errorMessageFor(key, errors[key as keyof typeof errors] ?? 'Please review this field.'));
    const ownerContext = data.exam_owner_type || inferredContext.type || (data.secondary_school_id ? 'secondary_school' : data.professional_school_id ? 'professional_school' : data.cbt_center_id ? 'cbt_center' : data.organization_id ? 'organization' : '');
    const isSecondaryExam = ownerContext === 'secondary_school';
    const isProfessionalExam = ownerContext === 'professional_school';
    const isCbtExam = ownerContext === 'cbt_center';
    const isOrganizationExam = ownerContext === 'organization';
    const candidateOptions = participantCandidates;
    const allowedCategories = isAssessmentRole
        ? examCategories.filter((category) => category.value === 'assessment')
        : isSecondaryExam
        ? examCategories.filter((category) => ['terminal', 'assessment'].includes(category.value))
        : isProfessionalExam
            ? examCategories.filter((category) => ['professional', 'certification', 'practice'].includes(category.value))
            : examCategories.filter((category) => category.value !== 'terminal');
    const allowedModes = isSecondaryExam ? modes.filter((mode) => mode.value === 'traditional') : modes;
    const paperLabel = isProfessionalExam ? 'Module' : 'Subject';
    const paperLabelPlural = isProfessionalExam ? 'Modules' : 'Subjects';
    const paperStepLabel = isProfessionalExam ? 'Course / Module Paper' : 'Subjects';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = { onError: (nextErrors: Record<string, string>) => setStep(stepForErrors(nextErrors)) };
        exam ? patch(`/exams/${exam.id}`, options) : post('/exams', options);
    };

    const setSubject = (index: number, next: Partial<ExamSubject>) => {
        const rows = [...data.subjects];
        rows[index] = { ...rows[index], ...next };
        setData('subjects', rows);
    };

    const addSubject = () => setData('subjects', [...data.subjects, emptyPaperRow()]);
    const removeSubject = (index: number) => setData('subjects', data.subjects.filter((_, rowIndex) => rowIndex !== index));

    return (
        <form onSubmit={submit}>
            {hasErrors && (
                <div className="mb-5 rounded-md border border-red-200 bg-red-50 p-4 text-danger">
                    <div className="flex gap-3">
                        <TriangleAlert className="h-5 w-5 shrink-0" />
                        <div>
                            <div className="text-sm font-semibold">Please fix these {isAssessmentRole ? 'assessment' : 'exam'} setup details</div>
                            <ul className="mt-2 space-y-1 text-sm leading-6">
                                {errorSummary.slice(0, 6).map((message) => <li key={message}>{message}</li>)}
                            </ul>
                            {errorSummary.length > 6 && <div className="mt-2 text-sm">There are {errorSummary.length - 6} more field errors below.</div>}
                        </div>
                    </div>
                </div>
            )}

            <div className="mb-5 grid gap-2 md:grid-cols-4">
                {['Basic Information', paperStepLabel, 'Settings', 'Review'].map((label, index) => (
                    <button key={label} type="button" onClick={() => setStep(index + 1)} className={`rounded-md border px-3 py-2 text-left text-sm font-semibold ${step === index + 1 ? 'border-primary bg-green-50 text-primary' : 'border-border bg-white text-slate-600'}`}>
                        Step {index + 1}: {label}
                    </button>
                ))}
            </div>

            {step === 1 && (
                <FormSection title="Basic Information" description={`This ${isAssessmentRole ? 'assessment' : 'exam'} will be created under ${inferredContext.name}.`}>
                    <input type="hidden" name="exam_owner_type" value={data.exam_owner_type} />
                    <input type="hidden" name="organization_id" value={data.organization_id} />
                    <input type="hidden" name="school_id" value={data.school_id} />
                    <input type="hidden" name="secondary_school_id" value={data.secondary_school_id} />
                    <input type="hidden" name="professional_school_id" value={data.professional_school_id} />
                    <input type="hidden" name="cbt_center_id" value={data.cbt_center_id} />
                    {errors.exam_owner_type && <div className="mb-4 text-sm text-danger">{errors.exam_owner_type}</div>}
                    {isSecondaryExam && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <ScopeSelect label="Academic Session" value={data.academic_session_id} options={academicSessions} onChange={(value) => setData('academic_session_id', value)} error={errors.academic_session_id} />
                            <ScopeSelect label="Term" value={data.term_id} options={academicTerms} onChange={(value) => setData('term_id', value)} error={errors.term_id} />
                            <ScopeSelect label="Student Group" value={data.student_group_id} options={studentGroups} onChange={(value) => setData('student_group_id', value)} error={errors.student_group_id} />
                        </div>
                    )}
                    {isProfessionalExam && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <ScopeSelect label="Programme" value={data.programme_id} options={programmes} onChange={(value) => setData({ ...data, programme_id: value, training_batch_id: '', subjects: data.subjects.map((row) => ({ ...row, course_id: '', module_id: '', question_bank_id: '', subject_id: '' })) })} error={errors.programme_id} />
                            <ScopeSelect label="Batch" value={data.training_batch_id} options={batchesForProgramme(trainingBatches, data.programme_id)} onChange={(value) => setData('training_batch_id', value)} error={errors.training_batch_id} />
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Title" error={errors.title}><input className={inputClass} value={data.title} onChange={(event) => setData('title', event.target.value)} required /></Field>
                        <Field label={isAssessmentRole ? 'Assessment Code' : 'Exam Code'} error={errors.exam_code}><input className={inputClass} value={data.exam_code} onChange={(event) => setData('exam_code', event.target.value.toUpperCase())} required /></Field>
                        {isAssessmentRole ? (
                            <>
                                <input type="hidden" value={data.exam_type} />
                                <input type="hidden" value={data.exam_category} />
                                <Summary label="Type" value="Assessment" />
                                <Summary label="Category" value="Assessment" />
                            </>
                        ) : (
                            <>
                                <SelectField label="Exam Type" value={data.exam_type} options={examTypes} onChange={(value) => setData('exam_type', value)} error={errors.exam_type} />
                                <SelectField label="Exam Category" value={data.exam_category} options={allowedCategories.length ? allowedCategories : [{ value: 'general', label: 'General' }]} onChange={(value) => setData('exam_category', value)} error={errors.exam_category} />
                            </>
                        )}
                        <SelectField label="Mode" value={data.mode} options={allowedModes} onChange={(value) => setData('mode', value)} error={errors.mode} />
                        <SelectField label="Delivery Mode" value={data.delivery_mode} options={deliveryModes} onChange={(value) => setData('delivery_mode', value)} error={errors.delivery_mode} />
                        <SelectField label="Status" value={data.status} options={statuses} onChange={(value) => setData('status', value)} error={errors.status} />
                        <Field label="Start At" error={errors.start_at}><input className={inputClass} type="datetime-local" value={data.start_at} onChange={(event) => setData('start_at', event.target.value)} required /></Field>
                        <Field label="End At" error={errors.end_at}><input className={inputClass} type="datetime-local" value={data.end_at} onChange={(event) => setData('end_at', event.target.value)} required /></Field>
                        <Field label="Duration Minutes" error={errors.duration_minutes}><input className={inputClass} type="number" min="1" value={data.duration_minutes} onChange={(event) => setData('duration_minutes', event.target.value)} required /></Field>
                        <Field label="Pass Mark" error={errors.pass_mark}><input className={inputClass} type="number" min="0" step="0.01" value={data.pass_mark} onChange={(event) => setData('pass_mark', event.target.value)} required /></Field>
                    </div>
                </FormSection>
            )}

            {step === 2 && (
                <FormSection title={paperStepLabel} description={isProfessionalExam ? 'Add one or more course/module rows and configure question counts and marks.' : 'Add one or more subjects and configure question counts and marks.'}>
                    {(isCbtExam || isOrganizationExam) && (
                        <div className="mb-4 grid gap-4 rounded-md border border-border bg-white p-4 md:grid-cols-2">
                            <Field label="Candidate Groups" error={errors.candidate_group_ids ?? errors.candidate_group_id}>
                                <select
                                    multiple
                                    className={`${inputClass} min-h-32`}
                                    value={data.candidate_group_ids}
                                    onChange={(event) => {
                                        const groupIds = Array.from(event.target.selectedOptions).map((option) => option.value);
                                        setData({
                                            ...data,
                                            candidate_group_ids: groupIds,
                                            candidate_group_id: groupIds[0] ?? '',
                                            candidate_ids: isCbtExam || groupIds.length > 0 ? [] : data.candidate_ids,
                                        });
                                    }}
                                >
                                    {candidateGroups.map((group) => <option key={group.id} value={group.id}>{group.name}</option>)}
                                </select>
                            </Field>
                            {isCbtExam || data.candidate_group_ids.length > 0 ? (
                                <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm font-semibold text-primary">
                                    Candidates will be fetched automatically from the selected group(s).
                                </div>
                            ) : (
                                <Field label="Candidates" error={errors.candidate_ids}>
                                    <select multiple className={`${inputClass} min-h-32`} value={data.candidate_ids} onChange={(event) => setData('candidate_ids', Array.from(event.target.selectedOptions).map((option) => option.value))}>
                                        {candidateOptions.map((candidate) => <option key={candidate.id} value={candidate.id}>{candidate.name}</option>)}
                                    </select>
                                </Field>
                            )}
                        </div>
                    )}
                    {isProfessionalExam && (
                        <div className="mb-4 rounded-md border border-border bg-white p-4">
                            <div className="text-sm font-semibold text-slateDark">Batch Registration</div>
                            <div className="mt-1 text-sm text-slate-600">
                                {trainingBatches.find((batch) => String(batch.id) === String(data.training_batch_id))?.name ?? 'Choose a batch in Basic Information'} will provide the exam candidates.
                            </div>
                            {errors.training_batch_id && <div className="mt-2 text-sm text-danger">{errors.training_batch_id}</div>}
                        </div>
                    )}
                    {isSecondaryExam && (
                        <div className="mb-4 grid gap-4 rounded-md border border-border bg-white p-4 md:grid-cols-2">
                            <div className="rounded-md border border-green-200 bg-green-50 p-3 text-sm font-semibold text-primary">Students will be assigned automatically from the selected student group.</div>
                            <div className="rounded-md border border-blue-200 bg-blue-50 p-3 text-sm font-semibold text-info">Choose the question bank inside each subject row below.</div>
                        </div>
                    )}
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="text-sm font-semibold text-slate-600">Total Questions: {totals.questions} | Total Marks: {totals.marks}</div>
                        <Button type="button" onClick={addSubject}><Plus className="h-4 w-4" />Add {paperLabel}</Button>
                    </div>
                    {errors.subjects && <div className="mb-2 text-sm text-danger">{errors.subjects}</div>}
                    <div className="space-y-3">
                        {data.subjects.map((row, index) => (
                            <div key={index} className={`grid gap-3 rounded-md border border-border bg-white p-3 ${isProfessionalExam ? 'md:grid-cols-[1fr_1fr_1.2fr_0.7fr_0.7fr_0.7fr_auto]' : 'md:grid-cols-[1.4fr_1.4fr_0.8fr_0.8fr_0.8fr_auto]'}`}>
                                {isProfessionalExam ? (
                                    <>
                                        <Field label="Course" error={fieldError(errors, `subjects.${index}.course_id`)}>
                                            <select className={inputClass} value={String(row.course_id ?? '')} onChange={(event) => setSubject(index, { course_id: event.target.value, module_id: '', question_bank_id: '', question_bank_ids: [], subject_id: '' })} required>
                                                <option value="">Choose course</option>
                                                {coursesForProgramme(courses, data.programme_id).map((course) => <option key={course.id} value={course.id}>{course.name}</option>)}
                                            </select>
                                        </Field>
                                        <Field label="Module" error={fieldError(errors, `subjects.${index}.module_id`)}>
                                            <select className={inputClass} value={String(row.module_id ?? '')} onChange={(event) => setSubject(index, { module_id: event.target.value, question_bank_id: '', question_bank_ids: [], subject_id: '' })} required>
                                                <option value="">Choose module</option>
                                                {modulesForCourse(modules, row.course_id).map((module) => <option key={module.id} value={module.id}>{module.name}</option>)}
                                            </select>
                                        </Field>
                                    </>
                                ) : (
                                    <Field label={paperLabel} error={fieldError(errors, `subjects.${index}.subject_id`)}>
                                        <select className={inputClass} value={row.subject_id} onChange={(event) => setSubject(index, { subject_id: event.target.value, question_bank_id: '', question_bank_ids: [] })} required>
                                            <option value="">Choose {paperLabel.toLowerCase()}</option>
                                            {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                                        </select>
                                    </Field>
                                )}
                                <Field label="Question Banks" error={fieldError(errors, `subjects.${index}.question_bank_id`)}>
                                    <select
                                        multiple
                                        className={`${inputClass} min-h-24`}
                                        value={(row.question_bank_ids ?? (row.question_bank_id ? [row.question_bank_id] : [])).map((value) => String(value))}
                                        onChange={(event) => {
                                            const nextIds = Array.from(event.target.selectedOptions).map((option) => option.value);
                                            const primaryBank = questionBanks.find((option) => String(option.id) === nextIds[0]);
                                            setSubject(index, {
                                                question_bank_ids: nextIds,
                                                question_bank_id: nextIds[0] ?? '',
                                                subject_id: primaryBank?.subject_id ? String(primaryBank.subject_id) : row.subject_id,
                                            });
                                        }}
                                        required
                                    >
                                        {questionBanksForPaperRow(questionBanks, row, isProfessionalExam).map((bank) => <option key={bank.id} value={bank.id}>{bank.name} ({bank.code})</option>)}
                                    </select>
                                    {isProfessionalExam && fieldError(errors, `subjects.${index}.subject_id`) && <span className="mt-1 block text-sm text-danger">{fieldError(errors, `subjects.${index}.subject_id`)}</span>}
                                </Field>
                                <Field label="Questions" error={fieldError(errors, `subjects.${index}.number_of_questions`)}>
                                    <input className={inputClass} type="number" min="1" value={row.number_of_questions} onChange={(event) => setSubject(index, { number_of_questions: event.target.value })} required />
                                </Field>
                                <Field label="Marks Each" error={fieldError(errors, `subjects.${index}.marks_per_question`)}>
                                    <input className={inputClass} type="number" min="0.01" step="0.01" value={row.marks_per_question} onChange={(event) => setSubject(index, { marks_per_question: event.target.value })} required />
                                </Field>
                                <Field label="Duration" error={fieldError(errors, `subjects.${index}.duration_minutes`)}>
                                    <input className={inputClass} type="number" min="1" value={row.duration_minutes ?? ''} onChange={(event) => setSubject(index, { duration_minutes: event.target.value })} placeholder="Optional" />
                                </Field>
                                <div className="flex items-end">
                                    <Button type="button" variant="danger" disabled={data.subjects.length === 1} onClick={() => removeSubject(index)}><Trash2 className="h-4 w-4" /></Button>
                                </div>
                            </div>
                        ))}
                    </div>
                </FormSection>
            )}

            {step === 3 && (
                <FormSection title="Settings" description="Configure navigation, supervision, device, and scoring behavior.">
                    <div className="grid gap-4 md:grid-cols-2">
                        {([
                            ['shuffle_questions', 'Shuffle Questions'],
                            ['shuffle_options', 'Shuffle Options'],
                            ['show_result_immediately', 'Show Result Immediately'],
                            ['allow_back_navigation', 'Allow Back Navigation'],
                            ['require_webcam', 'Require Webcam'],
                            ['require_fullscreen', 'Require Fullscreen'],
                            ['negative_marking', 'Negative Marking'],
                            ['bind_device', 'Bind Device'],
                            ['allow_retake', 'Allow Retake'],
                        ] as [keyof ExamSettings, string][]).map(([key, label]) => (
                            <label key={key} className="flex items-center justify-between rounded-md border border-border bg-white p-3 text-sm font-semibold text-slateDark">
                                {label}
                                <input type="checkbox" checked={Boolean(data.settings[key])} onChange={(event) => setData('settings', { ...data.settings, [key]: event.target.checked })} />
                            </label>
                        ))}
                        <Field label="Max Tab Switches"><input className={inputClass} type="number" min="0" value={data.settings.max_tab_switches} onChange={(event) => setData('settings', { ...data.settings, max_tab_switches: event.target.value })} /></Field>
                        <Field label="Negative Mark Value"><input className={inputClass} type="number" min="0" step="0.01" value={data.settings.negative_mark_value} onChange={(event) => setData('settings', { ...data.settings, negative_mark_value: event.target.value })} /></Field>
                        {isProfessionalExam && <Field label="Attempt Limit"><input className={inputClass} type="number" min="1" value={data.settings.attempt_limit ?? 1} onChange={(event) => setData('settings', { ...data.settings, attempt_limit: event.target.value })} /></Field>}
                        {data.exam_category === 'certification' && (
                            <label className="flex items-center justify-between rounded-md border border-border bg-white p-3 text-sm font-semibold text-slateDark">
                                Certificate After Pass
                                <input type="checkbox" checked={Boolean(data.settings.certificate_auto_generate)} onChange={(event) => setData('settings', { ...data.settings, certificate_auto_generate: event.target.checked })} />
                            </label>
                        )}
                        {data.mode === 'adaptive' && (
                            <>
                                <Field label="Adaptive Start Difficulty"><select className={inputClass} value={data.settings.adaptive_start_difficulty ?? 'medium'} onChange={(event) => setData('settings', { ...data.settings, adaptive_start_difficulty: event.target.value })}><option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option></select></Field>
                                <Field label="Adaptive Policy"><input className={inputClass} value={data.settings.adaptive_step_policy ?? 'simple'} onChange={(event) => setData('settings', { ...data.settings, adaptive_step_policy: event.target.value })} /></Field>
                            </>
                        )}
                    </div>
                </FormSection>
            )}

            {step === 4 && (
                <FormSection title="Review" description={`Confirm the ${isAssessmentRole ? 'assessment' : 'exam'} summary, ${isProfessionalExam ? 'course/module' : 'subject'} configuration, and settings before saving.`}>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Summary label="Title" value={data.title || 'Untitled'} />
                        <Summary label={isAssessmentRole ? 'Assessment Code' : 'Exam Code'} value={data.exam_code || 'N/A'} />
                        <Summary label="Total Marks" value={String(totals.marks)} />
                        <Summary label={paperLabelPlural} value={String(data.subjects.length)} />
                        <Summary label="Questions" value={String(totals.questions)} />
                        <Summary label="Pass Mark" value={data.pass_mark || '0'} />
                    </div>
                </FormSection>
            )}

            <div className="mt-5 flex flex-wrap justify-between gap-2">
                <Button asChild type="button" variant="secondary"><Link href={exam ? `/exams/${exam.id}` : '/exams'}>Cancel</Link></Button>
                <div className="flex gap-2">
                    <Button type="button" variant="secondary" disabled={step === 1} onClick={() => setStep((current) => current - 1)}><ArrowLeft className="h-4 w-4" />Back</Button>
                    {step < 4 ? (
                        <Button type="button" onClick={() => setStep((current) => current + 1)}>Next<ArrowRight className="h-4 w-4" /></Button>
                    ) : (
                        <Button type="submit" disabled={processing}><Save className="h-4 w-4" />{submitLabel}</Button>
                    )}
                </div>
            </div>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block"><span className={labelClass}>{label}</span>{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}

function SelectField({ label, value, options, onChange, error }: { label: string; value: string; options: SelectOption[]; onChange: (value: string) => void; error?: string }) {
    return <Field label={label} error={error}><select className={inputClass} value={value} onChange={(event) => onChange(event.target.value)}>{options.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></Field>;
}

function ScopeSelect({ label, value, options, onChange, error }: { label: string; value: string; options: TenantOption[]; onChange: (value: string) => void; error?: string }) {
    return <Field label={label} error={error}><select className={inputClass} value={value} onChange={(event) => onChange(event.target.value)}><option value="">None</option>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></Field>;
}

function Summary({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md border border-border bg-white p-4"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 font-bold text-slateDark">{value}</div></div>;
}

function fieldError(errors: Record<string, string>, key: string) {
    return errors[key];
}

function emptyPaperRow(): ExamSubject {
    return { subject_id: '', course_id: '', module_id: '', question_bank_id: '', question_bank_ids: [], number_of_questions: 1, marks_per_question: 1, duration_minutes: '' };
}

function coursesForProgramme(courses: TenantOption[], programmeId: string | number | null | undefined) {
    if (!programmeId) {
        return courses;
    }

    return courses.filter((course) => !course.programme_id || String(course.programme_id) === String(programmeId));
}

function modulesForCourse(modules: TenantOption[], courseId: string | number | null | undefined) {
    if (!courseId) {
        return [];
    }

    return modules.filter((module) => String(module.course_id ?? '') === String(courseId));
}

function batchesForProgramme(trainingBatches: TenantOption[], programmeId: string | number | null | undefined) {
    if (!programmeId) {
        return trainingBatches;
    }

    return trainingBatches.filter((batch) => !batch.programme_id || String(batch.programme_id) === String(programmeId));
}

function questionBanksForPaperRow(questionBanks: TenantOption[], row: ExamSubject, isProfessionalExam: boolean) {
    if (isProfessionalExam) {
        return questionBanks.filter((bank) => {
            const courseMatches = !row.course_id || !bank.course_id || String(bank.course_id) === String(row.course_id);
            const moduleMatches = !row.module_id || !bank.module_id || String(bank.module_id) === String(row.module_id);

            return courseMatches && moduleMatches;
        });
    }

    if (!row.subject_id) {
        return [];
    }

    return questionBanks.filter((bank) => String(bank.subject_id ?? '') === String(row.subject_id));
}

function contextFrom(
    exam: Exam | undefined,
    currentContext: CurrentContext | null,
    options: {
        organizations: TenantOption[];
        secondarySchools: TenantOption[];
        professionalSchools: TenantOption[];
        cbtCenters: TenantOption[];
        academicSessions: TenantOption[];
        programmes: TenantOption[];
        cbtCandidates: TenantOption[];
        questionBanks: TenantOption[];
    },
) {
    const type = (exam?.owner_context as CurrentContext['type'] | undefined)
        ?? currentContext?.type
        ?? fallbackContextType(options);
    const id = currentContext?.id ? String(currentContext.id) : fallbackContextId(type, options);
    const name = currentContext?.name ?? contextLabel(type);
    const isLegacySchool = type === 'secondary_school' && currentContext?.source === 'legacy_school';
    const isLegacyCenter = type === 'cbt_center' && currentContext?.source === 'legacy_center';

    return {
        type,
        name,
        organization_id: type === 'organization' ? id : '',
        school_id: isLegacySchool ? id : '',
        secondary_school_id: type === 'secondary_school' && !isLegacySchool ? id : '',
        professional_school_id: type === 'professional_school' ? id : '',
        cbt_center_id: type === 'cbt_center' && !isLegacyCenter ? id : '',
    };
}

function fallbackContextType(options: {
    organizations: TenantOption[];
    secondarySchools: TenantOption[];
    professionalSchools: TenantOption[];
    cbtCenters: TenantOption[];
    academicSessions: TenantOption[];
    programmes: TenantOption[];
    cbtCandidates: TenantOption[];
    questionBanks: TenantOption[];
}): CurrentContext['type'] {
    if (options.secondarySchools.length > 0 || options.academicSessions.length > 0) return 'secondary_school';
    if (options.professionalSchools.length > 0 || options.programmes.length > 0) return 'professional_school';
    if (options.cbtCenters.length > 0 || options.cbtCandidates.length > 0) return 'cbt_center';
    return 'organization';
}

function fallbackContextId(type: CurrentContext['type'], options: { organizations: TenantOption[]; secondarySchools: TenantOption[]; professionalSchools: TenantOption[]; cbtCenters: TenantOption[] }) {
    const rows = {
        organization: options.organizations,
        secondary_school: options.secondarySchools,
        professional_school: options.professionalSchools,
        cbt_center: options.cbtCenters,
    }[type];

    return rows[0]?.id ? String(rows[0].id) : '';
}

function defaultExamType(type: CurrentContext['type']) {
    return type === 'secondary_school' ? 'secondary' : type === 'professional_school' ? 'professional' : 'general';
}

function defaultExamCategory(type: CurrentContext['type']) {
    return type === 'secondary_school' ? 'terminal' : type === 'professional_school' ? 'professional' : 'general';
}

function contextLabel(type: CurrentContext['type']) {
    return {
        organization: 'the selected organization',
        secondary_school: 'the selected secondary school',
        professional_school: 'the selected professional school',
        cbt_center: 'the selected CBT center',
    }[type];
}

function stepForErrors(errors: Record<string, string>) {
    const keys = Object.keys(errors);

    if (keys.some((key) => key.startsWith('subjects') || ['question_bank_id', 'candidate_ids', 'candidate_group_id', 'candidate_group_ids'].includes(key))) {
        return 2;
    }

    if (keys.some((key) => key.startsWith('settings'))) {
        return 3;
    }

    return 1;
}

function errorMessageFor(key: string, message: string) {
    const labels: Record<string, string> = {
        academic_session_id: 'Academic Session',
        term_id: 'Term',
        academic_term_id: 'Term',
        student_group_id: 'Student Group',
        subject_id: 'Subject',
        title: 'Title',
        exam_code: 'Exam Code',
        exam_type: 'Exam Type',
        exam_category: 'Exam Category',
        mode: 'Mode',
        exam_mode: 'Mode',
        delivery_mode: 'Delivery Mode',
        start_at: 'Start Time',
        end_at: 'End Time',
        duration_minutes: 'Duration',
        pass_mark: 'Pass Mark',
        status: 'Status',
        question_bank_id: 'Question Bank',
        candidate_ids: 'Candidates',
        candidate_group_id: 'Candidate Group',
        candidate_group_ids: 'Candidate Groups',
        subjects: 'Paper Setup',
        'subjects.*.subject_id': 'Subject',
        'subjects.*.question_bank_id': 'Question Bank',
        'subjects.*.number_of_questions': 'Questions',
        'subjects.*.marks_per_question': 'Marks Each',
        'subjects.*.duration_minutes': 'Duration',
    };

    const normalizedKey = key.replace(/\.\d+\./g, '.*.');
    const label = labels[key] ?? labels[normalizedKey] ?? (key.startsWith('subjects.') ? 'Paper Setup' : key.replaceAll('_', ' '));

    return `${label}: ${message}`;
}
