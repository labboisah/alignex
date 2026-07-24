import { Head } from '@inertiajs/react';
import { AlertTriangle, Camera, CheckCircle2, Clock, Flag, Loader2, Maximize2, Wifi, WifiOff } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import { Button } from '@/Components/ui/button';

type CandidateProfile = {
    full_name: string;
    registration_number: string;
    phone?: string | null;
    photo_url?: string | null;
};

type ExamDetails = {
    title: string;
    exam_code: string;
    duration_minutes: number;
    settings: {
        allow_back_navigation: boolean;
        require_fullscreen: boolean;
        require_webcam: boolean;
        max_tab_switches: number;
    };
};

type ExamOption = {
    id: string;
    label: string;
    option_text: string;
};

type ExamQuestion = {
    id: string;
    question_id: string;
    question_order: number;
    subject_id?: string | null;
    subject_name?: string | null;
    question_text: string;
    image_url?: string | null;
    marks: string;
    options: ExamOption[];
};

type SavedAnswer = {
    question_id: string;
    selected_option_ids: string[];
    is_flagged: boolean;
    time_spent_seconds?: number;
    device_fingerprint?: string;
    saved_at?: string | null;
};

type SubjectSection = {
    key: string;
    subject: string;
    total: number;
    answered: number;
    percent: number;
    questionIndexes: number[];
};

type ExamPayload = {
    candidate: CandidateProfile;
    exam: ExamDetails;
    remaining_time: number;
    exam_token?: string | null;
    questions: { data: ExamQuestion[] } | ExamQuestion[];
    answers: SavedAnswer[];
};

const tokenKey = 'alignex_exam_token';
const payloadKey = 'alignex_exam_payload';
const fingerprintKey = 'alignex_device_fingerprint';

export default function CandidateExamApp() {
    return (
        <BrowserRouter>
            <Head title="Candidate Exam" />
            <Routes>
                <Route path="/exam/login" element={<CandidateLoginPage />} />
                <Route path="/exam/instructions" element={<ExamInstructionsPage />} />
                <Route path="/exam/write" element={<ExamScreenPage />} />
                <Route path="/exam/submitted" element={<SubmitSuccessPage />} />
                <Route path="/exam/error" element={<ExamErrorPage />} />
                <Route path="/exam/disqualified" element={<DisqualifiedPage />} />
                <Route path="*" element={<Navigate to="/exam/login" replace />} />
            </Routes>
        </BrowserRouter>
    );
}

function CandidateLoginPage() {
    const navigate = useNavigate();
    const [examCode, setExamCode] = useState('');
    const [identifier, setIdentifier] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setLoading(true);
        setError('');

        try {
            const payload = await api<ExamPayload>('/api/candidate/login', {
                method: 'POST',
                body: JSON.stringify({
                    exam_code: examCode,
                    identifier,
                    device_fingerprint: deviceFingerprint(),
                }),
            });

            localStorage.setItem(tokenKey, payload.exam_token ?? '');
            localStorage.setItem(payloadKey, JSON.stringify(payload));
            navigate('/exam/instructions');
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Unable to login.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <CandidateShell>
            <div className="mx-auto flex min-h-screen max-w-md items-center px-4">
                <form onSubmit={submit} className="w-full rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="mb-6">
                        <img src="/images/logo.png" alt="AlignEx" className="mb-4 h-16 w-16 object-contain" />
                        <div className="text-sm font-semibold uppercase text-primary">Candidate Exam</div>
                        <h1 className="mt-1 text-2xl font-bold text-slateDark">Login</h1>
                    </div>
                    {error && <Alert tone="danger" message={error} />}
                    <label className="mt-4 block text-sm font-semibold text-slateDark">
                        Exam Code
                        <input className="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={examCode} onChange={(event) => setExamCode(event.target.value.toUpperCase())} required />
                    </label>
                    <label className="mt-4 block text-sm font-semibold text-slateDark">
                        Registration Number, Phone, or NIN
                        <input className="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={identifier} onChange={(event) => setIdentifier(event.target.value)} required />
                    </label>
                    <Button className="mt-6 w-full" type="submit" disabled={loading}>
                        {loading && <Loader2 className="h-4 w-4 animate-spin" />}
                        Continue
                    </Button>
                </form>
            </div>
        </CandidateShell>
    );
}

function ExamInstructionsPage() {
    const navigate = useNavigate();
    const payload = storedPayload();
    const [webcamReady, setWebcamReady] = useState(sessionStorage.getItem('alignex_webcam_ready') === 'yes');
    const [fullscreenReady, setFullscreenReady] = useState(Boolean(document.fullscreenElement));
    const [setupError, setSetupError] = useState('');
    const [checkingSetup, setCheckingSetup] = useState(false);

    if (!payload) {
        return <Navigate to="/exam/login" replace />;
    }

    const prepareAndStart = async () => {
        const requirements = [
            payload.exam.settings.require_webcam ? 'webcam access' : null,
            payload.exam.settings.require_fullscreen ? 'fullscreen mode' : null,
        ].filter(Boolean);

        if (requirements.length > 0 && !window.confirm(`This exam requires ${requirements.join(' and ')}. Allow AlignEx to enable the required exam controls now?`)) {
            return;
        }

        setCheckingSetup(true);
        setSetupError('');

        try {
            if (payload.exam.settings.require_webcam && !webcamReady) {
                if (!navigator.mediaDevices?.getUserMedia) {
                    throw new Error('This device/browser does not support webcam access.');
                }

                const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                stream.getTracks().forEach((track) => track.stop());
                sessionStorage.setItem('alignex_webcam_ready', 'yes');
                setWebcamReady(true);
            }

            if (payload.exam.settings.require_fullscreen && !document.fullscreenElement) {
                if (!document.documentElement.requestFullscreen) {
                    throw new Error('This device/browser does not support fullscreen mode.');
                }

                await document.documentElement.requestFullscreen();
                setFullscreenReady(true);
            }

            navigate('/exam/write');
        } catch (exception) {
            setSetupError(exception instanceof Error ? exception.message : 'Required exam controls could not be enabled. Please allow permissions and try again.');
        } finally {
            setCheckingSetup(false);
        }
    };

    return (
        <CandidateShell>
            <div className="mx-auto max-w-3xl px-4 py-10">
                <div className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <img src="/images/logo.png" alt="AlignEx" className="mb-5 h-14 w-14 object-contain" />
                    <div className="text-sm font-semibold uppercase text-primary">{payload.exam.exam_code}</div>
                    <h1 className="mt-1 text-2xl font-bold text-slateDark">{payload.exam.title}</h1>
                    <div className="mt-4 grid gap-3 text-sm text-slate-600 md:grid-cols-2">
                        <Info label="Candidate" value={payload.candidate.full_name} />
                        <Info label="Registration Number" value={payload.candidate.registration_number} />
                        <Info label="Duration" value={`${payload.exam.duration_minutes} minutes`} />
                        <Info label="Questions" value={String(questionList(payload).length)} />
                    </div>
                    <div className="mt-6 space-y-2 text-sm leading-6 text-slate-600">
                        <p>Read each question carefully before selecting an answer.</p>
                        <p>Your answers save automatically. Keep your network connected and wait for pending saves before submitting.</p>
                        <p>Do not refresh, close, leave the exam window, copy, paste, right click, or attempt screen capture during the exam.</p>
                        <p>Anti-cheating controls produce evidence for supervisors and audit reports. They are not a guarantee that every violation can be detected.</p>
                    </div>
                    {(payload.exam.settings.require_webcam || payload.exam.settings.require_fullscreen) && (
                        <div className="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-slate-700">
                            <div className="font-bold text-slateDark">Exam control check</div>
                            <div className="mt-2 grid gap-2 md:grid-cols-2">
                                {payload.exam.settings.require_webcam && <RequirementStatus icon={Camera} label="Webcam" ready={webcamReady} />}
                                {payload.exam.settings.require_fullscreen && <RequirementStatus icon={Maximize2} label="Fullscreen" ready={fullscreenReady} />}
                            </div>
                            <p className="mt-3">When you start, AlignEx will ask your device to enable the required controls automatically.</p>
                        </div>
                    )}
                    {setupError && <div className="mt-4"><Alert tone="danger" message={setupError} /></div>}
                    <Button className="mt-6" type="button" disabled={checkingSetup} onClick={prepareAndStart}>
                        {checkingSetup && <Loader2 className="h-4 w-4 animate-spin" />}
                        Start Exam
                    </Button>
                </div>
            </div>
        </CandidateShell>
    );
}

function RequirementStatus({ icon: Icon, label, ready }: { icon: typeof Camera; label: string; ready: boolean }) {
    return (
        <div className="flex items-center justify-between rounded-md border border-border bg-white p-3">
            <span className="inline-flex items-center gap-2 font-semibold text-slateDark"><Icon className="h-4 w-4 text-primary" />{label}</span>
            <StatusText ready={ready} />
        </div>
    );
}

function StatusText({ ready }: { ready: boolean }) {
    return <span className={ready ? 'text-sm font-bold text-success' : 'text-sm font-bold text-warning'}>{ready ? 'Ready' : 'Will request'}</span>;
}

function CandidatePhoto({ candidate }: { candidate: CandidateProfile }) {
    const initials = candidate.full_name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || 'CA';

    if (candidate.photo_url) {
        return (
            <img
                src={candidate.photo_url}
                alt={candidate.full_name}
                className="h-14 w-14 shrink-0 rounded-md border border-border bg-slate-100 object-cover"
            />
        );
    }

    return (
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-md border border-border bg-slate-100 text-sm font-bold text-slate-600">
            {initials}
        </div>
    );
}

function ExamScreenPage() {
    const navigate = useNavigate();
    const [payload, setPayload] = useState<ExamPayload | null>(storedPayload());
    const [currentIndex, setCurrentIndex] = useState(0);
    const [selectedSubjectKey, setSelectedSubjectKey] = useState<string | null>(null);
    const [answers, setAnswers] = useState<Record<string, SavedAnswer>>(() => Object.fromEntries((payload?.answers ?? []).map((answer) => [answer.question_id, answer])));
    const [pending, setPending] = useState<Set<string>>(new Set());
    const [failed, setFailed] = useState<Record<string, SavedAnswer>>({});
    const [remaining, setRemaining] = useState(payload?.remaining_time ?? 0);
    const [online, setOnline] = useState(navigator.onLine);
    const [saveStatus, setSaveStatus] = useState('Ready');
    const [submitting, setSubmitting] = useState(false);
    const [autoSubmitting, setAutoSubmitting] = useState(false);
    const [confirmSubmit, setConfirmSubmit] = useState(false);
    const [warning, setWarning] = useState('');
    const submittedRef = useRef(false);
    const questionStartedAt = useRef(Date.now());
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const webcamStreamRef = useRef<MediaStream | null>(null);
    const lastEventAt = useRef<Record<string, number>>({});

    useEffect(() => {
        if (!examToken()) {
            navigate('/exam/login', { replace: true });
            return;
        }

        api<ExamPayload>('/api/candidate/exam')
            .then((next) => {
                setPayload(next);
                setRemaining(next.remaining_time);
                localStorage.setItem(payloadKey, JSON.stringify(next));
            })
            .catch(() => navigate('/exam/error', { replace: true }));
    }, [navigate]);

    useEffect(() => {
        const beforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        const updateOnline = () => setOnline(navigator.onLine);
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('online', updateOnline);
        window.addEventListener('offline', updateOnline);

        return () => {
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('online', updateOnline);
            window.removeEventListener('offline', updateOnline);
        };
    }, []);

    useEffect(() => {
        const interval = window.setInterval(() => {
            setRemaining((value) => {
                if (value <= 1 && !submittedRef.current) {
                    submittedRef.current = true;
                    setAutoSubmitting(true);
                    api('/api/candidate/auto-submit', { method: 'POST', body: JSON.stringify({}) }).finally(() => {
                        localStorage.removeItem(tokenKey);
                        navigate('/exam/submitted', { replace: true });
                    });
                    return 0;
                }

                return Math.max(0, value - 1);
            });
        }, 1000);

        return () => window.clearInterval(interval);
    }, [navigate]);

    useEffect(() => {
        questionStartedAt.current = Date.now();
    }, [currentIndex]);

    useEffect(() => {
        if (!payload?.exam.settings.require_webcam || webcamStreamRef.current) {
            return;
        }

        navigator.mediaDevices?.getUserMedia({ video: true, audio: false })
            .then((stream) => {
                webcamStreamRef.current = stream;
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    videoRef.current.play().catch(() => undefined);
                }
            })
            .catch(() => {
                setWarning('Webcam access failed. This has been reported to the supervisor.');
                reportProctoringEvent('webcam_permission_failed', { severity: 'high' });
            });

        return () => {
            webcamStreamRef.current?.getTracks().forEach((track) => track.stop());
            webcamStreamRef.current = null;
        };
    }, [payload?.exam.settings.require_webcam]);

    useEffect(() => {
        if (!online || Object.keys(failed).length === 0) {
            return;
        }

        const timer = window.setTimeout(() => {
            Object.values(failed).forEach((answer) => saveAnswer(answer));
        }, 1500);

        return () => window.clearTimeout(timer);
    }, [failed, online]);

    const questions = useMemo(() => (payload ? questionList(payload) : []), [payload]);
    const subjectSummaries = useMemo(() => subjectProgress(questions, answers), [questions, answers]);
    const currentSubjectKey = subjectSummaries.find((summary) => summary.questionIndexes.includes(currentIndex))?.key ?? null;

    useEffect(() => {
        if (subjectSummaries.length === 0) {
            setSelectedSubjectKey(null);
            return;
        }

        setSelectedSubjectKey((key) => {
            const hasSelectedSubject = key !== null && subjectSummaries.some((summary) => summary.key === key);

            return currentSubjectKey ?? (hasSelectedSubject ? key : subjectSummaries[0].key);
        });
    }, [currentSubjectKey, subjectSummaries]);

    if (!payload) {
        return <CandidateShell><div className="p-8 text-center">Loading exam...</div></CandidateShell>;
    }

    const current = questions[currentIndex];
    const currentAnswer = answers[current.question_id];
    const answeredCount = questions.filter((question) => answers[question.question_id]?.selected_option_ids?.length).length;
    const unansweredCount = Math.max(0, questions.length - answeredCount);
    const flaggedCount = questions.filter((question) => answers[question.question_id]?.is_flagged).length;
    const currentSubject = subjectSummaries.find((summary) => summary.questionIndexes.includes(currentIndex)) ?? subjectSummaries[0];
    const currentSubjectQuestionIndexes = currentSubject?.questionIndexes ?? [];
    const currentSubjectQuestionIndex = Math.max(0, currentSubjectQuestionIndexes.indexOf(currentIndex));
    const selectedSubject = subjectSummaries.find((summary) => summary.key === selectedSubjectKey) ?? currentSubject;
    const selectedSubjectQuestionIndexes = selectedSubject?.questionIndexes ?? [];
    const selectedSubjectQuestions = selectedSubjectQuestionIndexes.map((index) => questions[index]).filter((question): question is ExamQuestion => Boolean(question));

    const saveAnswer = async (answer: SavedAnswer) => {
        const answerForSave = {
            ...answer,
            time_spent_seconds: Math.max(answer.time_spent_seconds ?? 0, Math.round((Date.now() - questionStartedAt.current) / 1000)),
            device_fingerprint: deviceFingerprint(),
        };

        setPending((next) => new Set(next).add(answerForSave.question_id));
        setSaveStatus('Saving...');

        try {
            await api('/api/candidate/answer', {
                method: 'POST',
                body: JSON.stringify(answerForSave),
            });
            setFailed((next) => {
                const clone = { ...next };
                delete clone[answerForSave.question_id];
                return clone;
            });
            setAnswers((next) => ({ ...next, [answerForSave.question_id]: answerForSave }));
            setSaveStatus('Saved');
        } catch {
            setFailed((next) => ({ ...next, [answerForSave.question_id]: answerForSave }));
            setSaveStatus('Failed - pending sync');
        } finally {
            setPending((next) => {
                const clone = new Set(next);
                clone.delete(answerForSave.question_id);
                return clone;
            });
        }
    };

    const reportProctoringEvent = async (eventType: string, metadata: Record<string, unknown> = {}) => {
        const now = Date.now();

        if ((lastEventAt.current[eventType] ?? 0) + 1500 > now) {
            return;
        }

        lastEventAt.current[eventType] = now;
        const snapshot = captureVideoFrame(videoRef.current);
        if (eventType !== 'webcam_heartbeat') {
            const prettyEvent = eventType.replaceAll('_', ' ');
            setWarning(`${prettyEvent} detected. The supervisor has been notified.`);
        }

        try {
            const response = await api<{ disqualified?: boolean }>('/api/candidate/event', {
                method: 'POST',
                body: JSON.stringify({
                    event_type: eventType,
                    metadata: {
                        severity: metadata.severity ?? 'warning',
                        question_id: current?.question_id,
                        current_question: currentIndex + 1,
                        device_fingerprint: deviceFingerprint(),
                        ...metadata,
                        ...(snapshot ? { webcam_snapshot: snapshot } : {}),
                    },
                }),
            });

            if (response.disqualified) {
                localStorage.removeItem(tokenKey);
                navigate('/exam/disqualified', { replace: true });
            }
        } catch {
            setWarning('A monitoring event could not sync. Keep working while the system retries normal answer saves.');
        }
    };

    useEffect(() => {
        if (!payload.exam.settings.require_webcam) {
            return;
        }

        const timer = window.setInterval(() => {
            if (webcamStreamRef.current && !submittedRef.current) {
                reportProctoringEvent('webcam_heartbeat', { severity: 'info' });
            }
        }, 30000);

        return () => window.clearInterval(timer);
    }, [payload.exam.settings.require_webcam, current?.question_id, currentIndex]);

    const selectOption = (optionId: string) => {
        const answer = {
            question_id: current.question_id,
            selected_option_ids: [optionId],
            is_flagged: currentAnswer?.is_flagged ?? false,
            time_spent_seconds: Math.round((Date.now() - questionStartedAt.current) / 1000),
        };
        setAnswers((next) => ({ ...next, [current.question_id]: answer }));
        saveAnswer(answer);
    };

    const goNext = () => setCurrentIndex((index) => nextQuestionIndex(index, subjectSummaries, questions.length));
    const goPrevious = () => {
        if (!payload.exam.settings.allow_back_navigation) {
            return;
        }

        setCurrentIndex((index) => previousQuestionIndex(index, subjectSummaries));
    };

    const toggleFlag = () => {
        const answer = {
            question_id: current.question_id,
            selected_option_ids: currentAnswer?.selected_option_ids ?? [],
            is_flagged: !currentAnswer?.is_flagged,
            time_spent_seconds: Math.round((Date.now() - questionStartedAt.current) / 1000),
        };
        setAnswers((next) => ({ ...next, [current.question_id]: answer }));
        saveAnswer(answer);
    };

    const requestSubmit = () => {
        if (pending.size > 0 || Object.keys(failed).length > 0) {
            setSaveStatus('Wait for pending saves');
            return;
        }

        setConfirmSubmit(true);
    };

    const submit = async () => {
        if (pending.size > 0 || Object.keys(failed).length > 0) {
            setSaveStatus('Wait for pending saves');
            return;
        }

        submittedRef.current = true;
        setSubmitting(true);

        try {
            await api('/api/candidate/submit', { method: 'POST', body: JSON.stringify({}) });
            localStorage.removeItem(tokenKey);
            navigate('/exam/submitted', { replace: true });
        } catch (exception) {
            submittedRef.current = false;
            setSubmitting(false);
            setConfirmSubmit(false);
            setSaveStatus(exception instanceof Error ? exception.message : 'Submission failed');
        }
    };

    useEffect(() => {
        const onVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                reportProctoringEvent('tab_switch', { visibility_state: document.visibilityState });
            }
        };
        const onBlur = () => reportProctoringEvent('window_blur');
        const onFullscreenChange = () => {
            if (payload.exam.settings.require_fullscreen && !document.fullscreenElement) {
                reportProctoringEvent('fullscreen_exit', { severity: 'high' });
            }
        };
        const onCopy = (event: ClipboardEvent) => {
            event.preventDefault();
            reportProctoringEvent('copy_attempt');
        };
        const onPaste = (event: ClipboardEvent) => {
            event.preventDefault();
            reportProctoringEvent('paste_attempt');
        };
        const onContextMenu = (event: MouseEvent) => {
            event.preventDefault();
            reportProctoringEvent('right_click');
        };
        const onPrintScreen = (event: KeyboardEvent) => {
            if (event.key === 'PrintScreen') {
                reportProctoringEvent('print_screen_attempt', { severity: 'high' });
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('copy', onCopy);
        document.addEventListener('paste', onPaste);
        document.addEventListener('contextmenu', onContextMenu);
        window.addEventListener('blur', onBlur);
        window.addEventListener('keyup', onPrintScreen);

        return () => {
            document.removeEventListener('visibilitychange', onVisibilityChange);
            document.removeEventListener('fullscreenchange', onFullscreenChange);
            document.removeEventListener('copy', onCopy);
            document.removeEventListener('paste', onPaste);
            document.removeEventListener('contextmenu', onContextMenu);
            window.removeEventListener('blur', onBlur);
            window.removeEventListener('keyup', onPrintScreen);
        };
    }, [current?.question_id, currentIndex, payload.exam.settings.require_fullscreen]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
                return;
            }

            const key = event.key.toUpperCase();

            if (confirmSubmit) {
                if (key === 'Y') {
                    event.preventDefault();
                    submit();
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    setConfirmSubmit(false);
                }

                return;
            }

            if (['A', 'B', 'C', 'D', 'E'].includes(key)) {
                const option = current.options.find((item) => item.label.toUpperCase() === key);

                if (option) {
                    event.preventDefault();
                    selectOption(option.id);
                }
            }

            if (key === 'N') {
                event.preventDefault();
                goNext();
            }

            if (key === 'P') {
                event.preventDefault();
                goPrevious();
            }

            if (key === 'S') {
                event.preventDefault();
                requestSubmit();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [confirmSubmit, current, currentAnswer, failed, pending, payload.exam.settings.allow_back_navigation, submitting]);

    return (
        <CandidateShell>
            <div className="min-h-screen bg-surface">
                {confirmSubmit && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 text-white">
                        <div className="w-full max-w-lg rounded-md bg-white p-6 text-left text-slateDark shadow-xl">
                            <h2 className="text-xl font-bold">Submit Exam</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                You have answered {answeredCount} of {questions.length} questions. {unansweredCount} question(s) are unanswered.
                            </p>
                            <div className="mt-5 grid grid-cols-3 gap-3">
                                <SummaryBox label="Answered" value={answeredCount} tone="success" />
                                <SummaryBox label="Unanswered" value={unansweredCount} tone="warning" />
                                <SummaryBox label="Flagged" value={flaggedCount} tone="danger" />
                            </div>
                            <div className="mt-6 flex justify-end gap-2">
                                <Button type="button" variant="secondary" disabled={submitting} onClick={() => setConfirmSubmit(false)}>Cancel</Button>
                                <Button type="button" disabled={submitting} onClick={submit}>
                                    {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                    Confirm Submit
                                </Button>
                            </div>
                        </div>
                    </div>
                )}
                {autoSubmitting && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 px-4 text-white">
                        <div className="w-full max-w-md rounded-md bg-slateDark p-6 text-center shadow-xl">
                            <Loader2 className="mx-auto h-10 w-10 animate-spin text-accent" />
                            <h2 className="mt-4 text-xl font-bold">Time elapsed</h2>
                            <p className="mt-2 text-sm text-slate-200">Your exam is being submitted automatically. Please wait.</p>
                        </div>
                    </div>
                )}
                {payload.exam.settings.require_webcam && <video ref={videoRef} className="hidden" muted playsInline />}
                <div className="sticky top-0 z-10 border-b border-border bg-white px-4 py-3 shadow-sm">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-3">
                                <img src="/images/logo.png" alt="AlignEx" className="h-10 w-10 object-contain" />
                                <CandidatePhoto candidate={payload.candidate} />
                                <div>
                                    <div className="font-bold text-slateDark">{payload.candidate.full_name}</div>
                                    <div className="text-xs text-slate-500">{payload.candidate.registration_number} | {payload.exam.title}</div>
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-3 text-sm font-semibold">
                            <span className="inline-flex items-center gap-1 text-slateDark"><Clock className="h-4 w-4" />{formatTime(remaining)}</span>
                            <span className={online ? 'inline-flex items-center gap-1 text-success' : 'inline-flex items-center gap-1 text-danger'}>{online ? <Wifi className="h-4 w-4" /> : <WifiOff className="h-4 w-4" />}{online ? 'Online' : 'Offline'}</span>
                            <span className="text-slate-600">{saveStatus}</span>
                            {Object.keys(failed).length > 0 && <span className="text-warning">Pending sync: {Object.keys(failed).length}</span>}
                        </div>
                    </div>
                </div>
                {warning && (
                    <div className="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-warning">
                        <div className="mx-auto max-w-7xl">{warning}</div>
                    </div>
                )}

                <main className="mx-auto grid max-w-7xl gap-5 px-4 py-6 lg:grid-cols-[1fr_280px]">
                    <section className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="text-sm font-semibold text-primary">
                                    {currentSubject?.subject ?? current.subject_name ?? 'General'} | Question {currentSubjectQuestionIndex + 1} of {currentSubject?.total ?? questions.length}
                                </div>
                                <div className="mt-1 text-xs font-semibold text-slate-500">Overall question {currentIndex + 1} of {questions.length}</div>
                                <h1 className="mt-2 text-xl font-bold text-slateDark">{current.question_text}</h1>
                            </div>
                            <Button type="button" variant={currentAnswer?.is_flagged ? 'danger' : 'secondary'} onClick={toggleFlag}><Flag className="h-4 w-4" />Flag</Button>
                        </div>
                        {current.image_url && <img src={current.image_url} alt="" className="mb-5 max-h-72 rounded-md object-contain" />}
                        <div className="space-y-3">
                            {current.options.map((option) => {
                                const selected = currentAnswer?.selected_option_ids?.includes(option.id);
                                return (
                                    <button key={option.id} type="button" onClick={() => selectOption(option.id)} className={`w-full rounded-md border p-4 text-left transition ${selected ? 'border-primary bg-green-50' : 'border-border bg-white hover:border-primary'}`}>
                                        <span className="mr-3 inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-100 font-bold text-slateDark">{option.label}</span>
                                        <span className="font-semibold text-slateDark">{option.option_text}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </section>

                    <aside className="rounded-md border border-border bg-white p-4 shadow-sm">
                        <div className="mb-4 grid grid-cols-3 gap-2">
                            <SummaryBox label="Answered" value={answeredCount} tone="success" />
                            <SummaryBox label="Unanswered" value={unansweredCount} tone="warning" />
                            <SummaryBox label="Flagged" value={flaggedCount} tone="danger" />
                        </div>
                        {subjectSummaries.length > 1 && (
                            <div className="mb-4 space-y-2">
                                {subjectSummaries.map((summary) => (
                                    <button
                                        key={summary.key}
                                        type="button"
                                        onClick={() => {
                                            setSelectedSubjectKey(summary.key);
                                            setCurrentIndex(summary.questionIndexes[0]);
                                        }}
                                        className={`w-full rounded-md border p-2 text-left transition ${summary.key === selectedSubject?.key ? 'border-primary bg-green-50' : 'border-border hover:border-primary'}`}
                                    >
                                        <div className="truncate text-xs font-bold text-slateDark">{summary.subject}</div>
                                        <div className="mt-1 h-2 rounded bg-slate-100">
                                            <div className="h-2 rounded bg-primary" style={{ width: `${summary.percent}%` }} />
                                        </div>
                                        <div className="mt-1 text-xs text-slate-500">{summary.answered}/{summary.total} answered</div>
                                    </button>
                                ))}
                            </div>
                        )}
                        <div className="mb-3 font-semibold text-slateDark">{selectedSubject?.subject ?? 'Question'} Palette</div>
                        <div className="grid grid-cols-5 gap-2">
                            {selectedSubjectQuestions.map((question, subjectIndex) => {
                                const index = selectedSubjectQuestionIndexes[subjectIndex];
                                const answer = answers[question.question_id];
                                const state = pending.has(question.question_id)
                                    ? 'bg-amber-100 text-warning'
                                    : answer?.is_flagged
                                        ? 'bg-red-50 text-danger'
                                        : answer?.selected_option_ids?.length
                                            ? 'bg-green-50 text-success'
                                            : 'bg-slate-100 text-slate-600';
                                return <button key={question.question_id} type="button" onClick={() => setCurrentIndex(index)} className={`h-10 rounded-md text-sm font-bold ${state} ${index === currentIndex ? 'ring-2 ring-primary' : ''}`}>{subjectIndex + 1}</button>;
                            })}
                        </div>
                        {Object.keys(failed).length > 0 && (
                            <div className="mt-5 rounded-md border border-amber-200 bg-amber-50 p-3">
                                <div className="text-sm font-bold text-warning">Retry queue</div>
                                <p className="mt-1 text-xs text-slate-600">{Object.keys(failed).length} answer(s) waiting to sync.</p>
                                <Button type="button" variant="secondary" className="mt-3 w-full" disabled={!online || pending.size > 0} onClick={() => Object.values(failed).forEach((answer) => saveAnswer(answer))}>
                                    Retry Now
                                </Button>
                            </div>
                        )}
                    </aside>
                </main>

                <footer className="sticky bottom-0 border-t border-border bg-white px-4 py-3">
                    <div className="mx-auto flex max-w-7xl justify-between gap-3">
                        <Button type="button" variant="secondary" disabled={currentIndex === 0 || !payload.exam.settings.allow_back_navigation} onClick={goPrevious}>Previous</Button>
                        <div className="flex gap-2">
                            <Button type="button" variant="secondary" disabled={currentIndex === questions.length - 1} onClick={goNext}>Next</Button>
                            <Button type="button" disabled={submitting || pending.size > 0 || Object.keys(failed).length > 0} onClick={requestSubmit}>
                                {submitting && <Loader2 className="h-4 w-4 animate-spin" />}
                                Submit
                            </Button>
                        </div>
                    </div>
                </footer>
            </div>
        </CandidateShell>
    );
}

function SubmitSuccessPage() {
    return <SimpleScreen title="Submitted" message="Your exam has been submitted successfully." tone="success" />;
}

function ExamErrorPage() {
    return <SimpleScreen title="Exam Error" message="We could not load your exam. Please contact the exam administrator." tone="danger" />;
}

function DisqualifiedPage() {
    return <SimpleScreen title="Disqualified" message="This exam attempt has been disqualified." tone="danger" />;
}

function SimpleScreen({ title, message, tone }: { title: string; message: string; tone: 'success' | 'danger' }) {
    return (
        <CandidateShell>
            <div className="mx-auto flex min-h-screen max-w-lg items-center px-4">
                <div className="w-full rounded-md border border-border bg-white p-6 text-center shadow-sm">
                    <img src="/images/logo.png" alt="AlignEx" className="mx-auto mb-4 h-16 w-16 object-contain" />
                    {tone === 'success' ? <CheckCircle2 className="mx-auto h-12 w-12 text-success" /> : <AlertTriangle className="mx-auto h-12 w-12 text-danger" />}
                    <h1 className="mt-4 text-2xl font-bold text-slateDark">{title}</h1>
                    <p className="mt-2 text-sm text-slate-600">{message}</p>
                </div>
            </div>
        </CandidateShell>
    );
}

function CandidateShell({ children }: { children: React.ReactNode }) {
    return <div className="min-h-screen bg-surface text-slateDark">{children}</div>;
}

function Alert({ message, tone }: { message: string; tone: 'danger' }) {
    return <div className="rounded-md border border-red-200 bg-red-50 p-3 text-sm font-semibold text-danger">{message}</div>;
}

function Info({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md border border-border p-3"><div className="text-xs font-semibold uppercase text-slate-500">{label}</div><div className="mt-1 font-bold text-slateDark">{value}</div></div>;
}

function SummaryBox({ label, value, tone }: { label: string; value: number; tone: 'success' | 'warning' | 'danger' }) {
    const toneClass = {
        success: 'border-green-200 bg-green-50 text-success',
        warning: 'border-amber-200 bg-amber-50 text-warning',
        danger: 'border-red-200 bg-red-50 text-danger',
    }[tone];

    return (
        <div className={`rounded-md border p-2 text-center ${toneClass}`}>
            <div className="text-lg font-bold leading-none">{value}</div>
            <div className="mt-1 text-[10px] font-semibold uppercase">{label}</div>
        </div>
    );
}

async function api<T = any>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...(examToken() ? { Authorization: `Bearer ${examToken()}` } : {}),
            ...(options.headers ?? {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = payload.errors ?? {};
        const firstError = Object.values(errors)[0];
        throw new Error(Array.isArray(firstError) ? firstError[0] : payload.message ?? 'Request failed.');
    }

    return payload;
}

function examToken() {
    return localStorage.getItem(tokenKey);
}

function storedPayload(): ExamPayload | null {
    const raw = localStorage.getItem(payloadKey);
    return raw ? JSON.parse(raw) : null;
}

function questionList(payload: ExamPayload): ExamQuestion[] {
    return Array.isArray(payload.questions) ? payload.questions : payload.questions.data;
}

function subjectProgress(questions: ExamQuestion[], answers: Record<string, SavedAnswer>): SubjectSection[] {
    const grouped = new Map<string, SubjectSection>();

    questions.forEach((question, index) => {
        const key = subjectKey(question);
        const subject = question.subject_name ?? 'General';
        const existing = grouped.get(key) ?? { key, subject, total: 0, answered: 0, percent: 0, questionIndexes: [] };
        existing.total += 1;
        existing.answered += answers[question.question_id]?.selected_option_ids?.length ? 1 : 0;
        existing.questionIndexes.push(index);
        grouped.set(key, existing);
    });

    return Array.from(grouped.values()).map((summary) => ({
        ...summary,
        percent: summary.total > 0 ? Math.round((summary.answered / summary.total) * 100) : 0,
    }));
}

function subjectKey(question: ExamQuestion) {
    return question.subject_id ? `subject:${question.subject_id}` : `subject-name:${question.subject_name ?? 'General'}`;
}

function nextQuestionIndex(currentIndex: number, subjects: SubjectSection[], questionCount: number) {
    const currentSubjectIndex = subjects.findIndex((subject) => subject.questionIndexes.includes(currentIndex));
    const currentSubject = subjects[currentSubjectIndex];

    if (!currentSubject) {
        return Math.min(questionCount - 1, currentIndex + 1);
    }

    const currentPosition = currentSubject.questionIndexes.indexOf(currentIndex);
    const nextInSubject = currentSubject.questionIndexes[currentPosition + 1];

    if (nextInSubject !== undefined) {
        return nextInSubject;
    }

    const nextSubject = subjects[currentSubjectIndex + 1];

    return nextSubject?.questionIndexes[0] ?? currentIndex;
}

function previousQuestionIndex(currentIndex: number, subjects: SubjectSection[]) {
    const currentSubjectIndex = subjects.findIndex((subject) => subject.questionIndexes.includes(currentIndex));
    const currentSubject = subjects[currentSubjectIndex];

    if (!currentSubject) {
        return Math.max(0, currentIndex - 1);
    }

    const currentPosition = currentSubject.questionIndexes.indexOf(currentIndex);
    const previousInSubject = currentSubject.questionIndexes[currentPosition - 1];

    if (previousInSubject !== undefined) {
        return previousInSubject;
    }

    const previousSubject = subjects[currentSubjectIndex - 1];

    return previousSubject ? previousSubject.questionIndexes[previousSubject.questionIndexes.length - 1] : currentIndex;
}

function deviceFingerprint() {
    const existing = localStorage.getItem(fingerprintKey);

    if (existing) {
        return existing;
    }

    const next = `${navigator.userAgent}|${screen.width}x${screen.height}|${browserSafeId()}`;
    localStorage.setItem(fingerprintKey, next);
    return next;
}

function browserSafeId() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    if (window.crypto?.getRandomValues) {
        const values = new Uint32Array(4);
        window.crypto.getRandomValues(values);
        return Array.from(values, (value) => value.toString(16).padStart(8, '0')).join('');
    }

    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
}

function captureVideoFrame(video: HTMLVideoElement | null): string | null {
    if (!video || video.readyState < 2 || video.videoWidth === 0 || video.videoHeight === 0) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.75);
}

function formatTime(seconds: number) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return [hours, minutes, secs].map((value) => String(value).padStart(2, '0')).join(':');
}
