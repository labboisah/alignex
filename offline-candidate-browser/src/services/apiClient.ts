import axios, { AxiosError, type AxiosInstance } from 'axios';
import type {
    CandidateEventRequest,
    CandidateExamResponse,
    CandidateLoginRequest,
    CandidateLoginResponse,
    ExamDetails,
    ExamQuestion,
    HealthResponse,
    SaveAnswerRequest,
    SaveAnswerResponse,
    SubmitExamResponse,
} from '../types/api';

const serverUrlStorageKey = 'alignex_candidate_server_url';
const attemptTokenStorageKey = 'alignex_candidate_attempt_token';
const defaultServerUrl = import.meta.env.VITE_CANDIDATE_SERVER_URL || import.meta.env.VITE_DEFAULT_SERVER_URL || 'http://127.0.0.1:4080';
const mockAttemptToken = 'mock-attempt-token';
const mockCandidateId = 'mock-candidate-001';
const mockExamId = 'mock-exam-2026';
const mockStartedAt = new Date().toISOString();
const mockSaveAttempts = new Map<string, number>();

export class ApiClientError extends Error {
    constructor(
        message: string,
        readonly status?: number,
        readonly code?: string,
    ) {
        super(message);
        this.name = 'ApiClientError';
    }
}

export function getSavedServerUrl(): string | null {
    return localStorage.getItem(serverUrlStorageKey);
}

export function saveServerUrl(serverUrl: string): void {
    localStorage.setItem(serverUrlStorageKey, normalizeServerUrl(serverUrl));
}

export function clearServerUrl(): void {
    localStorage.removeItem(serverUrlStorageKey);
}

export function getAttemptToken(): string | null {
    return localStorage.getItem(attemptTokenStorageKey);
}

export function saveAttemptToken(token: string): void {
    localStorage.setItem(attemptTokenStorageKey, token);
}

export function clearAttemptToken(): void {
    localStorage.removeItem(attemptTokenStorageKey);
}

export function isMockApiEnabled(): boolean {
    return import.meta.env.DEV && String(import.meta.env.VITE_USE_MOCK_API ?? 'false').toLowerCase() === 'true';
}

export function normalizeServerUrl(value: string): string {
    const url = new URL(value.trim());
    url.pathname = url.pathname.replace(/\/+$/, '');
    url.search = '';
    url.hash = '';
    return url.toString().replace(/\/$/, '');
}

export function validateServerUrl(value: string): string | null {
    if (!value.trim()) {
        return 'Server URL is required.';
    }

    try {
        const url = new URL(value.trim());

        if (!['http:', 'https:'].includes(url.protocol)) {
            return 'Server URL must start with http:// or https://.';
        }

        if (!url.hostname) {
            return 'Server URL must include an IP address or hostname.';
        }

        return null;
    } catch {
        return 'Enter a valid server URL, for example http://192.168.1.10.';
    }
}

function createAxiosClient(baseUrl = getSavedServerUrl() ?? defaultServerUrl): AxiosInstance {
    const client = axios.create({
        baseURL: baseUrl,
        timeout: 10_000,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
    });

    client.interceptors.response.use(
        (response) => response,
        (error: AxiosError) => Promise.reject(toApiClientError(error)),
    );

    return client;
}

async function requestWithAttemptToken<T>(request: (client: AxiosInstance) => Promise<{ data: T }>): Promise<T> {
    const token = getAttemptToken();

    if (!token) {
        throw new ApiClientError('Candidate session is missing. Please login again.', 401, 'missing_attempt_token');
    }

    const client = createAxiosClient();
    client.defaults.headers.common.Authorization = `Bearer ${token}`;
    const response = await request(client);
    return response.data;
}

function toApiClientError(error: AxiosError): ApiClientError {
    if (error.code === 'ECONNABORTED') {
        return new ApiClientError('The request timed out. Confirm the Center Server is reachable and try again.', undefined, 'timeout');
    }

    if (!error.response) {
        return new ApiClientError('Unable to connect to Center Server. Confirm the IP address and network connection.', undefined, 'network_error');
    }

    const data = error.response.data;
    const message = extractBackendMessage(data) ?? friendlyStatusMessage(error.response.status);
    return new ApiClientError(message, error.response.status);
}

function extractBackendMessage(data: unknown): string | null {
    if (!data || typeof data !== 'object') {
        return null;
    }

    const record = data as Record<string, unknown>;

    if (typeof record.message === 'string' && record.message.trim()) {
        return record.message;
    }

    if (Array.isArray(record.errors)) {
        const first = record.errors.find((error) => typeof error === 'string');
        return typeof first === 'string' ? first : null;
    }

    if (record.errors && typeof record.errors === 'object') {
        const first = Object.values(record.errors as Record<string, unknown>).flat().find((error) => typeof error === 'string');
        return typeof first === 'string' ? first : null;
    }

    return null;
}

function friendlyStatusMessage(status: number): string {
    if (status === 401) return 'Your candidate session has expired. Please login again.';
    if (status === 403) return 'This action is not allowed.';
    if (status === 404) return 'The requested exam resource was not found.';
    if (status === 409) return 'This exam action cannot be completed right now.';
    if (status >= 500) return 'Center Server encountered an error. Please contact the supervisor.';
    return 'Request failed. Please try again.';
}

export const apiClient = {
    async healthCheck(serverUrl: string): Promise<HealthResponse> {
        if (isMockApiEnabled()) {
            return mockHealthCheck(serverUrl);
        }

        const validationError = validateServerUrl(serverUrl);

        if (validationError) {
            throw new ApiClientError(validationError, 422, 'invalid_server_url');
        }

        const client = createAxiosClient(normalizeServerUrl(serverUrl));
        const response = await client.get<HealthResponse>('/api/health');

        if (!response.data || typeof response.data !== 'object') {
            throw new ApiClientError('Center Server responded with an invalid health response.', 502, 'invalid_health_response');
        }

        return response.data;
    },

    async candidateLogin(payload: CandidateLoginRequest): Promise<CandidateLoginResponse> {
        if (isMockApiEnabled()) {
            return mockCandidateLogin(payload);
        }

        const response = await createAxiosClient().post<CandidateLoginResponse>('/api/candidate/login', payload);

        if (response.data.attempt_token) {
            saveAttemptToken(response.data.attempt_token);
        }

        return response.data;
    },

    async getCandidateExam(): Promise<CandidateExamResponse & { exam_with_questions: ExamDetails; questions_with_options: ExamQuestion[] }> {
        if (isMockApiEnabled()) {
            return mockCandidateExam();
        }

        const payload = await requestWithAttemptToken<CandidateExamResponse>((client) => client.get('/api/candidate/exam'));
        const optionsByQuestion = new Map<string, CandidateExamResponse['options']>();

        for (const option of payload.options) {
            optionsByQuestion.set(option.question_id, [...(optionsByQuestion.get(option.question_id) ?? []), option]);
        }

        const questions = payload.questions.map((question) => ({
            ...question,
            options: optionsByQuestion.get(question.id) ?? [],
        })) as ExamQuestion[];

        return {
            ...payload,
            questions_with_options: questions,
            exam_with_questions: {
                ...payload.exam,
                remaining_seconds: payload.remaining_time_seconds,
                questions,
            },
        };
    },

    async saveAnswer(payload: SaveAnswerRequest): Promise<SaveAnswerResponse> {
        if (isMockApiEnabled()) {
            return mockSaveAnswer(payload);
        }

        return requestWithAttemptToken<SaveAnswerResponse>((client) => client.post('/api/candidate/answer', payload));
    },

    async submitExam(): Promise<SubmitExamResponse> {
        if (isMockApiEnabled()) {
            return mockSubmitExam('submitted');
        }

        return requestWithAttemptToken<SubmitExamResponse>((client) => client.post('/api/candidate/submit'));
    },

    async autoSubmitExam(): Promise<SubmitExamResponse> {
        if (isMockApiEnabled()) {
            return mockSubmitExam('auto_submitted');
        }

        return requestWithAttemptToken<SubmitExamResponse>((client) => client.post('/api/candidate/auto-submit'));
    },

    async sendCandidateEvent(payload: CandidateEventRequest): Promise<{ success: boolean }> {
        if (isMockApiEnabled()) {
            if (import.meta.env.DEV) {
                console.info('[mock-api] candidate event', payload);
            }
            return delay(120).then(() => ({ success: true }));
        }

        return requestWithAttemptToken<{ success: boolean }>((client) => client.post('/api/candidate/event', payload));
    },
};

async function mockHealthCheck(serverUrl: string): Promise<HealthResponse> {
    const validationError = validateServerUrl(serverUrl);

    if (validationError) {
        throw new ApiClientError(validationError, 422, 'invalid_server_url');
    }

    await delay(250);
    return {
        status: 'ok',
        serverStatus: 'mock',
        timestamp: new Date().toISOString(),
    };
}

async function mockCandidateLogin(payload: CandidateLoginRequest): Promise<CandidateLoginResponse> {
    await delay(350);

    if (payload.registration_number.trim().toUpperCase() !== 'CAND-001') {
        throw new ApiClientError('Candidate not found.', 404, 'candidate_not_found');
    }

    saveAttemptToken(mockAttemptToken);

    return {
        attempt_token: mockAttemptToken,
        candidate: mockCandidate(),
        exam: mockExamDetails(),
        remaining_time_seconds: 60 * 60,
    };
}

async function mockCandidateExam(): Promise<CandidateExamResponse & { exam_with_questions: ExamDetails; questions_with_options: ExamQuestion[] }> {
    await delay(400);
    const questions = mockQuestions();
    const savedAnswers = [
        {
            question_id: questions[0].id,
            option_ids: [questions[0].options[1].id],
            text_answer: null,
            saved_at: new Date().toISOString(),
        },
    ];

    return {
        candidate: mockCandidate(),
        exam: mockExamDetails(),
        questions: questions.map(({ options: _options, ...question }) => question),
        options: questions.flatMap((question) => question.options),
        saved_answers: savedAnswers,
        remaining_time_seconds: 60 * 60,
        questions_with_options: questions,
        exam_with_questions: {
            ...mockExamDetails(),
            remaining_seconds: 60 * 60,
            questions,
        },
    };
}

async function mockSaveAnswer(payload: SaveAnswerRequest): Promise<SaveAnswerResponse> {
    await delay(250);

    if (shouldFailMockSave(payload.question_id)) {
        throw new ApiClientError('Mock save failure.', undefined, 'network_error');
    }

    return {
        success: true,
        answered_count: 1,
        total_questions: 50,
        saved_at: new Date().toISOString(),
    };
}

async function mockSubmitExam(status: 'submitted' | 'auto_submitted'): Promise<SubmitExamResponse> {
    await delay(650);

    return {
        success: true,
        candidate: mockCandidate(),
        exam: {
            id: mockExamId,
            exam_code: 'TEST2026',
            title: 'Mock CBT Practice Exam',
        },
        answered_count: 1,
        total_questions: 50,
        submitted_at: new Date().toISOString(),
        status,
    };
}

function mockCandidate() {
    return {
        id: mockCandidateId,
        full_name: 'Mock Candidate',
        registration_number: 'CAND-001',
        group_name: 'Mock Group',
    };
}

function mockExamDetails(): ExamDetails {
    return {
        id: mockExamId,
        exam_code: 'TEST2026',
        title: 'Mock CBT Practice Exam',
        organization_name: 'AlignEx Mock Center',
        duration_minutes: 60,
        total_questions: 50,
        started_at: mockStartedAt,
    };
}

function mockQuestions(): ExamQuestion[] {
    return Array.from({ length: 50 }, (_, index) => {
        const questionNumber = index + 1;
        const questionId = `mock-question-${questionNumber}`;

        return {
            id: questionId,
            subject_id: 'mock-subject',
            question_type: 'single_choice',
            body: `Mock question ${questionNumber}: choose the best answer from the options below.`,
            marks: 1,
            display_order: questionNumber,
            options: ['A', 'B', 'C', 'D'].map((label, optionIndex) => ({
                id: `${questionId}-option-${label}`,
                question_id: questionId,
                option_label: label,
                body: `Option ${label} for mock question ${questionNumber}`,
                display_order: optionIndex + 1,
            })),
        };
    });
}

function shouldFailMockSave(questionId: string): boolean {
    if (String(import.meta.env.VITE_MOCK_SAVE_FAILURES ?? 'false').toLowerCase() !== 'true') {
        return false;
    }

    const attemptCount = mockSaveAttempts.get(questionId) ?? 0;
    mockSaveAttempts.set(questionId, attemptCount + 1);

    return questionId.endsWith('7') && attemptCount === 0;
}

function delay(milliseconds: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, milliseconds));
}
