import { Head, router, useForm } from '@inertiajs/react';
import { Edit, KeyRound, Plus, Save, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';
import { ActionDropdown, DataTable, PageHeader, PortalAppShell, ProtectedAction, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Option = {
    id?: number;
    value?: string;
    label?: string;
    name?: string;
    organization_id?: number | null;
};

type UserRecord = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    status: 'active' | 'inactive';
    status_label: string;
    organization_id: number | null;
    organization_name: string | null;
    secondary_school_id: number | null;
    secondary_school_name: string | null;
    professional_school_id: number | null;
    professional_school_name: string | null;
    cbt_center_id: number | null;
    cbt_center_name: string | null;
    created_at: string | null;
};

type FormData = {
    name: string;
    email: string;
    role: string;
    status: 'active' | 'inactive';
    organization_id: string;
    secondary_school_id: string;
    professional_school_id: string;
    cbt_center_id: string;
    password: string;
};

type Props = {
    users: { data: UserRecord[] };
    options: {
        roles: Option[];
        statuses: Option[];
        organizations: Option[];
        secondary_schools: Option[];
        professional_schools: Option[];
        cbt_centers: Option[];
    };
    can: { create: boolean };
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

const blankForm: FormData = {
    name: '',
    email: '',
    role: 'organization_admin',
    status: 'active',
    organization_id: '',
    secondary_school_id: '',
    professional_school_id: '',
    cbt_center_id: '',
    password: '',
};

export default function UsersIndex({ users, options, can }: Props) {
    const [editing, setEditing] = useState<UserRecord | null>(null);
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm<FormData>(blankForm);

    const filteredSecondarySchools = useMemo(() => filterByOrganization(options.secondary_schools, data.organization_id), [options.secondary_schools, data.organization_id]);
    const filteredProfessionalSchools = useMemo(() => filterByOrganization(options.professional_schools, data.organization_id), [options.professional_schools, data.organization_id]);
    const filteredCbtCenters = useMemo(() => filterByOrganization(options.cbt_centers, data.organization_id), [options.cbt_centers, data.organization_id]);

    const beginCreate = () => {
        setEditing(null);
        clearErrors();
        reset();
    };

    const beginEdit = (user: UserRecord) => {
        setEditing(user);
        clearErrors();
        setData({
            name: user.name,
            email: user.email,
            role: user.role,
            status: user.status,
            organization_id: stringifyId(user.organization_id),
            secondary_school_id: stringifyId(user.secondary_school_id),
            professional_school_id: stringifyId(user.professional_school_id),
            cbt_center_id: stringifyId(user.cbt_center_id),
            password: '',
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (editing) {
            patch(`/users/${editing.id}`, { preserveScroll: true, onSuccess: beginCreate });
            return;
        }

        post('/users', { preserveScroll: true, onSuccess: beginCreate });
    };

    return (
        <PortalAppShell title="Users">
            <Head title="Users" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Administration"
                    title="Users"
                    description="Create portal users, assign roles, connect them to institutions, and control account access."
                    actions={(
                        <ProtectedAction allowed={can.create}>
                            <Button type="button" variant="secondary" onClick={beginCreate}>
                                <Plus className="h-4 w-4" />
                                New User
                            </Button>
                        </ProtectedAction>
                    )}
                />

                <div className="mb-6 rounded-md border border-border bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slateDark">{editing ? `Edit ${editing.name}` : 'Create User'}</h2>
                            <p className="mt-1 text-sm text-slate-600">Password is required for new users and optional when editing.</p>
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
                            <Field label="Email" error={errors.email}>
                                <input className={inputClass} type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} required />
                            </Field>
                            <Field label={editing ? 'New Password' : 'Password'} error={errors.password}>
                                <input className={inputClass} type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} required={!editing} minLength={8} />
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Role" error={errors.role}>
                                <select className={inputClass} value={data.role} onChange={(event) => setData('role', event.target.value)} required>
                                    {options.roles.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                                </select>
                            </Field>
                            <Field label="Status" error={errors.status}>
                                <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value as FormData['status'])} required>
                                    {options.statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                                </select>
                            </Field>
                            <Field label="Organization" error={errors.organization_id}>
                                <select className={inputClass} value={data.organization_id} onChange={(event) => setOrganization(event.target.value)}>
                                    <option value="">None</option>
                                    {options.organizations.map((organization) => <option key={organization.id} value={organization.id}>{organization.name}</option>)}
                                </select>
                            </Field>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Secondary School" error={errors.secondary_school_id}>
                                <select className={inputClass} value={data.secondary_school_id} onChange={(event) => setData('secondary_school_id', event.target.value)}>
                                    <option value="">None</option>
                                    {filteredSecondarySchools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Professional School" error={errors.professional_school_id}>
                                <select className={inputClass} value={data.professional_school_id} onChange={(event) => setData('professional_school_id', event.target.value)}>
                                    <option value="">None</option>
                                    {filteredProfessionalSchools.map((school) => <option key={school.id} value={school.id}>{school.name}</option>)}
                                </select>
                            </Field>
                            <Field label="CBT Center" error={errors.cbt_center_id}>
                                <select className={inputClass} value={data.cbt_center_id} onChange={(event) => setData('cbt_center_id', event.target.value)}>
                                    <option value="">None</option>
                                    {filteredCbtCenters.map((center) => <option key={center.id} value={center.id}>{center.name}</option>)}
                                </select>
                            </Field>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={processing}>
                                <Save className="h-4 w-4" />
                                {editing ? 'Save User' : 'Create User'}
                            </Button>
                        </div>
                    </form>
                </div>

                <DataTable<UserRecord>
                    rows={users.data}
                    emptyTitle="No users found"
                    columns={[
                        { key: 'name', header: 'Name', render: (user) => <span className="font-semibold text-slateDark">{user.name}</span> },
                        { key: 'email', header: 'Email' },
                        { key: 'role_label', header: 'Role' },
                        { key: 'organization_name', header: 'Organization', render: (user) => user.organization_name ?? 'N/A' },
                        { key: 'institution', header: 'Institution', render: institutionLabel },
                        { key: 'status', header: 'Status', render: (user) => <StatusBadge label={user.status_label} tone={user.status === 'active' ? 'success' : 'neutral'} /> },
                        {
                            key: 'actions',
                            header: 'Actions',
                            render: (user) => (
                                <ActionDropdown
                                    items={[
                                        { label: 'Edit', icon: Edit, onSelect: () => beginEdit(user) },
                                        { label: 'Reset Password', icon: KeyRound, onSelect: () => beginEdit(user) },
                                        {
                                            label: user.status === 'active' ? 'Deactivate' : 'Activate',
                                            destructive: user.status === 'active',
                                            onSelect: () => router.patch(`/users/${user.id}`, { ...userToForm(user), status: user.status === 'active' ? 'inactive' : 'active', password: '' }, { preserveScroll: true }),
                                        },
                                        {
                                            label: 'Delete',
                                            icon: Trash2,
                                            destructive: true,
                                            onSelect: () => window.confirm('Delete this user account?') && router.delete(`/users/${user.id}`, { preserveScroll: true }),
                                        },
                                    ]}
                                />
                            ),
                        },
                    ]}
                />
            </section>
        </PortalAppShell>
    );

    function setOrganization(value: string) {
        setData({
            ...data,
            organization_id: value,
            secondary_school_id: '',
            professional_school_id: '',
            cbt_center_id: '',
        });
    }
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

function filterByOrganization(options: Option[], organizationId: string) {
    if (!organizationId) {
        return options;
    }

    return options.filter((option) => String(option.organization_id ?? '') === organizationId);
}

function stringifyId(value: number | null): string {
    return value === null || value === undefined ? '' : String(value);
}

function institutionLabel(user: UserRecord): string {
    return user.secondary_school_name ?? user.professional_school_name ?? user.cbt_center_name ?? 'N/A';
}

function userToForm(user: UserRecord): FormData {
    return {
        name: user.name,
        email: user.email,
        role: user.role,
        status: user.status,
        organization_id: stringifyId(user.organization_id),
        secondary_school_id: stringifyId(user.secondary_school_id),
        professional_school_id: stringifyId(user.professional_school_id),
        cbt_center_id: stringifyId(user.cbt_center_id),
        password: '',
    };
}
