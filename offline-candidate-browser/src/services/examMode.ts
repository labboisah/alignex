export function isExamLockdownEnabled(): boolean {
    return String(import.meta.env.VITE_ENABLE_EXAM_LOCKDOWN ?? 'false').toLowerCase() === 'true';
}

export async function enterExamMode(): Promise<void> {
    if (!isExamLockdownEnabled()) {
        return;
    }

    await window.alignexCandidate?.enterExamMode?.();
}

export async function exitExamMode(): Promise<void> {
    if (!isExamLockdownEnabled()) {
        return;
    }

    await window.alignexCandidate?.exitExamMode?.();
}
