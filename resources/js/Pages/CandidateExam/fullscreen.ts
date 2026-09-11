type FullscreenDocument = Document & { webkitFullscreenElement?: Element | null };
type FullscreenElement = HTMLElement & { webkitRequestFullscreen?: () => void | Promise<void> };

export function isExamFullscreen(): boolean {
    return Boolean(document.fullscreenElement || (document as FullscreenDocument).webkitFullscreenElement);
}

export function watchExamFullscreen(listener: () => void): () => void {
    document.addEventListener('fullscreenchange', listener);
    document.addEventListener('webkitfullscreenchange', listener);
    return () => {
        document.removeEventListener('fullscreenchange', listener);
        document.removeEventListener('webkitfullscreenchange', listener);
    };
}

export async function enterExamFullscreen(): Promise<void> {
    if (isExamFullscreen()) return;
    const element = document.documentElement as FullscreenElement;
    const request = typeof element.requestFullscreen === 'function'
        ? element.requestFullscreen
        : element.webkitRequestFullscreen;
    if (typeof request !== 'function') {
        throw new Error('This phone or browser cannot enter fullscreen. This exam requires fullscreen: use a supported device, or ask the exam administrator whether fullscreen can be disabled for this exam.');
    }

    await new Promise<void>((resolve, reject) => {
        const finish = (error?: Error) => {
            cleanup();
            clearTimeout(timeout);
            error ? reject(error) : resolve();
        };
        const cleanup = watchExamFullscreen(() => { if (isExamFullscreen()) finish(); });
        const timeout = setTimeout(() => finish(new Error('Fullscreen did not open. Tap Enable exam controls again, or use a browser that supports fullscreen.')), 8000);
        try {
            Promise.resolve(request.call(element)).then(() => {
                if (isExamFullscreen()) finish();
            }, () => finish(new Error('Fullscreen was not allowed. Tap the exam controls button again and allow fullscreen, or use a supported browser.')));
        } catch {
            finish(new Error('Fullscreen could not be enabled. Try a supported browser or contact your exam administrator.'));
        }
    });
}
