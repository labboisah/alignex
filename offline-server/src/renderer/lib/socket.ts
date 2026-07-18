export type BrowserSocket = {
    on: (event: string, callback: (payload?: unknown) => void) => BrowserSocket;
    off: (event: string, callback?: (payload?: unknown) => void) => BrowserSocket;
    emit: (event: string, payload?: unknown) => BrowserSocket;
    disconnect: () => void;
};

type SocketFactory = (url?: string, options?: Record<string, unknown>) => BrowserSocket;

declare global {
    interface Window {
        io?: SocketFactory;
    }
}

let loadingPromise: Promise<SocketFactory> | null = null;

export async function loadSocketIo(): Promise<SocketFactory> {
    if (window.io) {
        return window.io;
    }

    if (!loadingPromise) {
        loadingPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/socket.io/socket.io.js';
            script.async = true;
            script.onload = () => {
                if (window.io) {
                    resolve(window.io);
                    return;
                }

                reject(new Error('Socket.IO client did not initialize.'));
            };
            script.onerror = () => reject(new Error('Unable to load Socket.IO client.'));
            document.head.appendChild(script);
        });
    }

    return loadingPromise;
}
