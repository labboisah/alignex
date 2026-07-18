import {
    AlertTriangle,
    Bookmark,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Save,
    Send,
    Wifi,
    WifiOff,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button } from '../renderer/components/ui/button';
import { loadSocketIo } from '../renderer/lib/socket';
import type { CandidateExamPayload } from './CandidateApp';

type AnswerState = Record<string, string[]>;
type SaveStatus = 'Not saved' | 'Saving' | 'Saved' | 'Failed';
type PendingAnswer = {
    question_id: string;
    selected_option_id: string;
    time_spent_seconds: number;
};
export type SubmissionSummary = {
    answered_count: number;
    total_questions: number;
    submitted_at: string;
};

const apiBaseUrl = window.location.origin;

export function ExamScreen({
    payload,
    attemptToken,
    onSubmitted,
}: {
    payload: CandidateExamPayload;
    attemptToken: string;
    onSubmitted: (summary: SubmissionSummary) => void;
}) {
    const [currentIndex, setCurrentIndex] = useState(0);
    const [answers, setAnswers] = useState<AnswerState>(() => buildSavedAnswerState(payload));
    const [reviewQuestions, setReviewQuestions] = useState<Set<string>>(new Set());
    const [remainingSeconds, setRemainingSeconds] = useState(payload.remaining_time_seconds);
    const [online, setOnline] = useState(navigator.onLine);
    const [saveStatus, setSaveStatus] = useState<SaveStatus>('Not saved');
    const [pendingAnswers, setPendingAnswers] = useState<Record<string, PendingAnswer>>({});
    const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
    const [questionStartedAt, setQuestionStartedAt] = useState(Date.now());
    const [retrying, setRetrying] = useState(false);
    const [autoSubmitting, setAutoSubmitting] = useState(false);
    const [autoSubmitError, setAutoSubmitError] = useState<string | null>(null);
    const [examClosedBySupervisor, setExamClosedBySupervisor] = useState(false);
    const [confirmSubmitOpen, setConfirmSubmitOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState<string | null>(null);

    const currentQuestion = payload.questions[currentIndex];
    const currentOptions = useMemo(
        () => currentQuestion ? payload.options.filter((option) => option.question_id === currentQuestion.id) : [],
        [currentQuestion, payload.options],
    );
    const selectedOptionIds = currentQuestion ? answers[currentQuestion.id] ?? [] : [];
    const pendingAnswerCount = Object.keys(pendingAnswers).length;
    const answered = answeredCount(answers);
    const unanswered = Math.max(0, payload.questions.length - answered);
    const timeExpired = remainingSeconds <= 0;
    const optionsDisabled = timeExpired || autoSubmitting || examClosedBySupervisor;

    useEffect(() => {
        const timer = window.setInterval(() => {
            setRemainingSeconds((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        let socket: {
            emit: (event: string, payload?: unknown) => void;
            on: (event: string, callback: (payload?: unknown) => void) => void;
            off: (event: string) => void;
            disconnect: () => void;
        } | null = null;
        let cancelled = false;

        async function connectSocket() {
            try {
                const io = await loadSocketIo();

                if (cancelled) {
                    return;
                }

                socket = io(window.location.origin);
                socket.emit('candidate:join', { attempt_token: attemptToken });
                socket.on('exam_closed', (socketPayload) => {
                    const record = typeof socketPayload === 'object' && socketPayload !== null ? socketPayload as Record<string, unknown> : {};
                    const exam = typeof record.exam === 'object' && record.exam !== null ? record.exam as Record<string, unknown> : {};

                    if (typeof exam.id === 'string' && exam.id !== payload.exam.id) {
                        return;
                    }

                    setExamClosedBySupervisor(true);
                    setRemainingSeconds(0);
                    void autoSubmit();
                });
            } catch {
                // Autosave remains HTTP-based if live monitoring cannot connect.
            }
        }

        void connectSocket();

        return () => {
            cancelled = true;
            socket?.off('exam_closed');
            socket?.disconnect();
        };
    }, [attemptToken, payload.exam.id]);

    useEffect(() => {
        if (remainingSeconds > 0 || autoSubmitting || autoSubmitError) {
            return;
        }

        void autoSubmit();
    }, [remainingSeconds, autoSubmitting, autoSubmitError]);

    useEffect(() => {
        setQuestionStartedAt(Date.now());
    }, [currentQuestion?.id]);

    useEffect(() => {
        function handleBeforeUnload(event: BeforeUnloadEvent) {
            event.preventDefault();
            event.returnValue = '';
        }

        function handleOnline() {
            setOnline(true);
        }

        function handleOffline() {
            setOnline(false);
        }

        window.addEventListener('beforeunload', handleBeforeUnload);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    useEffect(() => {
        if (!online || pendingAnswerCount === 0 || retrying) {
            return;
        }

        const retryTimer = window.setTimeout(() => {
            void retryPendingAnswers();
        }, 1000);

        return () => window.clearTimeout(retryTimer);
    }, [online, pendingAnswerCount, retrying]);

    function selectOption(optionId: string) {
        if (!currentQuestion || optionsDisabled) {
            return;
        }

        setAnswers((current) => {
            return { ...current, [currentQuestion.id]: [optionId] };
        });

        const pendingAnswer = {
            question_id: currentQuestion.id,
            selected_option_id: optionId,
            time_spent_seconds: Math.max(0, Math.floor((Date.now() - questionStartedAt) / 1000)),
        };

        void saveAnswer(pendingAnswer);
    }

    async function saveAnswer(answer: PendingAnswer) {
        try {
            setSaveStatus('Saving');
            const response = await fetch(`${apiBaseUrl}/api/candidate/answer`, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${attemptToken}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(answer),
            });
            const data = await response.json() as { saved_at?: string; message?: string };

            if (!response.ok) {
                throw new Error(data.message ?? `Save failed with status ${response.status}.`);
            }

            setPendingAnswers((current) => {
                const next = { ...current };
                delete next[answer.question_id];
                return next;
            });
            setLastSavedAt(data.saved_at ?? new Date().toISOString());
            setSaveStatus('Saved');
        } catch {
            setPendingAnswers((current) => ({ ...current, [answer.question_id]: answer }));
            setSaveStatus('Failed');
        }
    }

    async function retryPendingAnswers() {
        if (retrying) {
            return;
        }

        setRetrying(true);
        const pending = Object.values(pendingAnswers);

        try {
            for (const answer of pending) {
                await saveAnswer(answer);
            }
        } finally {
            setRetrying(false);
        }
    }

    async function autoSubmit() {
        try {
            setAutoSubmitting(true);
            setAutoSubmitError(null);
            const response = await fetch(`${apiBaseUrl}/api/candidate/auto-submit`, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${attemptToken}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ auto_submit: true }),
            });
            const data = await response.json() as Partial<SubmissionSummary> & { message?: string; code?: string };

            if (!response.ok) {
                if (data.code === 'already_submitted') {
                    onSubmitted({
                        answered_count: data.answered_count ?? answered,
                        total_questions: data.total_questions ?? payload.questions.length,
                        submitted_at: data.submitted_at ?? new Date().toISOString(),
                    });
                    return;
                }

                throw new Error(data.message ?? `Auto-submit failed with status ${response.status}.`);
            }

            onSubmitted({
                answered_count: data.answered_count ?? answered,
                total_questions: data.total_questions ?? payload.questions.length,
                submitted_at: data.submitted_at ?? new Date().toISOString(),
            });
        } catch (caught) {
            setAutoSubmitError(caught instanceof Error ? caught.message : 'Unable to auto-submit exam.');
            setAutoSubmitting(false);
        }
    }

    async function submitExam() {
        if (pendingAnswerCount > 0 || submitting) {
            return;
        }

        try {
            setSubmitting(true);
            setSubmitError(null);
            const response = await fetch(`${apiBaseUrl}/api/candidate/submit`, {
                method: 'POST',
                headers: {
                    Authorization: `Bearer ${attemptToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json() as Partial<SubmissionSummary> & { message?: string };

            if (!response.ok) {
                throw new Error(data.message ?? `Submit failed with status ${response.status}.`);
            }

            setConfirmSubmitOpen(false);
            onSubmitted({
                answered_count: data.answered_count ?? answered,
                total_questions: data.total_questions ?? payload.questions.length,
                submitted_at: data.submitted_at ?? new Date().toISOString(),
            });
        } catch (caught) {
            setSubmitError(caught instanceof Error ? caught.message : 'Unable to submit exam.');
        } finally {
            setSubmitting(false);
        }
    }

    function toggleReview() {
        if (!currentQuestion) {
            return;
        }

        setReviewQuestions((current) => {
            const next = new Set(current);
            if (next.has(currentQuestion.id)) {
                next.delete(currentQuestion.id);
            } else {
                next.add(currentQuestion.id);
            }

            return next;
        });
    }

    function goPrevious() {
        setCurrentIndex((index) => Math.max(0, index - 1));
    }

    function goNext() {
        setCurrentIndex((index) => Math.min(payload.questions.length - 1, index + 1));
    }

    return (
        <main className="min-h-screen bg-lightBackground text-slateDark">
            <header className="fixed inset-x-0 top-0 z-40 border-b border-border bg-white shadow-sm">
                <div className="grid min-h-20 gap-3 px-4 py-3 lg:grid-cols-[1.1fr_1.3fr_auto_auto_auto] lg:items-center">
                    <div className="min-w-0">
                        <div className="text-xs font-semibold uppercase text-slate-500">Candidate</div>
                        <div className="truncate text-base font-bold">{payload.candidate.full_name}</div>
                        <div className="truncate text-sm text-slate-500">{payload.candidate.registration_number}</div>
                    </div>
                    <div className="min-w-0">
                        <div className="text-xs font-semibold uppercase text-slate-500">Exam</div>
                        <div className="truncate text-base font-bold">{payload.exam.title}</div>
                        <div className="truncate text-sm text-slate-500">{payload.exam.exam_code}</div>
                    </div>
                    <TopStatus
                        className={timerStatusClass(remainingSeconds)}
                        icon={<Clock className={timerIconClass(remainingSeconds)} />}
                        label="Timer"
                        value={formatSeconds(remainingSeconds)}
                    />
                    <TopStatus
                        icon={online ? <Wifi className="h-5 w-5 text-success" /> : <WifiOff className="h-5 w-5 text-danger" />}
                        label="Connection"
                        value={online ? 'Connected' : 'Offline'}
                    />
                    <TopStatus icon={<Save className="h-5 w-5 text-info" />} label="Save Status" value={lastSavedAt && saveStatus === 'Saved' ? 'Saved' : saveStatus} />
                </div>
            </header>

            <div className="grid gap-5 px-4 pb-24 pt-28 lg:grid-cols-[280px_1fr]">
                <aside className="lg:sticky lg:top-28 lg:self-start">
                    <section className="rounded-md border border-border bg-white p-4 shadow-sm">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-base font-bold">Question Palette</h2>
                            <span className="text-sm text-slate-500">{answered} / {payload.questions.length}</span>
                        </div>
                        <div className="mt-4 grid grid-cols-5 gap-2">
                            {payload.questions.map((question, index) => (
                                <button
                                    className={paletteClass({
                                        current: index === currentIndex,
                                        answered: Boolean(answers[question.id]?.length),
                                        review: reviewQuestions.has(question.id),
                                    })}
                                    key={question.id}
                                    onClick={() => setCurrentIndex(index)}
                                    type="button"
                                >
                                    {index + 1}
                                </button>
                            ))}
                        </div>
                        <div className="mt-5 grid gap-2 text-xs text-slate-500">
                            <Legend color="bg-primary" label="Current" />
                            <Legend color="bg-success" label="Answered" />
                            <Legend color="bg-white border border-border" label="Unanswered" />
                            <Legend color="bg-accentOrange" label="Marked for review" />
                        </div>
                    </section>
                </aside>

                <section className="min-w-0 rounded-md border border-border bg-white p-5 shadow-sm md:p-7">
                    {pendingAnswerCount > 0 && (
                        <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-md border border-accentOrange/30 bg-accentOrange/10 p-4 text-accentOrange">
                            <div className="flex items-center gap-2 text-sm font-semibold">
                                <AlertTriangle className="h-5 w-5" />
                                {pendingAnswerCount} answer(s) pending save. Your selected answer remains on this screen.
                            </div>
                            <Button disabled={retrying} onClick={() => void retryPendingAnswers()} type="button" variant="secondary">
                                {retrying ? 'Retrying...' : 'Retry Save'}
                            </Button>
                        </div>
                    )}

                    {(timeExpired || autoSubmitting || autoSubmitError || examClosedBySupervisor) && (
                        <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-md border border-danger/30 bg-danger/5 p-4 text-danger">
                            <div className="flex items-center gap-2 text-sm font-semibold">
                                <AlertTriangle className="h-5 w-5" />
                                {autoSubmitError ?? (examClosedBySupervisor ? 'Exam closed by supervisor. Submitting...' : 'Time is up. Submitting...')}
                            </div>
                            {autoSubmitError && (
                                <Button onClick={() => void autoSubmit()} type="button" variant="secondary">
                                    Retry Auto-submit
                                </Button>
                            )}
                        </div>
                    )}

                    {currentQuestion ? (
                        <>
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="text-sm font-bold uppercase text-primary">
                                        Question {currentIndex + 1} of {payload.questions.length}
                                    </div>
                                    <div className="mt-1 text-sm text-slate-500">{currentQuestion.marks} mark(s)</div>
                                </div>
                                <Button onClick={toggleReview} type="button" variant="secondary">
                                    <Bookmark className="h-4 w-4" />
                                    {reviewQuestions.has(currentQuestion.id) ? 'Marked for Review' : 'Mark for Review'}
                                </Button>
                            </div>

                            <div className="mt-6 text-[20px] font-semibold leading-9 md:text-2xl md:leading-10">
                                {currentQuestion.body}
                            </div>

                            {currentQuestion.question_image_url && (
                                <div className="mt-5 overflow-hidden rounded-md border border-border bg-lightBackground">
                                    <img alt="Question reference" className="max-h-96 w-full object-contain" src={currentQuestion.question_image_url} />
                                </div>
                            )}

                            <div className="mt-8 space-y-4">
                                {currentOptions.length > 0 ? (
                                    currentOptions.map((option) => {
                                        const selected = selectedOptionIds.includes(option.id);

                                        return (
                                            <button
                                                className={optionClass(selected)}
                                                disabled={optionsDisabled}
                                                key={option.id}
                                                onClick={() => selectOption(option.id)}
                                                type="button"
                                            >
                                                <span className={optionLabelClass(selected)}>{option.option_label}</span>
                                                <span className="min-w-0 flex-1 text-left">{option.body}</span>
                                                {selected && <CheckCircle2 className="h-6 w-6 shrink-0 text-success" />}
                                            </button>
                                        );
                                    })
                                ) : (
                                    <textarea
                                        className="min-h-48 w-full rounded-md border border-border p-5 text-xl leading-8 outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                                        disabled={optionsDisabled}
                                        onChange={(event) => {
                                            if (!currentQuestion) return;
                                            setAnswers((current) => ({ ...current, [currentQuestion.id]: event.target.value ? ['text_answer_pending'] : [] }));
                                            setSaveStatus('Failed');
                                        }}
                                        placeholder="Type your answer here"
                                    />
                                )}
                            </div>
                        </>
                    ) : (
                        <div className="flex items-center gap-3 rounded-md border border-danger/30 bg-danger/5 p-5 text-danger">
                            <AlertTriangle className="h-5 w-5" />
                            No question is available.
                        </div>
                    )}
                </section>
            </div>

            <footer className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-white px-4 py-3 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button className="h-12 px-5 text-base" disabled={currentIndex === 0} onClick={goPrevious} size="default" type="button" variant="secondary">
                        <ChevronLeft className="h-5 w-5" />
                        Previous
                    </Button>
                    <div className="flex items-center gap-3">
                        <Button className="h-12 px-5 text-base" disabled={currentIndex === payload.questions.length - 1} onClick={goNext} size="default" type="button">
                            Next
                            <ChevronRight className="h-5 w-5" />
                        </Button>
                        <Button
                            className="h-12 bg-accentOrange px-5 text-base text-white hover:bg-accentOrange/90 disabled:opacity-60"
                            disabled={pendingAnswerCount > 0 || timeExpired || autoSubmitting || submitting || examClosedBySupervisor}
                            onClick={() => setConfirmSubmitOpen(true)}
                            title={pendingAnswerCount > 0 ? 'Resolve pending answer saves before submitting.' : undefined}
                            type="button"
                        >
                            <Send className="h-5 w-5" />
                            {submitting ? 'Submitting...' : 'Submit'}
                        </Button>
                    </div>
                </div>
            </footer>

            {confirmSubmitOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4">
                    <div className="w-full max-w-lg rounded-md border border-border bg-white p-6 shadow-xl">
                        <h2 className="text-xl font-bold text-slateDark">Submit Exam</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-500">
                            Confirm your final submission. Answers cannot be changed after this.
                        </p>

                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            <SummaryTile label="Answered" value={String(answered)} />
                            <SummaryTile label="Unanswered" value={String(unanswered)} />
                        </div>

                        {submitError && (
                            <div className="mt-5 rounded-md border border-danger/30 bg-danger/5 p-3 text-sm font-semibold text-danger">
                                {submitError}
                            </div>
                        )}

                        <div className="mt-6 flex flex-wrap justify-end gap-3">
                            <Button disabled={submitting} onClick={() => setConfirmSubmitOpen(false)} type="button" variant="secondary">
                                Cancel
                            </Button>
                            <Button disabled={submitting || pendingAnswerCount > 0} onClick={() => void submitExam()} type="button">
                                {submitting ? 'Submitting...' : 'Confirm Submit'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </main>
    );
}

function SummaryTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border border-border bg-lightBackground p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">{label}</div>
            <div className="mt-1 text-2xl font-bold text-slateDark">{value}</div>
        </div>
    );
}

function TopStatus({ icon, label, value, className = '' }: { icon: React.ReactNode; label: string; value: string; className?: string }) {
    return (
        <div className={`flex min-w-36 items-center gap-3 rounded-md border border-border bg-lightBackground px-3 py-2 ${className}`}>
            {icon}
            <div className="min-w-0">
                <div className="text-xs font-semibold uppercase text-slate-500">{label}</div>
                <div className="truncate text-sm font-bold text-slateDark">{value}</div>
            </div>
        </div>
    );
}

function Legend({ color, label }: { color: string; label: string }) {
    return (
        <div className="flex items-center gap-2">
            <span className={`h-3 w-3 rounded-sm ${color}`} />
            {label}
        </div>
    );
}

function buildSavedAnswerState(payload: CandidateExamPayload): AnswerState {
    return payload.saved_answers.reduce<AnswerState>((state, answer) => {
        state[answer.question_id] = answer.option_ids.length > 0 ? answer.option_ids : answer.text_answer ? ['text_answer_pending'] : [];
        return state;
    }, {});
}

function answeredCount(answers: AnswerState): number {
    return Object.values(answers).filter((answer) => answer.length > 0).length;
}

function formatSeconds(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
}

function timerStatusClass(seconds: number): string {
    if (seconds < 60) {
        return 'border-danger/40 bg-danger/10 text-danger';
    }

    if (seconds < 300) {
        return 'border-accentOrange/40 bg-accentOrange/10 text-accentOrange';
    }

    return '';
}

function timerIconClass(seconds: number): string {
    if (seconds < 60) {
        return 'h-5 w-5 text-danger';
    }

    if (seconds < 300) {
        return 'h-5 w-5 text-accentOrange';
    }

    return 'h-5 w-5 text-accentOrange';
}

function paletteClass(state: { current: boolean; answered: boolean; review: boolean }): string {
    if (state.current) {
        return 'h-11 rounded-md bg-primary text-sm font-bold text-white ring-2 ring-primary/20';
    }

    if (state.review) {
        return 'h-11 rounded-md bg-accentOrange text-sm font-bold text-white';
    }

    if (state.answered) {
        return 'h-11 rounded-md bg-success text-sm font-bold text-white';
    }

    return 'h-11 rounded-md border border-border bg-white text-sm font-bold text-slateDark hover:border-primary';
}

function optionClass(selected: boolean): string {
    return [
        'flex w-full items-start gap-4 rounded-md border p-5 text-xl leading-8 transition-colors disabled:cursor-not-allowed disabled:opacity-70',
        selected
            ? 'border-success bg-success/10 text-slateDark ring-2 ring-success/20'
            : 'border-border bg-lightBackground hover:border-primary hover:bg-primary/5',
    ].join(' ');
}

function optionLabelClass(selected: boolean): string {
    return [
        'flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-lg font-bold',
        selected ? 'bg-success text-white' : 'bg-white text-primary border border-border',
    ].join(' ');
}
