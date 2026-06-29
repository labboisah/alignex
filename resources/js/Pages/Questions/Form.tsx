import { Link, useForm } from '@inertiajs/react';
import { ImageOff, Save } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { FormSection } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { OptionChoice, Question, QuestionBankOption, SelectOption, SubjectOption, TopicOption } from './types';

type QuestionFormData = {
    question_bank_id: string;
    subject_id: string;
    topic_id: string;
    difficulty: string;
    marks: string;
    stem: string;
    image: File | null;
    remove_image: boolean;
    explanation: string;
    status: string;
    options: OptionChoice[];
};

const labels: OptionChoice['label'][] = ['A', 'B', 'C', 'D', 'E'];
const inputClass = 'mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary sm:text-sm';
const labelClass = 'text-sm font-semibold text-slateDark';

export function QuestionForm({ question, questionBanks, subjects, topics, difficulties, statuses, submitLabel }: { question?: Question; questionBanks: { data: QuestionBankOption[] }; subjects: { data: SubjectOption[] }; topics: { data: TopicOption[] }; difficulties: SelectOption[]; statuses: SelectOption[]; submitLabel: string }) {
    const initialOptions = labels.map((label) => {
        const option = question?.options?.find((choice) => choice.label === label);
        return {
            label,
            option_text: option?.option_text ?? '',
            is_correct: option?.is_correct ?? false,
        };
    });

    const { data, setData, post, processing, errors, transform } = useForm<QuestionFormData>({
        question_bank_id: question?.question_bank_id ?? '',
        subject_id: question?.subject_id ?? '',
        topic_id: question?.topic_id ?? '',
        difficulty: question?.difficulty ?? 'medium',
        marks: String(question?.marks ?? '1'),
        stem: question?.stem ?? '',
        image: null,
        remove_image: false,
        explanation: question?.explanation ?? '',
        status: question?.status ?? 'draft',
        options: initialOptions,
    });

    const selectedQuestionBank = questionBanks.data.find((bank) => bank.id === data.question_bank_id);
    const availableTopics = topics.data.filter((topic) => topic.subject_id === data.subject_id);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((values) => ({
            ...values,
            _method: question ? 'patch' : undefined,
        }));

        post(question ? `/questions/${question.id}` : '/questions', {
            forceFormData: true,
        });
    };

    const setOption = (index: number, field: keyof OptionChoice, value: string | boolean) => {
        const next = [...data.options];
        next[index] = { ...next[index], [field]: value };
        setData('options', next);
    };

    const chooseCorrect = (label: string) => {
        setData('options', data.options.map((option) => ({ ...option, is_correct: option.label === label })));
    };

    return (
        <form onSubmit={submit}>
            <FormSection
                title="Question Information"
                description="Author a single-choice question and mark one correct answer for admin review and scoring."
                footer={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button asChild type="button" variant="secondary">
                            <Link href={question ? `/questions/${question.id}` : '/questions'}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            {submitLabel}
                        </Button>
                    </div>
                }
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Question Bank" error={errors.question_bank_id}>
                        <select
                            className={inputClass}
                            value={data.question_bank_id}
                            onChange={(event) => {
                                const bank = questionBanks.data.find((item) => item.id === event.target.value);
                                setData({
                                    ...data,
                                    question_bank_id: event.target.value,
                                    subject_id: bank?.subject_id ?? '',
                                    topic_id: '',
                                });
                            }}
                            required
                        >
                            <option value="">Choose question bank</option>
                            {questionBanks.data.map((bank) => <option key={bank.id} value={bank.id}>{bank.name} ({bank.code})</option>)}
                        </select>
                    </Field>
                    <Field label="Subject" error={errors.subject_id}>
                        <select className={inputClass} value={data.subject_id} onChange={(event) => setData({ ...data, subject_id: event.target.value, topic_id: '' })} required>
                            <option value="">Choose subject</option>
                            {subjects.data.map((subject) => <option key={subject.id} value={subject.id}>{subject.name} ({subject.code})</option>)}
                        </select>
                        {selectedQuestionBank && <span className="mt-1 block text-xs text-slate-500">Expected: {selectedQuestionBank.subject_name}</span>}
                    </Field>
                    <Field label="Topic" error={errors.topic_id}>
                        <select className={inputClass} value={data.topic_id} onChange={(event) => setData('topic_id', event.target.value)}>
                            <option value="">None</option>
                            {availableTopics.map((topic) => <option key={topic.id} value={topic.id}>{topic.name} ({topic.code})</option>)}
                        </select>
                    </Field>
                    <Field label="Difficulty" error={errors.difficulty}>
                        <select className={inputClass} value={data.difficulty} onChange={(event) => setData('difficulty', event.target.value)}>
                            {difficulties.map((difficulty) => <option key={difficulty.value} value={difficulty.value}>{difficulty.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Marks" error={errors.marks}>
                        <input className={inputClass} type="number" step="0.01" min="0.01" value={data.marks} onChange={(event) => setData('marks', event.target.value)} required />
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            {statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                    </Field>
                </div>

                <Field label="Question Text" error={errors.stem}>
                    <textarea className={inputClass} rows={5} value={data.stem} onChange={(event) => setData('stem', event.target.value)} required />
                </Field>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field label="Optional Image" error={errors.image}>
                        <input className="mt-1 block w-full rounded-md border border-border text-sm file:mr-3 file:h-10 file:border-0 file:bg-slate-100 file:px-3 file:text-sm file:font-semibold" type="file" accept="image/*" onChange={(event) => setData('image', event.target.files?.[0] ?? null)} />
                    </Field>
                    {question?.image_url && (
                        <div className="rounded-md border border-border p-3">
                            <img src={question.image_url} alt="Question" className="max-h-36 rounded-md object-contain" />
                            <label className="mt-3 flex items-center gap-2 text-sm font-semibold text-slateDark">
                                <input type="checkbox" checked={data.remove_image} onChange={(event) => setData('remove_image', event.target.checked)} />
                                <ImageOff className="h-4 w-4" />
                                Remove image
                            </label>
                        </div>
                    )}
                </div>

                <Field label="Explanation" error={errors.explanation}>
                    <textarea className={inputClass} rows={4} value={data.explanation} onChange={(event) => setData('explanation', event.target.value)} />
                </Field>

                <div>
                    <div className="mb-2 text-sm font-semibold text-slateDark">Options A-E</div>
                    {errors.options && <div className="mb-2 text-sm text-danger">{errors.options}</div>}
                    <div className="space-y-3">
                        {data.options.map((option, index) => (
                            <div key={option.label} className="grid gap-3 rounded-md border border-border bg-white p-3 md:grid-cols-[4rem_1fr_10rem]">
                                <div className="flex h-10 items-center justify-center rounded-md bg-slate-100 text-sm font-bold text-slateDark">{option.label}</div>
                                <textarea className={inputClass} rows={2} value={option.option_text} onChange={(event) => setOption(index, 'option_text', event.target.value)} placeholder={`Option ${option.label}`} />
                                <label className="flex items-center gap-2 text-sm font-semibold text-slateDark">
                                    <input type="radio" name="correct_answer" checked={option.is_correct} onChange={() => chooseCorrect(option.label)} />
                                    Correct
                                </label>
                            </div>
                        ))}
                    </div>
                </div>
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
