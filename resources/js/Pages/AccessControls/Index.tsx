import { Head, useForm, usePage } from '@inertiajs/react';
import { Save, ShieldCheck } from 'lucide-react';
import { FormEvent, Fragment } from 'react';
import { AlertBanner, PageHeader, PortalAppShell, RoleBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/cn';

type Role = {
    name: string;
    label: string;
    description?: string;
    is_system: boolean;
    permissions: string[];
};

type Permission = {
    name: string;
    label: string;
    group: string;
    description?: string;
};

type Props = {
    roles: Role[];
    permissions: Permission[];
};

type PageProps = {
    flash?: {
        success?: string;
    };
};

const lockedRoles = ['super_admin', 'candidate'];

export default function AccessControlIndex({ roles, permissions }: Props) {
    const flash = (usePage().props as PageProps).flash;
    const groupedPermissions = permissions.reduce<Record<string, Permission[]>>((groups, permission) => {
        groups[permission.group] = [...(groups[permission.group] ?? []), permission];

        return groups;
    }, {});

    const { data, setData, patch, processing, errors } = useForm({
        roles: roles.reduce<Record<string, string[]>>((values, role) => {
            values[role.name] = role.permissions;

            return values;
        }, {}),
    });

    const togglePermission = (roleName: string, permissionName: string) => {
        if (lockedRoles.includes(roleName)) {
            return;
        }

        const current = data.roles[roleName] ?? [];
        const next = current.includes(permissionName)
            ? current.filter((name) => name !== permissionName)
            : [...current, permissionName];

        setData('roles', {
            ...data.roles,
            [roleName]: next,
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        patch('/access-controls', { preserveScroll: true });
    };

    return (
        <PortalAppShell title="Access Controls">
            <Head title="Access Controls" />
            <section className="mx-auto max-w-7xl">
                <PageHeader
                    eyebrow="Security"
                    title="Access Controls"
                    description="Manage the permission matrix for AlignEx system roles. Super admin and candidate protections stay locked."
                />

                <div className="mb-4 grid gap-3">
                    {flash?.success && <AlertBanner tone="success" title={flash.success} />}
                    {Object.keys(errors).length > 0 && (
                        <AlertBanner tone="danger" title="Unable to update access controls" message="Review the selected permissions and try again." />
                    )}
                </div>

                <form onSubmit={submit} className="rounded-md border border-border bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-md bg-green-50 text-primary">
                                <ShieldCheck className="h-5 w-5" />
                            </div>
                            <div>
                                <h2 className="text-base font-semibold text-slateDark">Role Permissions</h2>
                                <p className="text-sm text-slate-600">Changes apply immediately to sidebar visibility and protected routes.</p>
                            </div>
                        </div>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            Save
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-border text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="sticky left-0 z-10 w-72 bg-slate-50 px-4 py-3 text-left font-semibold text-slateDark">Permission</th>
                                    {roles.map((role) => (
                                        <th key={role.name} className="min-w-48 px-4 py-3 text-left align-top font-semibold text-slateDark">
                                            <div className="space-y-2">
                                                <RoleBadge role={role.label} />
                                                <p className="font-normal leading-5 text-slate-500">{role.description}</p>
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border bg-white">
                                {Object.entries(groupedPermissions).map(([group, groupPermissions]) => (
                                    <Fragment key={group}>
                                        <tr className="bg-slate-50">
                                            <td colSpan={roles.length + 1} className="px-4 py-2 text-xs font-bold uppercase text-slate-500">
                                                {group}
                                            </td>
                                        </tr>
                                        {groupPermissions.map((permission) => (
                                            <tr key={permission.name}>
                                                <td className="sticky left-0 z-10 bg-white px-4 py-3 align-top">
                                                    <div className="font-semibold text-slateDark">{permission.label}</div>
                                                    <div className="mt-1 leading-5 text-slate-500">{permission.description}</div>
                                                </td>
                                                {roles.map((role) => {
                                                    const checked = data.roles[role.name]?.includes(permission.name) ?? false;
                                                    const locked = lockedRoles.includes(role.name);

                                                    return (
                                                        <td key={`${role.name}-${permission.name}`} className="px-4 py-3 align-top">
                                                            <label
                                                                className={cn(
                                                                    'inline-flex h-9 items-center gap-2 rounded-md border border-border bg-white px-3 text-sm font-semibold text-slate-700',
                                                                    locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-slate-50',
                                                                )}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    className="rounded border-border text-primary focus:ring-primary"
                                                                    checked={checked}
                                                                    disabled={locked}
                                                                    onChange={() => togglePermission(role.name, permission.name)}
                                                                />
                                                                {checked ? 'Allowed' : 'Denied'}
                                                            </label>
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        </PortalAppShell>
    );
}
