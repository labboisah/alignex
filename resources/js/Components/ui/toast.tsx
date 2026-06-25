import * as ToastPrimitive from '@radix-ui/react-toast';
import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';

type Toast = { title: string; description?: string };
type ToastContextValue = { showToast: (toast: Toast) => void };

const ToastContext = createContext<ToastContextValue>({ showToast: () => null });

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toast, setToast] = useState<Toast | null>(null);
    const value = useMemo(() => ({ showToast: setToast }), []);

    return (
        <ToastContext.Provider value={value}>
            <ToastPrimitive.Provider swipeDirection="right">
                {children}
                <ToastPrimitive.Root
                    open={Boolean(toast)}
                    onOpenChange={(open) => !open && setToast(null)}
                    className="rounded-md border border-border bg-white p-4 shadow-lg"
                >
                    <ToastPrimitive.Title className="text-sm font-semibold text-slateDark">{toast?.title}</ToastPrimitive.Title>
                    {toast?.description && <ToastPrimitive.Description className="mt-1 text-sm text-slate-600">{toast.description}</ToastPrimitive.Description>}
                </ToastPrimitive.Root>
                <ToastPrimitive.Viewport className="fixed bottom-5 right-5 z-50 w-80 max-w-[calc(100vw-2rem)]" />
            </ToastPrimitive.Provider>
        </ToastContext.Provider>
    );
}

export function useToast() {
    return useContext(ToastContext);
}
