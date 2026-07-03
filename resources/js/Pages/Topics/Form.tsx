import { Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { StatusOption, SubjectOption, Topic } from './types';

type TopicFormData = {
    subject_id: string;
    parent_id: string;
    name: string;
    code: string;
    description: string;
    status: string;
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function TopicForm({ topic, subjects, topics, statuses, submitLabel }: { topic?: Topic; subjects: { data: SubjectOption[] }; topics: { data: Topic[] }; statuses: StatusOption[]; submitLabel: string }) {
    const { data, setData, post, patch, processing, errors } = useForm<TopicFormData>({
        subject_id: topic?.subject_id ?? '',
        parent_id: topic?.parent_id ?? '',
        name: topic?.name ?? '',
        code: topic?.code ?? '',
        description: topic?.description ?? '',
        status: topic?.status ?? 'active',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        topic ? patch(`/topics/${topic.id}`) : post('/topics');
    };

    const parentOptions = topics.data.filter((option) => option.subject_id === data.subject_id);

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Topic Information"
                description="Organize subject content into topics and optional parent topic groupings."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary">
                            <Link href="/topics">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            {submitLabel}
                        </Button>
                    </div>
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Subject" error={errors.subject_id}>
                        <select className={inputClass} value={data.subject_id} onChange={(event) => setData({ ...data, subject_id: event.target.value, parent_id: '' })} required>
                            <option value="">Choose subject</option>
                            {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Parent Topic" error={errors.parent_id}>
                        <select className={inputClass} value={data.parent_id} onChange={(event) => setData('parent_id', event.target.value)}>
                            <option value="">None</option>
                            {parentOptions.map((parent) => <option key={parent.id} value={parent.id}>{parent.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Name" error={errors.name}>
                        <input className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} required />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                    </Field>
                </div>
                <Field label="Description" error={errors.description}>
                    <textarea className={inputClass} rows={4} value={data.description} onChange={(event) => setData('description', event.target.value)} />
                </Field>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className={labelClass}>{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}
