import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, type ReactNode } from 'react';
import { apiClient, clearAttemptToken, clearServerUrl, getAttemptToken, getSavedServerUrl, saveAttemptToken, saveServerUrl } from '../services/apiClient';
import type { CandidateLoginResponse, CandidateProfile, ExamDetails, ExamQuestion, SaveAnswerRequest, SavedAnswer, SubmissionSummary, SubmitExamResponse } from '../types/api';

type ConnectionStatus = 'unknown' | 'connected' | 'reconnecting' | 'disconnected' | 'syncing';
type SaveStatus = 'idle' | 'saving' | 'saved' | 'failed' | 'syncing';
type SubmissionStatus = 'idle' | 'submitting' | 'submitted' | 'auto_submitted' | 'failed';

export type PendingAnswer = SaveAnswerRequest & {
    queued_at: string;
};

type PersistedExamSession = {
    server_url: string | null;
    attempt_token: string | null;
    candidate: CandidateProfile | null;
    exam: ExamDetails | null;
    questions: ExamQuestion[];
    saved_answers: Record<string, SavedAnswer>;
    selected_answers: Record<string, string | null>;
    pending_answers: Record<string, PendingAnswer>;
    current_question_index: number;
    remaining_time_seconds: number | null;
    submission_summary: SubmissionSummary | null;
};

export type ExamSessionState = PersistedExamSession & {
    connection_status: ConnectionStatus;
    save_status: SaveStatus;
    submission_status: SubmissionStatus;
    hydrated: boolean;
};

type ExamSessionContextValue = ExamSessionState & {
    setServerUrl: (serverUrl: string | null) => void;
    setLoginSession: (response: CandidateLoginResponse) => void;
    clearLoginSession: () => void;
    setExamData: (payload: { exam?: ExamDetails; questions?: ExamQuestion[]; saved_answers?: Record<string, SavedAnswer>; selected_answers?: Record<string, string | null>; remaining_time_seconds?: number }) => void;
    tickRemainingTime: () => void;
    setCurrentQuestion: (index: number) => void;
    selectAnswerLocally: (questionId: string, selectedOptionId: string | null) => void;
    markAnswerSaving: () => void;
    markAnswerSyncing: () => void;
    markAnswersSynced: () => void;
    markAnswerSaved: (answer: SavedAnswer) => void;
    markAnswerFailed: () => void;
    addPendingAnswer: (answer: SaveAnswerRequest) => void;
    removePendingAnswer: (questionId: string) => void;
    completeSubmission: (response: SubmitExamResponse) => void;
    clearExamSession: () => void;
    restoreSession: () => Promise<boolean>;
    setConnectionStatus: (status: ConnectionStatus) => void;
    setSubmissionStatus: (status: SubmissionStatus) => void;
};

type Action =
    | { type: 'hydrate'; payload: PersistedExamSession }
    | { type: 'set_server_url'; payload: string | null }
    | { type: 'set_login_session'; payload: CandidateLoginResponse }
    | { type: 'clear_login_session' }
    | { type: 'set_exam_data'; payload: { exam?: ExamDetails; questions?: ExamQuestion[]; saved_answers?: Record<string, SavedAnswer>; selected_answers?: Record<string, string | null>; remaining_time_seconds?: number } }
    | { type: 'tick_remaining_time' }
    | { type: 'set_current_question'; payload: number }
    | { type: 'select_answer_locally'; payload: { questionId: string; selectedOptionId: string | null } }
    | { type: 'mark_answer_saving' }
    | { type: 'mark_answer_syncing' }
    | { type: 'mark_answers_synced' }
    | { type: 'mark_answer_saved'; payload: SavedAnswer }
    | { type: 'mark_answer_failed' }
    | { type: 'add_pending_answer'; payload: PendingAnswer }
    | { type: 'remove_pending_answer'; payload: string }
    | { type: 'complete_submission'; payload: SubmitExamResponse }
    | { type: 'clear_exam_session' }
    | { type: 'set_connection_status'; payload: ConnectionStatus }
    | { type: 'set_submission_status'; payload: SubmissionStatus };

const storageKey = 'alignex_candidate_exam_session';

const emptyPersistedSession: PersistedExamSession = {
    server_url: null,
    attempt_token: null,
    candidate: null,
    exam: null,
    questions: [],
    saved_answers: {},
    selected_answers: {},
    pending_answers: {},
    current_question_index: 0,
    remaining_time_seconds: null,
    submission_summary: null,
};

const initialState: ExamSessionState = {
    ...emptyPersistedSession,
    server_url: getSavedServerUrl(),
    attempt_token: getAttemptToken(),
    connection_status: getSavedServerUrl() ? 'unknown' : 'disconnected',
    save_status: 'idle',
    submission_status: 'idle',
    hydrated: false,
};

const ExamSessionContext = createContext<ExamSessionContextValue | null>(null);

export function ExamSessionProvider({ children }: { children: ReactNode }) {
    const [state, dispatch] = useReducer(reducer, initialState);

    useEffect(() => {
        dispatch({ type: 'hydrate', payload: loadPersistedSession() });
    }, []);

    useEffect(() => {
        if (!state.hydrated) {
            return;
        }

        persistSession(state);
    }, [state]);

    const restoreSession = useCallback(async () => {
        const token = state.attempt_token ?? getAttemptToken();

        if (!token) {
            return false;
        }

        try {
            const exam = await apiClient.getCandidateExam();
            dispatch({
                type: 'set_exam_data',
                payload: {
                    exam: exam.exam_with_questions,
                    questions: exam.questions_with_options,
                    saved_answers: savedAnswerMapFromServer(exam.saved_answers),
                    selected_answers: selectedAnswerMapFromServer(exam.saved_answers),
                    remaining_time_seconds: exam.remaining_time_seconds,
                },
            });
            dispatch({ type: 'set_connection_status', payload: 'connected' });
            return true;
        } catch {
            dispatch({ type: 'set_connection_status', payload: 'disconnected' });
            return false;
        }
    }, [state.attempt_token]);

    const value = useMemo<ExamSessionContextValue>(
        () => ({
            ...state,
            setServerUrl: (serverUrl) => dispatch({ type: 'set_server_url', payload: serverUrl }),
            setLoginSession: (response) => dispatch({ type: 'set_login_session', payload: response }),
            clearLoginSession: () => dispatch({ type: 'clear_login_session' }),
            setExamData: (payload) => dispatch({ type: 'set_exam_data', payload }),
            tickRemainingTime: () => dispatch({ type: 'tick_remaining_time' }),
            setCurrentQuestion: (index) => dispatch({ type: 'set_current_question', payload: index }),
            selectAnswerLocally: (questionId, selectedOptionId) => dispatch({ type: 'select_answer_locally', payload: { questionId, selectedOptionId } }),
            markAnswerSaving: () => dispatch({ type: 'mark_answer_saving' }),
            markAnswerSyncing: () => dispatch({ type: 'mark_answer_syncing' }),
            markAnswersSynced: () => dispatch({ type: 'mark_answers_synced' }),
            markAnswerSaved: (answer) => dispatch({ type: 'mark_answer_saved', payload: answer }),
            markAnswerFailed: () => dispatch({ type: 'mark_answer_failed' }),
            addPendingAnswer: (answer) => dispatch({ type: 'add_pending_answer', payload: { ...answer, queued_at: new Date().toISOString() } }),
            removePendingAnswer: (questionId) => dispatch({ type: 'remove_pending_answer', payload: questionId }),
            completeSubmission: (response) => dispatch({ type: 'complete_submission', payload: response }),
            clearExamSession: () => dispatch({ type: 'clear_exam_session' }),
            restoreSession,
            setConnectionStatus: (status) => dispatch({ type: 'set_connection_status', payload: status }),
            setSubmissionStatus: (status) => dispatch({ type: 'set_submission_status', payload: status }),
        }),
        [restoreSession, state],
    );

    return <ExamSessionContext.Provider value={value}>{children}</ExamSessionContext.Provider>;
}

export function useExamSession() {
    const context = useContext(ExamSessionContext);

    if (!context) {
        throw new Error('useExamSession must be used inside ExamSessionProvider.');
    }

    return context;
}

function reducer(state: ExamSessionState, action: Action): ExamSessionState {
    switch (action.type) {
        case 'hydrate':
            return {
                ...state,
                ...action.payload,
                server_url: action.payload.server_url ?? getSavedServerUrl(),
                attempt_token: action.payload.attempt_token ?? getAttemptToken(),
                hydrated: true,
            };
        case 'set_server_url':
            if (action.payload) {
                saveServerUrl(action.payload);
            } else {
                clearServerUrl();
            }
            return { ...state, server_url: action.payload, connection_status: action.payload ? 'connected' : 'disconnected' };
        case 'set_login_session':
            saveAttemptToken(action.payload.attempt_token);
            return {
                ...state,
                attempt_token: action.payload.attempt_token,
                candidate: action.payload.candidate,
                exam: action.payload.exam,
                questions: action.payload.exam.questions ?? state.questions,
                remaining_time_seconds: action.payload.remaining_time_seconds,
                connection_status: 'connected',
                submission_status: 'idle',
            };
        case 'clear_login_session':
            clearAttemptToken();
            return {
                ...state,
                attempt_token: null,
                candidate: null,
                exam: null,
                questions: [],
                saved_answers: {},
                selected_answers: {},
                pending_answers: {},
                current_question_index: 0,
                remaining_time_seconds: null,
                save_status: 'idle',
                submission_status: 'idle',
            };
        case 'set_exam_data':
            return {
                ...state,
                exam: action.payload.exam ?? state.exam,
                questions: action.payload.questions ?? state.questions,
                saved_answers: action.payload.saved_answers ?? state.saved_answers,
                selected_answers: action.payload.selected_answers ?? state.selected_answers,
                remaining_time_seconds: action.payload.remaining_time_seconds ?? state.remaining_time_seconds,
            };
        case 'tick_remaining_time':
            return {
                ...state,
                remaining_time_seconds: state.remaining_time_seconds === null ? null : Math.max(0, state.remaining_time_seconds - 1),
            };
        case 'set_current_question':
            return { ...state, current_question_index: Math.max(0, action.payload) };
        case 'select_answer_locally':
            return {
                ...state,
                selected_answers: {
                    ...state.selected_answers,
                    [action.payload.questionId]: action.payload.selectedOptionId,
                },
            };
        case 'mark_answer_saving':
            return { ...state, save_status: 'saving' };
        case 'mark_answer_syncing':
            return { ...state, save_status: 'syncing' };
        case 'mark_answers_synced':
            return { ...state, save_status: 'saved' };
        case 'mark_answer_saved':
            return {
                ...state,
                save_status: 'saved',
                saved_answers: {
                    ...state.saved_answers,
                    [action.payload.question_id]: action.payload,
                },
                pending_answers: omitKey(state.pending_answers, action.payload.question_id),
            };
        case 'mark_answer_failed':
            return { ...state, save_status: 'failed' };
        case 'add_pending_answer':
            return {
                ...state,
                pending_answers: {
                    ...state.pending_answers,
                    [action.payload.question_id]: action.payload,
                },
            };
        case 'remove_pending_answer':
            return { ...state, pending_answers: omitKey(state.pending_answers, action.payload) };
        case 'complete_submission': {
            const summary = buildSubmissionSummary(state, action.payload);

            if (!summary) {
                return { ...state, submission_status: action.payload.status };
            }

            clearAttemptToken();
            return {
                ...emptyPersistedSession,
                server_url: state.server_url,
                connection_status: state.connection_status,
                save_status: 'saved',
                submission_status: action.payload.status,
                submission_summary: summary,
                hydrated: true,
            };
        }
        case 'clear_exam_session':
            clearAttemptToken();
            localStorage.removeItem(storageKey);
            return {
                ...emptyPersistedSession,
                server_url: state.server_url,
                connection_status: state.connection_status,
                save_status: 'idle',
                submission_status: 'idle',
                hydrated: true,
            };
        case 'set_connection_status':
            return { ...state, connection_status: action.payload };
        case 'set_submission_status':
            return { ...state, submission_status: action.payload };
        default:
            return state;
    }
}

function persistSession(state: ExamSessionState): void {
    const persisted: PersistedExamSession = {
        server_url: state.server_url,
        attempt_token: state.attempt_token,
        candidate: state.candidate,
        exam: state.exam,
        questions: state.questions,
        saved_answers: state.saved_answers,
        selected_answers: state.selected_answers,
        pending_answers: state.pending_answers,
        current_question_index: state.current_question_index,
        remaining_time_seconds: state.remaining_time_seconds,
        submission_summary: state.submission_summary,
    };

    localStorage.setItem(storageKey, JSON.stringify(persisted));
}

function loadPersistedSession(): PersistedExamSession {
    const parsed = parseJson<Partial<PersistedExamSession>>(localStorage.getItem(storageKey));

    return {
        ...emptyPersistedSession,
        ...parsed,
        server_url: parsed?.server_url ?? getSavedServerUrl(),
        attempt_token: parsed?.attempt_token ?? getAttemptToken(),
    };
}

function buildSubmissionSummary(state: ExamSessionState, response: SubmitExamResponse): SubmissionSummary | null {
    const candidate = response.candidate ?? state.candidate;
    const exam = response.exam ?? (state.exam ? { id: state.exam.id, exam_code: state.exam.exam_code, title: state.exam.title } : null);

    if (!candidate || !exam) {
        return null;
    }

    return {
        candidate,
        exam,
        answered_count: response.answered_count,
        total_questions: response.total_questions,
        submitted_at: response.submitted_at,
        status: response.status,
        score: response.score,
        total_marks: response.total_marks,
    };
}

function parseJson<T>(value: string | null): T | null {
    if (!value) {
        return null;
    }

    try {
        return JSON.parse(value) as T;
    } catch {
        return null;
    }
}

function omitKey<T>(record: Record<string, T>, key: string): Record<string, T> {
    const next = { ...record };
    delete next[key];
    return next;
}

function savedAnswerMapFromServer(savedAnswers: Array<{ question_id: string; option_ids: string[]; saved_at: string }>): Record<string, SavedAnswer> {
    return Object.fromEntries(
        savedAnswers.map((answer) => [
            answer.question_id,
            {
                question_id: answer.question_id,
                selected_option_id: answer.option_ids[0] ?? null,
                saved_at: answer.saved_at,
            },
        ]),
    );
}

function selectedAnswerMapFromServer(savedAnswers: Array<{ question_id: string; option_ids: string[] }>): Record<string, string | null> {
    return Object.fromEntries(savedAnswers.map((answer) => [answer.question_id, answer.option_ids[0] ?? null]));
}
