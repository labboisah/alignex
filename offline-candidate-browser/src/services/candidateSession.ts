import type { CandidateLoginResponse, CandidateProfile, ExamDetails } from '../types/api';

const candidateProfileKey = 'alignex_candidate_profile';
const examDetailsKey = 'alignex_candidate_exam_details';
const remainingTimeKey = 'alignex_candidate_remaining_time_seconds';

export type StoredCandidateSession = {
    candidate: CandidateProfile;
    exam: ExamDetails;
    remainingTimeSeconds: number;
};

export function saveCandidateSession(response: CandidateLoginResponse): void {
    localStorage.setItem(candidateProfileKey, JSON.stringify(response.candidate));
    localStorage.setItem(examDetailsKey, JSON.stringify(response.exam));
    localStorage.setItem(remainingTimeKey, String(response.remaining_time_seconds));
}

export function getCandidateSession(): StoredCandidateSession | null {
    const candidate = parseJson<CandidateProfile>(localStorage.getItem(candidateProfileKey));
    const exam = parseJson<ExamDetails>(localStorage.getItem(examDetailsKey));
    const remainingTimeSeconds = Number(localStorage.getItem(remainingTimeKey));

    if (!candidate || !exam || !Number.isFinite(remainingTimeSeconds)) {
        return null;
    }

    return { candidate, exam, remainingTimeSeconds };
}

export function clearCandidateSession(): void {
    localStorage.removeItem(candidateProfileKey);
    localStorage.removeItem(examDetailsKey);
    localStorage.removeItem(remainingTimeKey);
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
