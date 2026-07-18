import type { ExamQuestion } from '../../types/api';
import { OptionButton } from './OptionButton';

type QuestionWithOptionalImage = ExamQuestion & {
    image_url?: string | null;
};

export function QuestionCard({
    question,
    questionNumber,
    totalQuestions,
    selectedOptionId,
    disabled = false,
    onSelectOption,
}: {
    question: QuestionWithOptionalImage;
    questionNumber: number;
    totalQuestions: number;
    selectedOptionId: string | null | undefined;
    disabled?: boolean;
    onSelectOption: (optionId: string) => void;
}) {
    return (
        <section className="rounded-md border border-border bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                <div className="text-base font-bold text-primary">
                    Question {questionNumber} of {totalQuestions}
                </div>
                <div className="rounded-md bg-lightBackground px-3 py-1 text-sm font-bold text-slate-500">{question.marks} mark(s)</div>
            </div>

            <div className="mt-4 text-lg font-semibold leading-7 text-slateDark">{question.body}</div>

            {question.image_url && (
                <div className="mt-4 overflow-hidden rounded-md border border-border bg-lightBackground">
                    <img alt={`Question ${questionNumber}`} className="max-h-72 w-full object-contain" src={question.image_url} />
                </div>
            )}

            <div className="mt-4 grid gap-2">
                {question.options.length > 0 ? (
                    question.options.map((option) => (
                        <OptionButton key={option.id} disabled={disabled} option={option} selected={selectedOptionId === option.id} onSelect={() => onSelectOption(option.id)} />
                    ))
                ) : (
                    <div className="rounded-md border border-accentOrange/30 bg-accentOrange/10 p-4 text-lg font-semibold text-slateDark">
                        This question type will be answered in a later module.
                    </div>
                )}
            </div>
        </section>
    );
}
