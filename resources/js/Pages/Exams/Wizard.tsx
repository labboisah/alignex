import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Plus, Save, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { AlertBanner, FormSection } from '@/Components/Platform';
import { Exam, ExamSettings, ExamSubject, SelectOption, SubjectOption, TenantOption } from './types';

type ExamFormData = {
    organization_id: string;
    center_id: string;
    school_id: string;
    title: string;
    exam_code: string;
    exam_type: string;
    mode: string;
    delivery_mode: string;
    start_at: string;
    end_at: string;
    duration_minutes: string;
    pass_mark: string;
    status: string;
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
};

export function ExamWizard({ exam, subjects, organizations = [], schools = [], centers = [], examTypes, modes, deliveryModes, statuses, submitLabel }: { exam?: Exam; subjects: { data: SubjectOption[] }; organizations?: TenantOption[]; schools?: TenantOption[]; centers?: TenantOption[]; examTypes: SelectOption[]; modes: SelectOption[]; deliveryModes: SelectOption[]; statuses: SelectOption[]; submitLabel: string }) {
    const [step, setStep] = useState(1);
    const { data, setData, post, patch, processing, errors } = useForm<ExamFormData>({
        organization_id: exam?.organization_id ? String(exam.organization_id) : '',
        center_id: exam?.center_id ? String(exam.center_id) : '',
        school_id: exam?.school_id ? String(exam.school_id) : '',
        title: exam?.title ?? '',
        exam_code: exam?.exam_code ?? '',
        exam_type: exam?.exam_type ?? 'secondary',
        mode: exam?.mode ?? 'traditional',
        delivery_mode: exam?.delivery_mode ?? 'online',
        start_at: exam?.start_at ?? '',
        end_at: exam?.end_at ?? '',
        duration_minutes: String(exam?.duration_minutes ?? ''),
        pass_mark: String(exam?.pass_mark ?? ''),
        status: exam?.status ?? 'draft',
        subjects: exam?.subjects?.length ? exam.subjects : [{ subject_id: '', number_of_questions: 1, marks_per_question: 1, duration_minutes: '' }],
        settings: { ...defaultSettings, ...(exam?.settings ?? {}) },
    });

    const totals = useMemo(() => {
        const questions = data.subjects.reduce((sum, subject) => sum + Number(subject.number_of_questions || 0), 0);
        const marks = data.subjects.reduce((sum, subject) => sum + Number(subject.number_of_questions || 0) * Number(subject.marks_per_question || 0), 0);
        return { questions, marks };
    }, [data.subjects]);

    const errorKeys = Object.keys(errors);
    const hasErrors = errorKeys.length > 0;

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

    const addSubject = () => setData('subjects', [...data.subjects, { subject_id: '', number_of_questions: 1, marks_per_question: 1, duration_minutes: '' }]);
    const removeSubject = (index: number) => setData('subjects', data.subjects.filter((_, rowIndex) => rowIndex !== index));

    return (
        <form onSubmit={submit}>
            {hasErrors && (
                <AlertBanner
                    tone="danger"
                    title="Exam could not be saved"
                    message="Please review the highlighted fields and try again."
                    className="mb-5"
                />
            )}

            <div className="mb-5 grid gap-2 md:grid-cols-4">
                {['Basic Information', 'Subjects', 'Settings', 'Review'].map((label, index) => (
                    <button key={label} type="button" onClick={() => setStep(index + 1)} className={`rounded-md border px-3 py-2 text-left text-sm font-semibold ${step === index + 1 ? 'border-primary bg-green-50 text-primary' : 'border-border bg-white text-slate-600'}`}>
                        Step {index + 1}: {label}
                    </button>
                ))}
            </div>

            {step === 1 && (
                <FormSection title="Basic Information" description="Define exam identity, schedule, timing, and delivery mode.">
                    {!exam && (organizations.length > 0 || schools.length > 0 || centers.length > 0) && (
                        <div className="grid gap-4 md:grid-cols-3">
                            <ScopeSelect label="Organization" value={data.organization_id} options={organizations} onChange={(value) => setData({ ...data, organization_id: value, school_id: '', center_id: '' })} error={errors.organization_id} />
                            <ScopeSelect label="School" value={data.school_id} options={schools} onChange={(value) => setData({ ...data, school_id: value, organization_id: '', center_id: '' })} error={errors.school_id} />
                            <ScopeSelect label="Center" value={data.center_id} options={centers} onChange={(value) => setData({ ...data, center_id: value, organization_id: '', school_id: '' })} error={errors.center_id} />
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Title" error={errors.title}><input className={inputClass} value={data.title} onChange={(event) => setData('title', event.target.value)} required /></Field>
                        <Field label="Exam Code" error={errors.exam_code}><input className={inputClass} value={data.exam_code} onChange={(event) => setData('exam_code', event.target.value.toUpperCase())} required /></Field>
                        <SelectField label="Exam Type" value={data.exam_type} options={examTypes} onChange={(value) => setData('exam_type', value)} error={errors.exam_type} />
                        <SelectField label="Mode" value={data.mode} options={modes} onChange={(value) => setData('mode', value)} error={errors.mode} />
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
                <FormSection title="Subjects" description="Add one or more subjects and configure question counts and marks.">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div className="text-sm font-semibold text-slate-600">Total Questions: {totals.questions} | Total Marks: {totals.marks}</div>
                        <Button type="button" onClick={addSubject}><Plus className="h-4 w-4" />Add Subject</Button>
                    </div>
                    {errors.subjects && <div className="mb-2 text-sm text-danger">{errors.subjects}</div>}
                    <div className="space-y-3">
                        {data.subjects.map((row, index) => (
                            <div key={index} className="grid gap-3 rounded-md border border-border bg-white p-3 md:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
                                <select className={inputClass} value={row.subject_id} onChange={(event) => setSubject(index, { subject_id: event.target.value })} required>
                                    <option value="">Choose subject</option>
                                    {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name} ({subject.code})</option>)}
                                </select>
                                <input className={inputClass} type="number" min="1" value={row.number_of_questions} onChange={(event) => setSubject(index, { number_of_questions: event.target.value })} placeholder="Questions" required />
                                <input className={inputClass} type="number" min="0.01" step="0.01" value={row.marks_per_question} onChange={(event) => setSubject(index, { marks_per_question: event.target.value })} placeholder="Marks each" required />
                                <input className={inputClass} type="number" min="1" value={row.duration_minutes ?? ''} onChange={(event) => setSubject(index, { duration_minutes: event.target.value })} placeholder="Duration optional" />
                                <Button type="button" variant="danger" disabled={data.subjects.length === 1} onClick={() => removeSubject(index)}><Trash2 className="h-4 w-4" /></Button>
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
                    </div>
                </FormSection>
            )}

            {step === 4 && (
                <FormSection title="Review" description="Confirm the exam summary, subject configuration, and settings before saving.">
                    <div className="grid gap-4 md:grid-cols-3">
                        <Summary label="Title" value={data.title || 'Untitled'} />
                        <Summary label="Exam Code" value={data.exam_code || 'N/A'} />
                        <Summary label="Total Marks" value={String(totals.marks)} />
                        <Summary label="Subjects" value={String(data.subjects.length)} />
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
    return <Field label={`${label} Scope`} error={error}><select className={inputClass} value={value} onChange={(event) => onChange(event.target.value)}><option value="">None</option>{options.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></Field>;
}

function Summary({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md border border-border bg-white p-4"><div className="text-sm font-semibold text-slate-500">{label}</div><div className="mt-2 font-bold text-slateDark">{value}</div></div>;
}

function stepForErrors(errors: Record<string, string>) {
    const keys = Object.keys(errors);

    if (keys.some((key) => key.startsWith('subjects'))) {
        return 2;
    }

    if (keys.some((key) => key.startsWith('settings'))) {
        return 3;
    }

    return 1;
}
