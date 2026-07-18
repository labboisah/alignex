import { Head, router, useForm } from '@inertiajs/react';
import { Award, BadgeCheck, CreditCard, Download, FileCheck, Save, Trash2 } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { AlertBanner, DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Exam = { id: string; title: string; exam_code: string; exam_type?: string; pass_mark: number | string; total_marks: number | string; institution_name?: string; route_base?: string };
type Settings = { attempt_limit: number; retake_policy: string; payment_required: boolean; certificate_auto_generate: boolean; certificate_valid_months: number | null; pass_mark: number };
type Template = {
    id: string;
    name: string;
    title: string;
    institution_name?: string | null;
    logo_url?: string | null;
    primary_color?: string | null;
    accent_color?: string | null;
    background_color?: string | null;
    theme?: string | null;
    paper_size?: string | null;
    orientation?: string | null;
    template_key?: string | null;
    use_logo_watermark?: boolean;
    body: string;
    signatory_name?: string | null;
    signatory_title?: string | null;
    is_active: boolean;
};
type Attempt = Record<string, unknown> & { id: string; candidate_name: string; registration_number: string; status: string; score: number; total_marks: number; passed: boolean; payment_status: string; payment_reference?: string | null; attempt_number: number; certificate_serial?: string | null };
type Certificate = Record<string, unknown> & { id: string; serial_number: string; verification_hash: string; status: string; candidate_name: string; registration_number: string; exam_title: string; score: number; total_marks: number; percentage: number; issued_at?: string | null; expires_at?: string | null; verification_url: string; qr_payload: string };

export default function ProfessionalShow({ exam, settings, templates, attempts, certificates }: { exam: Exam; settings: Settings; templates: Template[]; attempts: Attempt[]; certificates: Certificate[] }) {
    const activeTemplate = templates.find((template) => template.is_active) ?? templates[0] ?? null;

    return (
        <PortalAppShell title={`Certification - ${exam.title}`}>
            <Head title={`Certification - ${exam.title}`} />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Certification"
                    title={exam.title}
                    description={`${exam.exam_code} certification, retake, and payment administration.`}
                    backHref={`/exams/${exam.id}`}
                />
                <div className="grid gap-5 xl:grid-cols-[24rem_minmax(0,1fr)]">
                    <div className="space-y-5">
                        <SettingsPanel exam={exam} settings={settings} />
                        <TemplatePanel exam={exam} template={activeTemplate} />
                    </div>
                    <div className="space-y-5">
                        <PaymentPanel exam={exam} attempts={attempts} />
                        <CertificatesPanel exam={exam} certificates={certificates} />
                        <TemplatesList exam={exam} templates={templates} />
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}

function SettingsPanel({ exam, settings }: { exam: Exam; settings: Settings }) {
    const { data, setData, patch, processing, errors } = useForm({
        pass_mark: String(settings.pass_mark ?? exam.pass_mark ?? ''),
        attempt_limit: String(settings.attempt_limit ?? 1),
        retake_policy: settings.retake_policy ?? 'no_retake',
        payment_required: settings.payment_required,
        certificate_auto_generate: settings.certificate_auto_generate,
        certificate_valid_months: settings.certificate_valid_months ? String(settings.certificate_valid_months) : '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch(routeBase(exam, 'settings'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Certification Settings</h2>
            <div className="mt-4 space-y-4">
                <Input label="Pass Mark" value={data.pass_mark} error={errors.pass_mark} type="number" step="0.01" onChange={(value) => setData('pass_mark', value)} />
                <Input label="Attempt Limit" value={data.attempt_limit} error={errors.attempt_limit} type="number" onChange={(value) => setData('attempt_limit', value)} />
                <label className="block text-sm font-semibold text-slateDark">
                    Retake Policy
                    <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.retake_policy} onChange={(event) => setData('retake_policy', event.target.value)}>
                        <option value="no_retake">No retake</option>
                        <option value="failed_only">Failed candidates only</option>
                        <option value="payment_required">Retake after payment</option>
                        <option value="always_allowed">Always allowed within limit</option>
                    </select>
                    {errors.retake_policy && <span className="mt-1 block text-sm text-danger">{errors.retake_policy}</span>}
                </label>
                <Input label="Certificate Validity Months" value={data.certificate_valid_months} error={errors.certificate_valid_months} type="number" onChange={(value) => setData('certificate_valid_months', value)} />
                <Toggle label="Payment required before exam" checked={data.payment_required} onChange={(checked) => setData('payment_required', checked)} />
                <Toggle label="Generate certificate after pass" checked={data.certificate_auto_generate} onChange={(checked) => setData('certificate_auto_generate', checked)} />
                <Button type="submit" disabled={processing} className="w-full"><Save className="h-4 w-4" />Save Settings</Button>
            </div>
        </form>
    );
}

function TemplatePanel({ exam, template }: { exam: Exam; template: Template | null }) {
    const [localLogoUrl, setLocalLogoUrl] = useState<string | null>(null);
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        title: string;
        institution_name: string;
        logo_url: string;
        logo: File | null;
        primary_color: string;
        accent_color: string;
        background_color: string;
        theme: string;
        paper_size: string;
        orientation: string;
        template_key: string;
        use_logo_watermark: boolean;
        body: string;
        signatory_name: string;
        signatory_title: string;
        is_active: boolean;
    }>({
        name: template?.name ?? 'Default Certificate',
        title: template?.title ?? 'Certificate of Achievement',
        institution_name: template?.institution_name ?? exam.institution_name ?? 'AlignEx',
        logo_url: template?.logo_url ?? '',
        logo: null,
        primary_color: template?.primary_color ?? '#0F7A3A',
        accent_color: template?.accent_color ?? '#F59E0B',
        background_color: template?.background_color ?? '#FFFFFF',
        theme: template?.theme ?? 'classic',
        paper_size: template?.paper_size ?? 'a4',
        orientation: template?.orientation ?? 'landscape',
        template_key: template?.template_key ?? 'formal',
        use_logo_watermark: template?.use_logo_watermark ?? true,
        body: template?.body ?? 'This certifies that {{candidate_name}} with registration number {{registration_number}} has successfully completed {{exam_title}} with a score of {{score}}/{{total_marks}}.',
        signatory_name: template?.signatory_name ?? '',
        signatory_title: template?.signatory_title ?? '',
        is_active: true,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (template) {
            post(`${routeBase(exam, 'templates')}/${template.id}`, { preserveScroll: true, forceFormData: true });
        } else {
            post(routeBase(exam, 'templates'), { preserveScroll: true, forceFormData: true });
        }
    };

    useEffect(() => {
        if (!data.logo) {
            setLocalLogoUrl(null);
            return;
        }

        const objectUrl = URL.createObjectURL(data.logo);
        setLocalLogoUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [data.logo]);

    const previewTemplate = {
        ...(template ?? {
            id: 'preview',
            is_active: true,
            body: data.body,
            name: data.name,
            title: data.title,
        }),
        name: data.name,
        title: data.title,
        institution_name: data.institution_name,
        logo_url: localLogoUrl ?? data.logo_url,
        primary_color: data.primary_color,
        accent_color: data.accent_color,
        background_color: data.background_color,
        theme: data.theme,
        paper_size: data.paper_size,
        orientation: data.orientation,
        template_key: data.template_key,
        use_logo_watermark: data.use_logo_watermark,
        body: data.body,
        signatory_name: data.signatory_name,
        signatory_title: data.signatory_title,
    } as Template;

    return (
        <div className="space-y-5">
            <form onSubmit={submit} className="rounded-md border border-border bg-white p-4 shadow-sm">
                <h2 className="font-semibold text-slateDark">Certificate Template</h2>
                <div className="mt-4 space-y-4">
                    <Input label="Template Name" value={data.name} error={errors.name} onChange={(value) => setData('name', value)} />
                    <Input label="Certificate Title" value={data.title} error={errors.title} onChange={(value) => setData('title', value)} />
                    <Input label="School / Organization Name" value={data.institution_name} error={errors.institution_name} onChange={(value) => setData('institution_name', value)} />
                    <Input label="Logo URL" value={data.logo_url} error={errors.logo_url} onChange={(value) => setData('logo_url', value)} />
                    <label className="block text-sm font-semibold text-slateDark">
                        Upload Logo
                        <input className="mt-1 block h-10 w-full rounded-md border-border text-sm shadow-sm file:mr-3 file:h-full file:border-0 file:bg-slate-100 file:px-3 file:font-semibold file:text-slateDark focus:border-primary focus:ring-primary" type="file" accept="image/*" onChange={(event) => setData('logo', event.target.files?.[0] ?? null)} />
                        {errors.logo && <span className="mt-1 block text-sm text-danger">{errors.logo}</span>}
                    </label>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Select label="Theme" value={data.theme} error={errors.theme} onChange={(value) => setData('theme', value)} options={[['classic', 'Classic'], ['modern', 'Modern'], ['emerald', 'Emerald'], ['mono', 'Mono']]} />
                        <Select label="Template" value={data.template_key} error={errors.template_key} onChange={(value) => setData('template_key', value)} options={[['formal', 'Formal'], ['compact', 'Compact'], ['bordered', 'Bordered']]} />
                        <Select label="Paper Size" value={data.paper_size} error={errors.paper_size} onChange={(value) => setData('paper_size', value)} options={[['a4', 'A4'], ['letter', 'Letter']]} />
                        <Select label="Orientation" value={data.orientation} error={errors.orientation} onChange={(value) => setData('orientation', value)} options={[['landscape', 'Landscape'], ['portrait', 'Portrait']]} />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Input label="Primary Color" value={data.primary_color} error={errors.primary_color} type="color" onChange={(value) => setData('primary_color', value)} />
                        <Input label="Accent Color" value={data.accent_color} error={errors.accent_color} type="color" onChange={(value) => setData('accent_color', value)} />
                        <Input label="Background" value={data.background_color} error={errors.background_color} type="color" onChange={(value) => setData('background_color', value)} />
                    </div>
                    <label className="block text-sm font-semibold text-slateDark">
                        Body
                        <textarea className="mt-1 block min-h-32 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={data.body} onChange={(event) => setData('body', event.target.value)} />
                        {errors.body && <span className="mt-1 block text-sm text-danger">{errors.body}</span>}
                    </label>
                    <Input label="Signatory Name" value={data.signatory_name} error={errors.signatory_name} onChange={(value) => setData('signatory_name', value)} />
                    <Input label="Signatory Title" value={data.signatory_title} error={errors.signatory_title} onChange={(value) => setData('signatory_title', value)} />
                    <Toggle label="Use logo as background watermark" checked={data.use_logo_watermark} onChange={(checked) => setData('use_logo_watermark', checked)} />
                    <Toggle label="Active template" checked={data.is_active} onChange={(checked) => setData('is_active', checked)} />
                    <Button type="submit" disabled={processing} className="w-full"><FileCheck className="h-4 w-4" />Save Template</Button>
                </div>
            </form>
            <CertificatePreview exam={exam} template={previewTemplate} />
        </div>
    );
}

function CertificatePreview({ exam, template }: { exam: Exam; template: Template | null }) {
    const institutionName = template?.institution_name || exam.institution_name || 'AlignEx';
    const primaryColor = template?.primary_color || '#0F7A3A';
    const accentColor = template?.accent_color || '#F59E0B';
    const backgroundColor = template?.background_color || '#FFFFFF';
    const sample = useMemo(() => renderTemplate(template?.body ?? 'Create a certificate template to preview certificate text.', {
        candidate_name: 'Amina Yusuf',
        registration_number: 'PRO-2026-001',
        exam_title: exam.title,
        exam_code: exam.exam_code,
        score: '82',
        total_marks: String(exam.total_marks),
        institution_name: institutionName,
    }), [exam, institutionName, template]);

    const previewClass = template?.orientation === 'portrait' ? 'mx-auto max-w-md' : '';

    return (
        <div className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Certificate Preview</h2>
            <div className={`mt-4 overflow-hidden rounded-md border p-2 ${previewClass}`} style={{ borderColor: primaryColor, backgroundColor }}>
                <div className={`relative border text-center ${template?.template_key === 'compact' ? 'min-h-64 p-4' : 'min-h-80 p-6'} ${template?.template_key === 'bordered' ? 'border-4' : ''}`} style={{ borderColor: accentColor }}>
                    {template?.logo_url && template.use_logo_watermark !== false && (
                        <img src={template.logo_url} alt="" className="pointer-events-none absolute left-1/2 top-1/2 h-44 w-44 -translate-x-1/2 -translate-y-1/2 object-contain opacity-10" />
                    )}
                    <div className="relative">
                        {template?.logo_url ? <img src={template.logo_url} alt={institutionName} className="mx-auto h-14 w-14 object-contain" /> : <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-xl font-bold text-white" style={{ backgroundColor: primaryColor }}>{institutionName.slice(0, 1)}</div>}
                        <div className="mt-3 text-xs font-semibold uppercase tracking-wide" style={{ color: primaryColor }}>{institutionName}</div>
                        <h3 className="mt-3 text-2xl font-bold text-slateDark">{template?.title ?? 'Certificate Preview'}</h3>
                        <div className="mx-auto mt-3 h-1 w-28 rounded" style={{ backgroundColor: accentColor }} />
                        <p className="mx-auto mt-6 max-w-xl text-sm leading-7 text-slate-700">{sample}</p>
                        <div className="mt-8 grid gap-5 sm:grid-cols-[1fr_auto_1fr] sm:items-end">
                            <div>
                                <div className="mx-auto h-px w-40 bg-slate-300" />
                                <div className="mt-2 text-sm font-semibold text-slateDark">{template?.signatory_name || 'Authorized Signatory'}</div>
                                <div className="text-xs text-slate-500">{template?.signatory_title || 'Certification Officer'}</div>
                            </div>
                            <QrPreview payload={`${window.location.origin}/verify-certificate?serial=PRO-2026-SAMPLE`} />
                            <div>
                                <div className="mx-auto h-px w-40 bg-slate-300" />
                                <div className="mt-2 text-sm font-semibold text-slateDark">Verification</div>
                                <div className="text-xs text-slate-500">Serial: PRO-2026-SAMPLE</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function PaymentPanel({ exam, attempts }: { exam: Exam; attempts: Attempt[] }) {
    return (
        <section>
            <div className="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold text-slateDark">Payment Status</h2>
                    <p className="text-sm text-slate-600">Mark paid or waived candidates before exam access and certificate generation.</p>
                </div>
            </div>
            <DataTable<Attempt>
                rows={attempts}
                emptyTitle="No candidate attempts"
                columns={[
                    { key: 'candidate_name', header: 'Candidate', render: (row) => <span className="font-semibold text-slateDark">{row.candidate_name}</span> },
                    { key: 'registration_number', header: 'Registration Number' },
                    { key: 'score', header: 'Score', render: (row) => `${row.score}/${row.total_marks}` },
                    { key: 'passed', header: 'Result', render: (row) => <StatusBadge label={row.passed ? 'Pass' : 'Not Passed'} tone={row.passed ? 'success' : 'neutral'} /> },
                    { key: 'payment_status', header: 'Payment', render: (row) => <PaymentEditor exam={exam} attempt={row} /> },
                    { key: 'certificate_serial', header: 'Certificate', render: (row) => row.certificate_serial ?? 'Not generated' },
                    { key: 'actions', header: 'Actions', render: (row) => <Button type="button" variant="secondary" onClick={() => router.post(`${routeBase(exam, 'attempts')}/${row.id}/certificate`, {}, { preserveScroll: true })}><Award className="h-4 w-4" />Generate</Button> },
                ]}
            />
        </section>
    );
}

function PaymentEditor({ exam, attempt }: { exam: Exam; attempt: Attempt }) {
    const [status, setStatus] = useState(attempt.payment_status);
    const [reference, setReference] = useState(attempt.payment_reference ?? '');

    return (
        <div className="flex min-w-72 flex-wrap items-center gap-2">
            <select className="h-9 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary" value={status} onChange={(event) => setStatus(event.target.value)}>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="waived">Waived</option>
                <option value="failed">Failed</option>
            </select>
            <input className="h-9 w-32 rounded-md border-border text-sm shadow-sm focus:border-primary focus:ring-primary" value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Reference" />
            <Button type="button" variant="secondary" className="h-9 px-3" onClick={() => router.patch(`${routeBase(exam, 'attempts')}/${attempt.id}/payment`, { payment_status: status, payment_reference: reference }, { preserveScroll: true })}><CreditCard className="h-4 w-4" />Save</Button>
        </div>
    );
}

function CertificatesPanel({ exam, certificates }: { exam: Exam; certificates: Certificate[] }) {
    return (
        <section className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-slateDark">Generated Certificates</h2>
                    <p className="text-sm text-slate-600">{certificates.length} issued certificates.</p>
                </div>
                <Button type="button" onClick={() => router.post(routeBase(exam, 'certificates'), {}, { preserveScroll: true })}><BadgeCheck className="h-4 w-4" />Generate Passed</Button>
            </div>
            {certificates.length === 0 && <AlertBanner className="mt-4" title="No certificates generated" message="Passed candidates with cleared payment will appear here after generation." />}
            <div className="mt-4 space-y-3">
                {certificates.map((certificate) => (
                    <div key={certificate.id} className="grid gap-3 rounded-md border border-border p-3 md:grid-cols-[1fr_auto] md:items-center">
                        <div>
                            <div className="font-semibold text-slateDark">{certificate.candidate_name}</div>
                            <div className="text-sm text-slate-600">{certificate.registration_number} | {certificate.serial_number} | {certificate.percentage}%</div>
                            <a className="text-sm font-semibold text-primary" href={certificate.verification_url} target="_blank" rel="noreferrer">Verification link</a>
                        </div>
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <QrPreview payload={certificate.qr_payload} />
                            <Button asChild type="button" variant="secondary">
                                <a href={`${routeBase(exam, 'certificates')}/${certificate.id}/download`}>
                                    <Download className="h-4 w-4" />
                                    Download
                                </a>
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function TemplatesList({ exam, templates }: { exam: Exam; templates: Template[] }) {
    return (
        <section className="rounded-md border border-border bg-white p-4 shadow-sm">
            <h2 className="font-semibold text-slateDark">Template Management</h2>
            <div className="mt-4 space-y-2">
                {templates.length === 0 && <div className="text-sm text-slate-500">No certificate templates yet.</div>}
                {templates.map((template) => (
                    <div key={template.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-3">
                        <div>
                            <div className="font-semibold text-slateDark">{template.name}</div>
                            <div className="text-sm text-slate-500">{template.title}</div>
                        </div>
                        <div className="flex items-center gap-2">
                            <StatusBadge label={template.is_active ? 'Active' : 'Inactive'} tone={template.is_active ? 'success' : 'neutral'} />
                            <Button type="button" variant="danger" onClick={() => window.confirm('Delete this certificate template?') && router.delete(`${routeBase(exam, 'templates')}/${template.id}`, { preserveScroll: true })}><Trash2 className="h-4 w-4" />Delete</Button>
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function QrPreview({ payload }: { payload: string }) {
    const cells = Array.from({ length: 49 }, (_, index) => payload.charCodeAt(index % payload.length) + index);
    return (
        <div className="mx-auto mt-4 grid h-24 w-24 grid-cols-7 gap-0.5 rounded-md border border-border bg-white p-1" title={payload}>
            {cells.map((value, index) => <span key={index} className={value % 3 === 0 || [0, 1, 7, 8, 5, 6, 12, 13, 35, 36, 42, 43].includes(index) ? 'bg-slateDark' : 'bg-slate-100'} />)}
        </div>
    );
}

function Input({ label, value, error, type = 'text', step, onChange }: { label: string; value: string; error?: string; type?: string; step?: string; onChange: (value: string) => void }) {
    return (
        <label className="block text-sm font-semibold text-slateDark">
            {label}
            <input className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" type={type} step={step} value={value} onChange={(event) => onChange(event.target.value)} />
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function Select({ label, value, error, options, onChange }: { label: string; value: string; error?: string; options: [string, string][]; onChange: (value: string) => void }) {
    return (
        <label className="block text-sm font-semibold text-slateDark">
            {label}
            <select className="mt-1 block h-10 w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm" value={value} onChange={(event) => onChange(event.target.value)}>
                {options.map(([optionValue, labelText]) => <option key={optionValue} value={optionValue}>{labelText}</option>)}
            </select>
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm font-semibold text-slateDark">
            {label}
            <input className="h-4 w-4 rounded border-border text-primary focus:ring-primary" type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
        </label>
    );
}

function renderTemplate(template: string, values: Record<string, string>) {
    return Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`{{${key}}}`, value), template);
}

function routeBase(exam: Exam, suffix: string) {
    return `${exam.route_base ?? `/exams/${exam.id}/certification`}/${suffix}`;
}
