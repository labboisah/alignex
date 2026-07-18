import { Head, router, useForm } from '@inertiajs/react';
import { Edit, Plus, Save, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';
import { DataTable, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type PricingPlan = {
    id: number;
    slug: string;
    name: string;
    description: string;
    price: number;
    formatted_price: string;
    currency: string;
    billing_cycle: 'forever' | 'monthly' | 'yearly' | 'contract';
    billing_label: string;
    delivery_modes: string[];
    limits: Record<string, number | null>;
    features: Record<string, boolean>;
    feature_items: { key: string; label: string; enabled: boolean }[];
    highlights: string[];
    is_active: boolean;
    is_featured: boolean;
    cta_label: string;
    sort_order: number;
    registrations_count?: number;
};

type FormData = {
    slug: string;
    name: string;
    description: string;
    price: string;
    currency: string;
    billing_cycle: PricingPlan['billing_cycle'];
    delivery_modes: string[];
    feature_flags: string[];
    highlights_text: string;
    max_candidates: string;
    max_exams_per_month: string;
    max_admin_users: string;
    max_devices: string;
    official_live_exam_allowed: boolean;
    is_active: boolean;
    is_featured: boolean;
    cta_label: string;
    sort_order: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

const blankForm: FormData = {
    slug: '',
    name: '',
    description: '',
    price: '0',
    currency: 'NGN',
    billing_cycle: 'yearly',
    delivery_modes: ['online', 'offline'],
    feature_flags: [
        'traditional_cbt',
        'candidate_import',
        'question_import',
        'csv_export',
        'offline_activation',
        'exam_package_import',
        'result_package_export',
        'official_live_exam_allowed',
    ],
    highlights_text: '',
    max_candidates: '',
    max_exams_per_month: '',
    max_admin_users: '',
    max_devices: '',
    official_live_exam_allowed: true,
    is_active: true,
    is_featured: false,
    cta_label: 'Register',
    sort_order: '100',
};

export default function PricingPlansIndex({ plans }: { plans: { data: PricingPlan[] } }) {
    const [editing, setEditing] = useState<PricingPlan | null>(null);
    const { data, setData, post, patch, processing, errors, reset } = useForm<FormData>(blankForm);

    const beginCreate = () => {
        setEditing(null);
        reset();
    };

    const beginEdit = (plan: PricingPlan) => {
        setEditing(plan);
        setData({
            slug: plan.slug,
            name: plan.name,
            description: plan.description,
            price: String(plan.price),
            currency: plan.currency,
            billing_cycle: plan.billing_cycle,
            delivery_modes: plan.delivery_modes ?? [],
            feature_flags: (plan.feature_items ?? []).filter((feature) => feature.enabled).map((feature) => feature.key),
            highlights_text: (plan.highlights ?? []).join('\n'),
            max_candidates: valueFromLimit(plan, 'max_candidates'),
            max_exams_per_month: valueFromLimit(plan, 'max_exams_per_month'),
            max_admin_users: valueFromLimit(plan, 'max_admin_users'),
            max_devices: valueFromLimit(plan, 'max_devices'),
            official_live_exam_allowed: plan.features?.official_live_exam_allowed ?? false,
            is_active: plan.is_active,
            is_featured: plan.is_featured,
            cta_label: plan.cta_label,
            sort_order: String(plan.sort_order),
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            patch(`/pricing-plans/${editing.id}`, { preserveScroll: true });
            return;
        }

        post('/pricing-plans', { preserveScroll: true, onSuccess: beginCreate });
    };

    const toggleDeliveryMode = (mode: string) => {
        const current = data.delivery_modes ?? [];
        setData('delivery_modes', current.includes(mode) ? current.filter((item) => item !== mode) : [...current, mode]);
    };

    const toggleFeature = (feature: string) => {
        const current = data.feature_flags ?? [];
        setData('feature_flags', current.includes(feature) ? current.filter((item) => item !== feature) : [...current, feature]);
    };

    const featureOptions = plans.data[0]?.feature_items ?? defaultFeatureItems;

    return (
        <PortalAppShell title="Pricing Plans">
            <Head title="Pricing Plans" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Platform"
                    title="Pricing Plans"
                    description="Manage public plans shown on the pricing page and selected during admin registration."
                    actions={(
                        <Button type="button" variant="secondary" onClick={beginCreate}>
                            <Plus className="h-4 w-4" />
                            New Plan
                        </Button>
                    )}
                />

                <div className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slateDark">{editing ? `Edit ${editing.name}` : 'Create Pricing Plan'}</h2>
                            <p className="mt-1 text-sm text-slate-600">Changes affect the public pricing page and future registration selections.</p>
                        </div>
                        {editing && (
                            <Button type="button" variant="secondary" onClick={beginCreate}>
                                <X className="h-4 w-4" />
                                Cancel
                            </Button>
                        )}
                    </div>

                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Name" error={errors.name}>
                                <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} required />
                            </Field>
                            <Field label="Slug" error={errors.slug}>
                                <input className={inputClass} value={data.slug} onChange={(event) => setData('slug', event.target.value)} required />
                            </Field>
                            <Field label="CTA Label" error={errors.cta_label}>
                                <input className={inputClass} value={data.cta_label} onChange={(event) => setData('cta_label', event.target.value)} required />
                            </Field>
                        </div>

                        <Field label="Description" error={errors.description}>
                            <textarea className={inputClass} rows={3} value={data.description} onChange={(event) => setData('description', event.target.value)} required />
                        </Field>

                        <div className="grid gap-4 md:grid-cols-4">
                            <Field label="Price" error={errors.price}>
                                <input className={inputClass} type="number" min="0" value={data.price} onChange={(event) => setData('price', event.target.value)} required />
                            </Field>
                            <Field label="Currency" error={errors.currency}>
                                <input className={inputClass} value={data.currency} onChange={(event) => setData('currency', event.target.value)} required />
                            </Field>
                            <Field label="Billing" error={errors.billing_cycle}>
                                <select className={inputClass} value={data.billing_cycle} onChange={(event) => setData('billing_cycle', event.target.value as PricingPlan['billing_cycle'])}>
                                    <option value="forever">Forever</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="contract">Yearly or Contract</option>
                                </select>
                            </Field>
                            <Field label="Sort Order" error={errors.sort_order}>
                                <input className={inputClass} type="number" min="0" value={data.sort_order} onChange={(event) => setData('sort_order', event.target.value)} required />
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-4">
                            <Field label="Max Candidates" error={errors.max_candidates}>
                                <input className={inputClass} type="number" min="1" value={data.max_candidates} onChange={(event) => setData('max_candidates', event.target.value)} placeholder="Blank for custom" />
                            </Field>
                            <Field label="Exams / Month" error={errors.max_exams_per_month}>
                                <input className={inputClass} type="number" min="1" value={data.max_exams_per_month} onChange={(event) => setData('max_exams_per_month', event.target.value)} />
                            </Field>
                            <Field label="Admin Users" error={errors.max_admin_users}>
                                <input className={inputClass} type="number" min="1" value={data.max_admin_users} onChange={(event) => setData('max_admin_users', event.target.value)} />
                            </Field>
                            <Field label="Center Devices" error={errors.max_devices}>
                                <input className={inputClass} type="number" min="1" value={data.max_devices} onChange={(event) => setData('max_devices', event.target.value)} />
                            </Field>
                        </div>

                        <Field label="Highlights" error={errors.highlights_text}>
                            <textarea className={inputClass} rows={5} value={data.highlights_text} onChange={(event) => setData('highlights_text', event.target.value)} placeholder="One highlight per line" />
                        </Field>

                        <div>
                            <h3 className="text-sm font-semibold text-slateDark">Detailed Features</h3>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {featureOptions
                                    .filter((feature) => !['online_delivery', 'offline_delivery', 'official_live_exam_allowed'].includes(feature.key))
                                    .map((feature) => (
                                        <Checkbox
                                            key={feature.key}
                                            label={feature.label}
                                            checked={data.feature_flags.includes(feature.key)}
                                            onChange={() => toggleFeature(feature.key)}
                                        />
                                    ))}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <Checkbox label="Online Delivery" checked={data.delivery_modes.includes('online')} onChange={() => toggleDeliveryMode('online')} />
                            <Checkbox label="Offline Delivery" checked={data.delivery_modes.includes('offline')} onChange={() => toggleDeliveryMode('offline')} />
                            <Checkbox label="Official Exams" checked={data.official_live_exam_allowed} onChange={(checked) => setData('official_live_exam_allowed', checked)} />
                            <Checkbox label="Active" checked={data.is_active} onChange={(checked) => setData('is_active', checked)} />
                            <Checkbox label="Recommended" checked={data.is_featured} onChange={(checked) => setData('is_featured', checked)} />
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Save className="h-4 w-4" />
                                {editing ? 'Save Plan' : 'Create Plan'}
                            </Button>
                        </div>
                    </form>
                </div>

                <DataTable<PricingPlan>
                    rows={plans.data}
                    emptyTitle="No pricing plans found"
                    columns={[
                        { key: 'name', header: 'Plan', render: (plan) => <span className="font-semibold text-slateDark">{plan.name}</span> },
                        { key: 'price', header: 'Price', render: (plan) => `${plan.formatted_price} ${plan.billing_label}` },
                        { key: 'delivery_modes', header: 'Delivery', render: (plan) => (plan.delivery_modes ?? []).join(', ') || 'N/A' },
                        { key: 'registrations_count', header: 'Registrations', render: (plan) => String(plan.registrations_count ?? 0) },
                        { key: 'status', header: 'Status', render: (plan) => <StatusBadge label={plan.is_active ? 'Active' : 'Inactive'} tone={plan.is_active ? 'success' : 'neutral'} /> },
                        {
                            key: 'actions',
                            header: 'Actions',
                            render: (plan) => (
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" variant="secondary" className="h-9 px-3" onClick={() => beginEdit(plan)}>
                                        <Edit className="h-4 w-4" />
                                        Edit
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="danger"
                                        className="h-9 px-3"
                                        disabled={(plan.registrations_count ?? 0) > 0}
                                        onClick={() => window.confirm('Delete this pricing plan?') && router.delete(`/pricing-plans/${plan.id}`, { preserveScroll: true })}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            ),
                        },
                    ]}
                />
            </section>
        </PortalAppShell>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block text-sm font-semibold text-slateDark">
            {label}
            {children}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}

function Checkbox({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <label className="inline-flex h-10 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-semibold text-slate-700">
            <input
                type="checkbox"
                className="rounded border-border text-primary focus:ring-primary"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            {label}
        </label>
    );
}

function valueFromLimit(plan: PricingPlan, key: string): string {
    const value = plan.limits?.[key];
    return value === null || value === undefined ? '' : String(value);
}

const defaultFeatureItems = [
    { key: 'traditional_cbt', label: 'Traditional CBT', enabled: true },
    { key: 'adaptive_exam', label: 'Adaptive exams', enabled: false },
    { key: 'candidate_import', label: 'Candidate import', enabled: true },
    { key: 'question_import', label: 'Question import', enabled: true },
    { key: 'teacher_management', label: 'Teacher management', enabled: false },
    { key: 'facilitator_management', label: 'Facilitator management', enabled: false },
    { key: 'csv_export', label: 'CSV result export', enabled: true },
    { key: 'pdf_export', label: 'PDF result export', enabled: false },
    { key: 'certificate_generation', label: 'Certificate generation', enabled: false },
    { key: 'custom_branding', label: 'Custom branding', enabled: false },
    { key: 'webcam_proctoring', label: 'Webcam proctoring', enabled: false },
    { key: 'result_sync', label: 'Result sync', enabled: false },
    { key: 'multi_center', label: 'Multi-center delivery', enabled: false },
    { key: 'custom_reports', label: 'Custom reports', enabled: false },
    { key: 'advanced_analytics', label: 'Advanced analytics', enabled: false },
    { key: 'priority_support', label: 'Priority support', enabled: false },
    { key: 'dedicated_support', label: 'Dedicated support', enabled: false },
    { key: 'api_integration', label: 'API integration', enabled: false },
    { key: 'offline_activation', label: 'Offline activation', enabled: true },
    { key: 'exam_package_import', label: 'Exam package import', enabled: true },
    { key: 'result_package_export', label: 'Result package export', enabled: true },
    { key: 'email_notifications', label: 'Email notifications', enabled: false },
    { key: 'sms_notifications', label: 'SMS notifications', enabled: false },
    { key: 'demo_watermark', label: 'Demo watermark', enabled: false },
];
