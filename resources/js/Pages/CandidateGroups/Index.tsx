import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Save, Trash2, X } from 'lucide-react';
import { FormEvent, ReactNode, useEffect, useState } from 'react';
import { DataTable, FormSection, PageHeader, PortalAppShell, StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type CandidateGroup = {
    id: string;
    name: string;
    code?: string | null;
    department_id?: number | string | null;
    department_name?: string | null;
    description?: string | null;
    status: string;
    candidates_count: number;
    candidate_ids: Array<number | string>;
    can_manage: boolean;
};

type DepartmentOption = {
    id: number | string;
    name: string;
    code?: string | null;
};

type CandidateOption = {
    id: number | string;
    department_id?: number | string | null;
    name: string;
    registration_number: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export default function CandidateGroupsIndex({ groups, statuses, departments = [], candidates = [] }: { groups: CandidateGroup[]; statuses: { value: string; label: string }[]; departments?: DepartmentOption[]; candidates?: CandidateOption[] }) {
    const [editing, setEditing] = useState<CandidateGroup | null>(null);
    const isInstitution = departments.length > 0 || groups.some((group) => group.department_id);

    return (
        <PortalAppShell title="Candidate Groups">
            <Head title="Candidate Groups" />
            <PageHeader
                eyebrow="Exam Registration"
                title="Candidate Groups"
                description="Organize candidates into reusable groups for exam registration."
            />
            <GroupForm statuses={statuses} departments={departments} candidates={candidates} editing={editing} onDone={() => setEditing(null)} />
            <DataTable<CandidateGroup>
                rows={groups}
                emptyTitle="No candidate groups"
                columns={[
                    { key: 'name', header: 'Name', render: (group) => <span className="font-semibold text-slateDark">{group.name}</span> },
                    ...(isInstitution ? [{ key: 'department_name' as const, header: 'Department', render: (group: CandidateGroup) => group.department_name ?? 'N/A' }] : []),
                    { key: 'candidates_count', header: 'Candidates', render: (group) => String(group.candidates_count) },
                    { key: 'status', header: 'Status', render: (group) => <StatusBadge label={group.status} tone={group.status === 'active' ? 'success' : 'neutral'} /> },
                    { key: 'description', header: 'Description', render: (group) => group.description ?? 'N/A' },
                    {
                        key: 'actions',
                        header: 'Actions',
                        render: (group) => (
                            <div className="flex flex-wrap gap-2">
                                {group.can_manage ? (
                                    <>
                                        <Button type="button" size="sm" variant="secondary" onClick={() => setEditing(group)}><Pencil className="h-4 w-4" />Edit</Button>
                                        <Button type="button" size="sm" variant="danger" onClick={() => window.confirm('Delete this candidate group?') && router.delete(`/candidate-groups/${group.id}`, { preserveScroll: true })}><Trash2 className="h-4 w-4" />Delete</Button>
                                    </>
                                ) : <span className="text-xs text-slate-500">View only</span>}
                            </div>
                        ),
                    },
                ]}
            />
        </PortalAppShell>
    );
}

function GroupForm({ statuses, departments, candidates, editing, onDone }: { statuses: { value: string; label: string }[]; departments: DepartmentOption[]; candidates: CandidateOption[]; editing: CandidateGroup | null; onDone: () => void }) {
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        department_id: editing?.department_id ? String(editing.department_id) : '',
        name: editing?.name ?? '',
        code: editing?.code ?? '',
        description: editing?.description ?? '',
        status: editing?.status ?? 'active',
        candidate_ids: editing?.candidate_ids?.map(String) ?? [] as string[],
    });
    const availableCandidates = candidates.filter((candidate) => !data.department_id || !candidate.department_id || String(candidate.department_id) === String(data.department_id));

    useEffect(() => {
        if (!editing) {
            reset();
            return;
        }

        setData({
            department_id: editing.department_id ? String(editing.department_id) : '',
            name: editing.name,
            code: editing.code ?? '',
            description: editing.description ?? '',
            status: editing.status,
            candidate_ids: editing.candidate_ids?.map(String) ?? [],
        });
    }, [editing?.id]);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        };

        if (editing) {
            patch(`/candidate-groups/${editing.id}`, options);
        } else {
            post('/candidate-groups', options);
        }
    };

    return (
        <form onSubmit={submit} className="mb-6">
            <FormSection
                title={editing ? 'Edit Candidate Group' : 'New Candidate Group'}
                description="Select candidates for this group, or create an empty group and add candidates later through import."
                footer={
                    <div className="flex flex-wrap gap-2">
                        {editing && <Button type="button" variant="secondary" onClick={onDone}><X className="h-4 w-4" />Cancel</Button>}
                        <Button type="submit" disabled={processing}>{editing ? <Save className="h-4 w-4" /> : <Plus className="h-4 w-4" />}{editing ? 'Save Group' : 'Create Group'}</Button>
                    </div>
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    {departments.length > 0 && (
                        <Field label="Department" error={errors.department_id}>
                            <select required className={inputClass} value={data.department_id} onChange={(event) => setData({ ...data, department_id: event.target.value, candidate_ids: [] })}>
                                <option value="">Choose department</option>
                                {departments.map((department) => <option key={department.id} value={department.id}>{department.name}{department.code ? ` (${department.code})` : ''}</option>)}
                            </select>
                        </Field>
                    )}
                    <Field label="Name" error={errors.name}><input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} /></Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Description" error={errors.description}><textarea className={inputClass} value={data.description} onChange={(event) => setData('description', event.target.value)} /></Field>
                    <fieldset className="min-w-0">
                        <legend className="text-sm font-semibold text-slateDark">Candidates</legend>
                        <div className="mt-1 max-h-64 space-y-1 overflow-y-auto rounded-md border border-border p-2">
                            {availableCandidates.map(candidate => (
                                <label key={candidate.id} className="flex cursor-pointer items-start gap-3 rounded-md p-2 hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        className="mt-1 rounded border-border text-primary focus:ring-primary"
                                        checked={data.candidate_ids.includes(String(candidate.id))}
                                        disabled={processing}
                                        onChange={event => {
                                            const id = String(candidate.id);
                                            setData('candidate_ids', event.target.checked
                                                ? Array.from(new Set([...data.candidate_ids, id]))
                                                : data.candidate_ids.filter(selectedId => selectedId !== id));
                                        }}
                                    />
                                    <span className="text-sm">
                                        <span className="font-medium text-slateDark">{candidate.name}</span>
                                        <span className="block text-xs text-slate-500">{candidate.registration_number}</span>
                                    </span>
                                </label>
                            ))}
                            {availableCandidates.length === 0 && (
                                <p className="p-2 text-sm text-slate-500">No candidates are available{data.department_id ? ' for this department' : ''}.</p>
                            )}
                        </div>
                        <p className="mt-2 text-xs text-slate-600" aria-live="polite">{data.candidate_ids.length} candidate(s) selected.</p>
                        {errors.candidate_ids && <p role="alert" className="mt-1 text-sm text-danger">{errors.candidate_ids}</p>}
                    </fieldset>
                </div>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="block text-sm font-semibold text-slateDark">{label}{children}{error && <span className="mt-1 block text-sm text-danger">{error}</span>}</label>;
}
