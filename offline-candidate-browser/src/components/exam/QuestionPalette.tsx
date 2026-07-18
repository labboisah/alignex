import { cn } from '../../utils/cn';

export function QuestionPalette({
    totalQuestions,
    currentIndex,
    selectedAnswers,
    pendingQuestionIds,
    failedQuestionIds,
    questionIds,
    disabled = false,
    onSelectQuestion,
}: {
    totalQuestions: number;
    currentIndex: number;
    selectedAnswers: Record<string, string | null>;
    pendingQuestionIds: Set<string>;
    failedQuestionIds: Set<string>;
    questionIds: string[];
    disabled?: boolean;
    onSelectQuestion: (index: number) => void;
}) {
    const answeredCount = questionIds.filter((questionId) => Boolean(selectedAnswers[questionId])).length;
    const unansweredCount = Math.max(0, totalQuestions - answeredCount);

    return (
        <aside className="rounded-md border border-border bg-white p-4 shadow-sm lg:sticky lg:top-24 lg:self-start">
            <div className="text-lg font-bold text-slateDark">Question Palette</div>
            <div className="mt-3 grid grid-cols-2 gap-2">
                <CountPill label="Answered" value={answeredCount} tone="success" />
                <CountPill label="Unanswered" value={unansweredCount} tone="neutral" />
            </div>
            <div className="mt-4 grid grid-cols-5 gap-2">
                {Array.from({ length: totalQuestions }, (_, index) => {
                    const questionId = questionIds[index];
                    const current = index === currentIndex;
                    const pending = questionId ? pendingQuestionIds.has(questionId) : false;
                    const failed = questionId ? failedQuestionIds.has(questionId) : false;
                    const answered = questionId ? Boolean(selectedAnswers[questionId]) : false;

                    return (
                        <button
                            key={questionId ?? index}
                            className={cn(
                                'flex h-11 w-11 items-center justify-center rounded-md border text-sm font-bold transition-colors',
                                current && 'border-info bg-info text-white',
                                !current && failed && 'border-danger bg-danger/10 text-danger',
                                !current && !failed && pending && 'border-accentOrange bg-accentOrange/10 text-accentOrange',
                                !current && !failed && !pending && answered && 'border-primary bg-primary/10 text-primary',
                                !current && !failed && !pending && !answered && 'border-border bg-lightBackground text-slate-500',
                                disabled && 'cursor-not-allowed opacity-70',
                            )}
                            disabled={disabled}
                            onClick={() => onSelectQuestion(index)}
                            type="button"
                        >
                            {index + 1}
                        </button>
                    );
                })}
            </div>
            <div className="mt-4 grid gap-2 text-xs font-semibold text-slate-600">
                <Legend color="bg-info" label="Current" />
                <Legend color="bg-primary" label="Answered" />
                <Legend color="bg-lightBackground border border-border" label="Unanswered" />
                <Legend color="bg-accentOrange" label="Pending save" />
                <Legend color="bg-danger" label="Save failed" />
            </div>
        </aside>
    );
}

function CountPill({ label, value, tone }: { label: string; value: number; tone: 'success' | 'neutral' }) {
    return (
        <div className={cn('rounded-md border px-3 py-2 text-center', tone === 'success' ? 'border-primary/30 bg-primary/5 text-primary' : 'border-border bg-lightBackground text-slate-600')}>
            <div className="text-lg font-bold">{value}</div>
            <div className="text-xs font-semibold">{label}</div>
        </div>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className={cn('h-3 w-3 rounded-sm', color)} />
            {label}
        </div>
    );
}
