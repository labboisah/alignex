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
    const [placement, setPlacement] = useState<'up' | 'down'>('down');
    const menuRef = useRef<HTMLDivElement>(null);

    const toggleOpen = () => {
        const rect = menuRef.current?.getBoundingClientRect();

        if (rect) {
            setPlacement(window.innerHeight - rect.bottom < 220 && rect.top > 220 ? 'up' : 'down');
        }

        setOpen((current) => !current);
    };

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
                <button type="button" onClick={toggleOpen} className="inline-flex" aria-expanded={open} aria-haspopup="menu">
                    {trigger}
                </button>
            ) : (
                <Button variant="secondary" type="button" onClick={toggleOpen} aria-expanded={open} aria-haspopup="menu">
                    {label}
                    <ChevronDown className="h-4 w-4" />
                </Button>
            )}

            {open && (
                <div
                    className={cn(
                        'absolute right-0 z-50 min-w-48 rounded-md border border-border bg-white p-1 shadow-lg',
                        placement === 'up' ? 'bottom-full mb-1' : 'top-full mt-1',
                    )}
                    role="menu"
                >
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
