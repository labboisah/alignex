import { Link, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Course = {
    id: number | string;
    name: string;
    code?: string | null;
    programme?: { name: string } | null;
};

export type FacilitatorPayload = {
    id?: number | string;
    name: string;
    email: string;
    course_ids: Array<number | string>;
};

type Props = {
    courses: Course[];
    facilitator?: FacilitatorPayload;
    submitUrl: string;
    cancelUrl: string;
    mode: 'create' | 'edit';
};

const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';

export function FacilitatorForm({ courses, facilitator, submitUrl, cancelUrl, mode }: Props) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: facilitator?.name ?? '',
        email: facilitator?.email ?? '',
        password: '',
        course_ids: (facilitator?.course_ids ?? []).map(String),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (mode === 'edit') {
            patch(submitUrl);
            return;
        }

        post(submitUrl);
    };

    const toggleCourse = (courseId: number | string, checked: boolean) => {
        const id = String(courseId);

        setData('course_ids', checked
            ? [...data.course_ids, id]
            : data.course_ids.filter((selected) => selected !== id));
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title={mode === 'edit' ? 'Edit Facilitator' : 'Register Facilitator'}
                description="Assigned courses control the facilitator's question bank, question, and assessment access."
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Name" error={errors.name}>
                        <input required className={inputClass} value={data.name} onChange={(event) => setData('name', event.target.value)} />
                    </Field>
                    <Field label="Email" error={errors.email}>
                        <input required type="email" className={inputClass} value={data.email} onChange={(event) => setData('email', event.target.value)} />
                    </Field>
                    <Field label={mode === 'edit' ? 'Password (optional)' : 'Password'} error={errors.password}>
                        <input
                            required={mode === 'create'}
                            type="password"
                            className={inputClass}
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                        />
                    </Field>
                </div>

                <div className="mt-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="text-sm font-semibold text-slateDark">Courses</h3>
                            <p className="mt-1 text-sm text-slate-600">Select one or more professional school courses.</p>
                        </div>
                        {data.course_ids.length > 0 && (
                            <span className="text-sm font-medium text-primary">{data.course_ids.length} selected</span>
                        )}
                    </div>
                    {errors.course_ids && <p className="mt-2 text-sm text-danger">{errors.course_ids}</p>}

                    <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {courses.map((course) => {
                            const checked = data.course_ids.includes(String(course.id));

                            return (
                                <label
                                    key={course.id}
                                    className="flex min-h-20 cursor-pointer gap-3 rounded-md border border-border bg-white p-3 text-sm transition hover:border-primary/50 hover:bg-green-50/40"
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-1 h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                        checked={checked}
                                        onChange={(event) => toggleCourse(course.id, event.target.checked)}
                                    />
                                    <span className="min-w-0">
                                        <span className="block font-semibold text-slateDark">{course.name}</span>
                                        <span className="mt-1 block text-xs text-slate-500">
                                            {course.code ?? 'No code'}{course.programme?.name ? ` / ${course.programme.name}` : ''}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>

                    {courses.length === 0 && (
                        <div className="mt-3 rounded-md border border-dashed border-border bg-slate-50 p-4 text-sm text-slate-600">
                            No courses exist in this professional school yet.
                        </div>
                    )}
                </div>

                <div className="mt-6 flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" asChild>
                        <Link href={cancelUrl}>Cancel</Link>
                    </Button>
                    <Button disabled={processing || courses.length === 0}>
                        {mode === 'edit' ? 'Save Changes' : 'Create Facilitator'}
                    </Button>
                </div>
            </FormSection>
        </form>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slateDark">{label}</span>
            {children}
            {error && <span className="mt-1 block text-sm text-danger">{error}</span>}
        </label>
    );
}
