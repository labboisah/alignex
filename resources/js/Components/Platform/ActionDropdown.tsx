import { ChevronDown, LucideIcon } from 'lucide-react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/cn';

export type ActionDropdownItem = {
    label: string;
    icon?: LucideIcon;
    destructive?: boolean;
    disabled?: boolean;
    onSelect: () => void;
};

export function ActionDropdown({ label = 'Actions', items, trigger }: { label?: string; items: ActionDropdownItem[]; trigger?: ReactNode }) {
    const [open, setOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnOutsideClick = (event: MouseEvent) => {
            if (!menuRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    return (
        <div ref={menuRef} className="relative inline-flex">
            {trigger ? (
                <button type="button" onClick={() => setOpen((current) => !current)} className="inline-flex">
                    {trigger}
                </button>
            ) : (
                <Button variant="secondary" type="button" onClick={() => setOpen((current) => !current)} aria-expanded={open} aria-haspopup="menu">
                    {label}
                    <ChevronDown className="h-4 w-4" />
                </Button>
            )}

            {open && (
                <div className="absolute right-0 top-full z-50 mt-1 min-w-48 rounded-md border border-border bg-white p-1 shadow-lg" role="menu">
                    {items.map((item) => {
                        const Icon = item.icon;

                        return (
                            <button
                                key={item.label}
                                type="button"
                                disabled={item.disabled}
                                onClick={() => {
                                    if (item.disabled) {
                                        return;
                                    }

                                    setOpen(false);
                                    item.onSelect();
                                }}
                                className={cn(
                                    'flex w-full cursor-pointer items-center gap-2 rounded px-3 py-2 text-left text-sm outline-none hover:bg-slate-100 disabled:pointer-events-none disabled:opacity-50',
                                    item.destructive ? 'text-danger' : 'text-slate-700',
                                )}
                                role="menuitem"
                            >
                                {Icon && <Icon className="h-4 w-4" />}
                                {item.label}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
