import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { ChevronDown, LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
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
    return (
        <DropdownMenu.Root>
            <DropdownMenu.Trigger asChild>
                {trigger ?? (
                    <Button variant="secondary" type="button">
                        {label}
                        <ChevronDown className="h-4 w-4" />
                    </Button>
                )}
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content align="end" className="z-50 min-w-48 rounded-md border border-border bg-white p-1 shadow-lg">
                    {items.map((item) => {
                        const Icon = item.icon;
                        return (
                            <DropdownMenu.Item
                                key={item.label}
                                disabled={item.disabled}
                                onSelect={item.onSelect}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded px-3 py-2 text-sm outline-none hover:bg-slate-100 data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                    item.destructive ? 'text-danger' : 'text-slate-700',
                                )}
                            >
                                {Icon && <Icon className="h-4 w-4" />}
                                {item.label}
                            </DropdownMenu.Item>
                        );
                    })}
                </DropdownMenu.Content>
            </DropdownMenu.Portal>
        </DropdownMenu.Root>
    );
}
