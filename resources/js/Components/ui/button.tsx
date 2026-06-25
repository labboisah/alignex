import { Slot } from '@radix-ui/react-slot';
import { type ButtonHTMLAttributes } from 'react';
import { cn } from '../../lib/cn';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    asChild?: boolean;
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
};

export function Button({ asChild, className, variant = 'primary', ...props }: ButtonProps) {
    const Comp = asChild ? Slot : 'button';
    const variants = {
        primary: 'bg-primary text-white hover:bg-primaryDark',
        secondary: 'border border-border bg-white text-slateDark hover:bg-slate-50',
        ghost: 'text-slateDark hover:bg-slate-100',
        danger: 'bg-danger text-white hover:bg-red-700',
    };

    return (
        <Comp
            className={cn(
                'inline-flex h-10 items-center justify-center gap-2 rounded-md px-4 text-sm font-semibold transition disabled:pointer-events-none disabled:opacity-50',
                variants[variant],
                className,
            )}
            {...props}
        />
    );
}
