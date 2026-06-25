import * as Dialog from '@radix-ui/react-dialog';
import { AlertTriangle, X } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';

export function ConfirmDialog({ trigger, title, description, confirmLabel = 'Confirm', cancelLabel = 'Cancel', destructive = false, onConfirm }: { trigger: ReactNode; title: string; description?: string; confirmLabel?: string; cancelLabel?: string; destructive?: boolean; onConfirm: () => void }) {
    return (
        <Dialog.Root>
            <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-40 bg-slate-950/40" />
                <Dialog.Content className="fixed left-1/2 top-1/2 z-50 w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-md border border-border bg-white p-5 shadow-xl">
                    <div className="flex items-start gap-3">
                        <div className="rounded-md bg-red-50 p-2 text-danger">
                            <AlertTriangle className="h-5 w-5" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <Dialog.Title className="font-semibold text-slateDark">{title}</Dialog.Title>
                            {description && <Dialog.Description className="mt-2 text-sm leading-6 text-slate-600">{description}</Dialog.Description>}
                        </div>
                        <Dialog.Close className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                            <X className="h-4 w-4" />
                        </Dialog.Close>
                    </div>
                    <div className="mt-6 flex justify-end gap-2">
                        <Dialog.Close asChild>
                            <Button variant="secondary" type="button">{cancelLabel}</Button>
                        </Dialog.Close>
                        <Dialog.Close asChild>
                            <Button variant={destructive ? 'danger' : 'primary'} type="button" onClick={onConfirm}>{confirmLabel}</Button>
                        </Dialog.Close>
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
