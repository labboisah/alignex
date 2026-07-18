export function logDevelopmentError(context: string, error: unknown): void {
    if (import.meta.env.DEV) {
        console.error(context, error);
    }
}
