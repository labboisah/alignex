import type { ReactNode } from 'react';
import { Button } from './button';

type ConfirmDialogProps = {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    confirmTone?: 'default' | 'danger';
    loading?: boolean;
    onConfirm: () => void;
    onOpenChange: (open: boolean) => void;
    children?: ReactNode;
};

export function ConfirmDialog({
    open,
    title,
    message,
    confirmLabel = 'Confirm',
    confirmTone = 'default',
    loading = false,
    onConfirm,
    onOpenChange,
    children,
}: ConfirmDialogProps) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slateDark/50 p-4">
            <div className="w-full max-w-md rounded-md border border-border bg-white p-5 shadow-xl">
                <div className="text-lg font-semibold text-slateDark">{title}</div>
                <div className="mt-2 text-sm leading-6 text-slate-600">{message}</div>
                {children && <div className="mt-4">{children}</div>}
                <div className="mt-6 flex justify-end gap-3">
                    <Button disabled={loading} onClick={() => onOpenChange(false)} variant="secondary">
                        Cancel
                    </Button>
                    <Button
                        disabled={loading}
                        onClick={onConfirm}
                        variant={confirmTone === 'danger' ? 'danger' : 'default'}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
